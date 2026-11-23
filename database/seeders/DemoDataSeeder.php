<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\ExpenseCategory;
use App\Enums\MovementType;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Brand;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use Database\Factories\PartyFactory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic demo dataset on top of TestSeeder: products, parties,
 * purchases and sales spread across the last three months with payments that
 * match the paid amounts, returns, expenses and stock movements.
 *
 * Document numbers are generated from the seeded document sequences.
 * Re-run it only on a freshly seeded database; it does not guard against
 * duplicate document numbers on a second run.
 */
class DemoDataSeeder extends Seeder
{
    private const TAX_RATE = 0.10;

    public int $extraProducts = 20;

    public int $extraParties = 8;

    public int $purchases = 8;

    public int $sales = 25;

    public int $salesReturns = 3;

    public int $purchaseReturns = 2;

    public int $expenses = 6;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Product::exists() || ! Party::exists()) {
            throw new \LogicException(
                'DemoDataSeeder expects the TestSeeder baseline. Run it first.'
            );
        }

        $user = User::query()->firstOrFail();
        $warehouse = Warehouse::query()->firstOrFail();
        $cashbox = Cashbox::query()->firstOrFail();

        // Grow the catalog and the trading partners beyond the baseline
        ProductFactory::new()->count($this->extraProducts)->create();
        PartyFactory::new()
            ->count(max($this->extraParties - 2, 0))
            ->create();

        $products = Product::query()->get();
        $customers = Party::query()
            ->whereIn('type', [PartyType::CUSTOMER, PartyType::BOTH])
            ->get();
        $suppliers = Party::query()
            ->whereIn('type', [PartyType::SUPPLIER, PartyType::BOTH])
            ->get();

        // Suppliers distribute the stocked brands
        $suppliers->each(
            fn (Party $supplier) => $supplier->brands()->syncWithoutDetaching(Brand::all()->modelKeys()),
        );

        for ($i = 0; $i < $this->purchases; $i++) {
            $this->seedPurchase($suppliers->random(), $warehouse, $cashbox, $user, $products);
        }

        for ($i = 0; $i < $this->sales; $i++) {
            $this->seedSale($customers->random(), $warehouse, $cashbox, $user, $products);
        }

        for ($i = 0; $i < $this->purchaseReturns; $i++) {
            $this->seedPurchaseReturn(Purchase::query()->inRandomOrder()->first(), $user);
        }

        for ($i = 0; $i < $this->salesReturns; $i++) {
            $this->seedSalesReturn(
                Sale::query()->where('status', '!=', DocumentStatus::DRAFT)->inRandomOrder()->first(),
                $user,
            );
        }

        for ($i = 0; $i < $this->expenses; $i++) {
            $this->seedExpense($cashbox, $user);
        }
    }

    /**
     * Seed a purchase in the last three months, paid in full, in part, or not at all.
     */
    private function seedPurchase(
        Party $supplier,
        Warehouse $warehouse,
        Cashbox $cashbox,
        User $user,
        Collection $products,
    ): Purchase {
        $items = $products
            ->random(fake()->numberBetween(1, 4))
            ->values()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'quantity' => fake()->numberBetween(5, 40),
            ]);

        $subtotal = round($items->sum(
            fn (array $item): float => $item['quantity'] * (float) $item['product']->purchase_price
        ), 2);
        $discountAmount = fake()->boolean(30) ? round($subtotal * 0.05, 2) : 0;
        $taxAmount = round($subtotal * self::TAX_RATE, 2);
        $totalAmount = round($subtotal - $discountAmount + $taxAmount, 2);

        [$status, $paidAmount] = $this->paidScenario($totalAmount);

        $invoiceDate = fake()->dateTimeBetween('-90 days');

        $purchase = Purchase::query()->create([
            'document_number' => DocumentNumber::next('purchase'),
            'party_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => $invoiceDate,
            'due_date' => (clone $invoiceDate)->modify('+30 days'),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => round($totalAmount - $paidAmount, 2),
            'status' => $status,
            'created_by' => $user->id,
        ]);

        $purchase->items()->createMany(
            $items->map(fn (array $item): array => [
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'sku' => $item['product']->sku,
                'quantity' => $item['quantity'],
                'unit_price' => $item['product']->purchase_price,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => round($item['quantity'] * (float) $item['product']->purchase_price, 2),
            ])->all()
        );

        if ($paidAmount > 0) {
            $this->seedDocumentPayment($purchase, $cashbox, $user);
        }

        $purchase->items->each(fn ($item) => StockMovement::query()->create([
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => MovementType::IN,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_price,
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'movement_date' => $invoiceDate,
            'created_by' => $user->id,
        ]));

        return $purchase;
    }

    /**
     * Seed a sale in the last two months, paid in full, in part, not at all, or left as a draft.
     */
    private function seedSale(
        Party $customer,
        Warehouse $warehouse,
        Cashbox $cashbox,
        User $user,
        Collection $products,
    ): Sale {
        $items = $products
            ->random(fake()->numberBetween(1, 4))
            ->values()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'quantity' => fake()->numberBetween(1, 6),
            ]);

        $subtotal = round($items->sum(
            fn (array $item): float => $item['quantity'] * (float) $item['product']->selling_price
        ), 2);
        $discountAmount = fake()->boolean(30) ? round($subtotal * 0.05, 2) : 0;
        $taxAmount = round($subtotal * self::TAX_RATE, 2);
        $totalAmount = round($subtotal - $discountAmount + $taxAmount, 2);

        $isDraft = fake()->boolean(10);
        [$status, $paidAmount] = $isDraft
            ? [DocumentStatus::DRAFT, 0.0]
            : $this->paidScenario($totalAmount);

        $invoiceDate = fake()->dateTimeBetween('-60 days');

        $sale = Sale::query()->create([
            'document_number' => DocumentNumber::next('sale'),
            'party_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => $invoiceDate,
            'due_date' => $status === DocumentStatus::PAID ? null : (clone $invoiceDate)->modify('+14 days'),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => round($totalAmount - $paidAmount, 2),
            'status' => $status,
            'created_by' => $user->id,
        ]);

        $sale->items()->createMany(
            $items->map(fn (array $item): array => [
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'sku' => $item['product']->sku,
                'quantity' => $item['quantity'],
                'unit_price' => $item['product']->selling_price,
                'cost_price' => $item['product']->purchase_price,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => round($item['quantity'] * (float) $item['product']->selling_price, 2),
            ])->all()
        );

        if ($paidAmount > 0) {
            $this->seedDocumentPayment($sale, $cashbox, $user);
        }

        if ($status !== DocumentStatus::DRAFT) {
            $sale->items->each(fn ($item) => StockMovement::query()->create([
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouse->id,
                'movement_type' => MovementType::OUT,
                'quantity' => $item->quantity,
                'unit_cost' => $item->cost_price,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'movement_date' => $invoiceDate,
                'created_by' => $user->id,
            ]));
        }

        return $sale;
    }

    /**
     * Seed the payment matching a purchase or sale document: paid and
     * dated on the document's invoice date.
     */
    private function seedDocumentPayment(Purchase|Sale $document, Cashbox $cashbox, User $user): void
    {
        $isPurchase = $document instanceof Purchase;

        Payment::query()->create([
            'document_number' => DocumentNumber::next('payment'),
            'party_id' => $document->party_id,
            'cashbox_id' => $cashbox->id,
            'sale_id' => $isPurchase ? null : $document->id,
            'purchase_id' => $isPurchase ? $document->id : null,
            'type' => $isPurchase ? PaymentType::payment : PaymentType::recipt,
            'method' => fake()->randomElement([PaymentMethod::CASH, PaymentMethod::BANK_TRANSFER]),
            'amount' => $document->paid_amount,
            'payment_date' => $document->invoice_date,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Seed a partial or full return of a real purchase: one of its items, paid back.
     */
    private function seedPurchaseReturn(?Purchase $purchase, User $user): void
    {
        if (! $purchase instanceof Purchase || $purchase->items->isEmpty()) {
            return;
        }

        $warehouse = Warehouse::query()->firstOrFail();
        $item = $purchase->items->random();
        $quantity = fake()->numberBetween(1, max(1, (int) floor((float) $item->quantity / 2)));
        $returnAmount = round($quantity * (float) $item->unit_price, 2);

        $purchaseReturn = PurchaseReturn::query()->create([
            'document_number' => DocumentNumber::next('purchase_return'),
            'purchase_id' => $purchase->id,
            'party_id' => $purchase->party_id,
            'warehouse_id' => $purchase->warehouse_id,
            'return_date' => (clone $purchase->invoice_date)->modify('+1 week'),
            'total_amount' => $returnAmount,
            'status' => DocumentStatus::PAID,
            'created_by' => $user->id,
        ]);

        $purchaseReturn->items()->create([
            'product_id' => $item->product_id,
            'quantity' => $quantity,
            'unit_cost' => $item->unit_price,
            'line_total' => $returnAmount,
        ]);

        StockMovement::query()->create([
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => MovementType::IN,
            'quantity' => $quantity,
            'unit_cost' => $item->unit_price,
            'reference_type' => PurchaseReturn::class,
            'reference_id' => $purchaseReturn->id,
            'movement_date' => (clone $purchase->invoice_date)->modify('+1 week'),
            'created_by' => $user->id,
        ]);
    }

    /**
     * Seed a partial return of a real sale: one unit of one of its items, back in stock.
     */
    private function seedSalesReturn(?Sale $sale, User $user): void
    {
        if (! $sale instanceof Sale || $sale->items->isEmpty()) {
            return;
        }

        $warehouse = Warehouse::query()->firstOrFail();
        $item = $sale->items->random();
        $quantity = fake()->numberBetween(1, max(1, (int) floor((float) $item->quantity / 2)));
        $returnAmount = round($quantity * (float) $item->unit_price, 2);

        $salesReturn = SalesReturn::query()->create([
            'document_number' => DocumentNumber::next('sales_return'),
            'sale_id' => $sale->id,
            'party_id' => $sale->party_id,
            'warehouse_id' => $sale->warehouse_id,
            'return_date' => (clone $sale->invoice_date)->modify('+1 week'),
            'total_amount' => $returnAmount,
            'status' => DocumentStatus::APPROVED,
            'created_by' => $user->id,
        ]);

        $salesReturn->items()->create([
            'product_id' => $item->product_id,
            'quantity' => $quantity,
            'unit_price' => $item->unit_price,
            'cost_price' => $item->cost_price,
            'line_total' => $returnAmount,
        ]);

        StockMovement::query()->create([
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => MovementType::IN,
            'quantity' => $quantity,
            'unit_cost' => $item->cost_price,
            'reference_type' => SalesReturn::class,
            'reference_id' => $salesReturn->id,
            'movement_date' => (clone $sale->invoice_date)->modify('+1 week'),
            'created_by' => $user->id,
        ]);
    }

    /**
     * Seed an expense in the last two months with a matching cash payment.
     */
    private function seedExpense(Cashbox $cashbox, User $user): void
    {
        $expense = Expense::query()->create([
            'document_number' => DocumentNumber::next('expense'),
            'expense_category' => fake()->randomElement(ExpenseCategory::cases()),
            'cashbox_id' => $cashbox->id,
            'amount' => fake()->randomFloat(2, 20, 500),
            'expense_date' => fake()->dateTimeBetween('-60 days'),
            'description' => fake()->sentence(),
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'document_number' => DocumentNumber::next('payment'),
            'cashbox_id' => $cashbox->id,
            'expense_id' => $expense->id,
            'type' => PaymentType::payment,
            'method' => PaymentMethod::CASH,
            'amount' => $expense->amount,
            'payment_date' => $expense->expense_date,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Pick a payment scenario: 70% fully paid, 20% partially paid, 10% unpaid.
     *
     * @return array{0: DocumentStatus, 1: float}
     */
    private function paidScenario(float $totalAmount): array
    {
        $roll = fake()->numberBetween(1, 100);

        return match (true) {
            $roll <= 70 => [DocumentStatus::PAID, $totalAmount],
            $roll <= 90 => [DocumentStatus::PARTIAL, round($totalAmount * fake()->randomFloat(2, 0.3, 0.7), 2)],
            default => [DocumentStatus::PENDING, 0.0],
        };
    }
}
