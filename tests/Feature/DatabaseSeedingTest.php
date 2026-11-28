<?php

use App\Enums\DocumentStatus;
use App\Enums\PartyType;
use App\Enums\PaymentType;
use App\Models\Brand;
use App\Models\Cashbox;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TestSeeder', function () {
    it('seeds the baseline master data', function () {
        $this->seed(TestSeeder::class);

        expect(User::count())->toBe(1)
            ->and(Warehouse::count())->toBe(1)
            ->and(Cashbox::count())->toBe(1)
            ->and(Category::count())->toBe(1)
            ->and(Unit::count())->toBe(1)
            ->and(Brand::count())->toBe(2)
            ->and(Party::count())->toBe(2)
            ->and(Product::count())->toBe(4)
            ->and(Setting::count())->toBe(6)
            ->and(DocumentSequence::where('document_type', 'sale')->where('prefix', 'SALE')->exists())->toBeTrue();
    });

    it('is idempotent when run twice', function () {
        $this->seed(TestSeeder::class);
        $this->seed(TestSeeder::class);

        expect(Product::count())->toBe(4)
            ->and(DocumentSequence::where('document_type', 'sale')->first()->next_number)->toBe(1);
    });
});

describe('DemoDataSeeder', function () {
    beforeEach(function () {
        $this->seed(TestSeeder::class);
        $this->seed(DemoDataSeeder::class);
    });

    it('scales the catalog and the trading partners', function () {
        expect(Product::count())->toBe(24)
            ->and(Party::count())->toBe(8)
            ->and(Party::whereIn('type', [PartyType::CUSTOMER, PartyType::BOTH])->count())->toBeGreaterThan(0)
            ->and(Party::whereIn('type', [PartyType::SUPPLIER, PartyType::BOTH])->count())->toBeGreaterThan(0);
    });

    it('keeps every purchase consistent with its items and payments', function () {
        Purchase::query()->with('items', 'payments')->get()->each(function (Purchase $purchase) {
            $paid = round($purchase->payments->sum('amount'), 2);

            expect((float) round($purchase->items->sum('line_total'), 2))->toEqual($purchase->subtotal)
                ->and($paid)->toEqual(round((float) $purchase->paid_amount, 2))
                ->and(round((float) ($purchase->total_amount - $purchase->paid_amount), 2))->toEqual($purchase->remaining_amount);
        });

        expect(Purchase::count())->toBe(8);
    });

    it('keeps every sale consistent with its items and payments', function () {
        Sale::query()->with('items', 'payments')->get()->each(function (Sale $sale) {
            $paid = round($sale->payments->sum('amount'), 2);

            expect((float) round($sale->items->sum('line_total'), 2))->toEqual($sale->subtotal)
                ->and($paid)->toEqual(round((float) $sale->paid_amount, 2))
                ->and(round((float) ($sale->total_amount - $sale->paid_amount), 2))->toEqual($sale->remaining_amount);

            if ($sale->status === DocumentStatus::DRAFT) {
                expect((float) $sale->paid_amount)->toEqual(0.0);
            }
        });

        expect(Sale::count())->toBe(25);
    });

    it('records stock movements for purchased and sold items', function () {
        $purchased = Purchase::query()->with('items')->get()->sum(fn (Purchase $purchase) => $purchase->items->count());
        $sold = Sale::query()->with('items')->get()
            ->filter(fn (Sale $sale) => $sale->status !== DocumentStatus::DRAFT)
            ->sum(fn (Sale $sale) => $sale->items->count());

        expect(StockMovement::where('reference_type', Purchase::class)->count())->toBe($purchased)
            ->and(StockMovement::where('reference_type', Sale::class)->count())->toBe($sold);
    });

    it('spreads purchases and sales across the last three months', function () {
        Purchase::query()->pluck('invoice_date')->each(function ($date) {
            expect($date->toDateTime()->getTimestamp())->toBeGreaterThan(now()->subDays(90)->getTimestamp())
                ->and($date->timestamp)->toBeLessThanOrEqual(now()->getTimestamp());
        });

        Sale::query()->pluck('invoice_date')->each(function ($date) {
            expect($date->timestamp)->toBeGreaterThan(now()->subDays(60)->getTimestamp())
                ->and($date->timestamp)->toBeLessThanOrEqual(now()->getTimestamp());
        });
    });

    it('creates returns linked to real documents with consistent totals', function () {
        SalesReturn::query()->with('items', 'sale')->get()->each(function (SalesReturn $salesReturn) {
            expect($salesReturn->sale)->not->toBeNull()
                ->and((float) $salesReturn->items->sum('line_total'))->toEqual($salesReturn->total_amount)
                ->and($salesReturn->status)->toBe(DocumentStatus::APPROVED);
        });

        PurchaseReturn::query()->with('items', 'purchase')->get()->each(function (PurchaseReturn $purchaseReturn) {
            expect($purchaseReturn->purchase)->not->toBeNull()
                ->and((float) $purchaseReturn->items->sum('line_total'))->toEqual($purchaseReturn->total_amount)
                ->and((float) $purchaseReturn->items->first()->quantity)->toBeLessThanOrEqual(20.0);
        });

        expect(SalesReturn::count())->toBe(3)
            ->and(PurchaseReturn::count())->toBe(2);
    });

    it('covers every expense with a matching payment', function () {
        expect(Expense::count())->toBe(6);

        Expense::query()->get()->each(function ($expense) {
            $payment = Payment::query()->where('expense_id', $expense->id)->firstOrFail();

            expect((float) $payment->amount)->toEqual($expense->amount)
                ->and($payment->type)->toBe(PaymentType::payment);
        });
    });

    it('advances every document sequence past the seeded documents', function () {
        $expected = [
            'sale' => Sale::count(),
            'purchase' => Purchase::count(),
            'payment' => Payment::count(),
            'expense' => Expense::count(),
            'sales_return' => SalesReturn::count(),
            'purchase_return' => PurchaseReturn::count(),
        ];

        foreach ($expected as $documentType => $count) {
            $sequence = DocumentSequence::query()->where('document_type', $documentType)->firstOrFail();

            expect($sequence->next_number)->toBe($count + 1);
        }
    });

    it('attaches all stocked brands to every supplier', function () {
        $brandCount = Brand::count();

        Party::query()
            ->whereIn('type', [PartyType::SUPPLIER, PartyType::BOTH])
            ->withCount('brands')
            ->get()
            ->each(fn (Party $supplier) => expect($supplier->brands_count)->toBe($brandCount));
    });
});

it('gives seeded stock movements a balance snapshot', function () {
    $this->seed(TestSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(StockMovement::query()->whereNull('balance_after')->doesntExist())->toBeTrue();
});
