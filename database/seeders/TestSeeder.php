<?php

namespace Database\Seeders;

use App\Enums\PartyType;
use App\Enums\ResetPeriod;
use App\Models\Brand;
use App\Models\Cashbox;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\Party;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Seeds the baseline master data used by tests and the demo dataset.
 *
 * Running it again is safe: existing rows are looked up by a stable key
 * instead of being created twice.
 */
class TestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Staff
        User::firstOrCreate(
            ['email' => 'admin@minipos.test'],
            ['name' => 'Admin User', 'password' => bcrypt('password')],
        );

        // Locations and cash handling
        Warehouse::firstOrCreate(['code' => 'WH-001'], [
            'name' => 'Main Warehouse',
        ]);

        Cashbox::firstOrCreate(['code' => 'CASH-001'], [
            'name' => 'Main Cashbox',
            'opening_balance' => 0,
        ]);

        // Catalog foundations
        $categoryGeneral = Category::firstOrCreate(['code' => 'CAT-0001'], [
            'name' => 'General',
        ]);

        $unitPiece = Unit::firstOrCreate(['code' => 'UNT-0001'], [
            'name' => 'Piece',
            'abbreviation' => 'pcs',
        ]);

        $brandAcme = Brand::firstOrCreate(['code' => 'BRD-0001'], [
            'name' => 'Acme',
        ]);

        $brandGlobex = Brand::firstOrCreate(['code' => 'BRD-0002'], [
            'name' => 'Globex',
        ]);

        // Trading partners
        Party::firstOrCreate(['code' => 'PTY-0001'], [
            'name' => 'ACME Supplies',
            'phone' => '555-0101',
            'type' => PartyType::SUPPLIER,
        ]);

        Party::firstOrCreate(['code' => 'PTY-0002'], [
            'name' => 'Jane Customer',
            'phone' => '555-0102',
            'type' => PartyType::CUSTOMER,
        ]);

        // Retail products (sold with a healthy margin, bought at cost price)
        $productNames = ['Water Bottle', 'Desk Lamp', 'Phone Case', 'Backpack'];

        foreach ($productNames as $index => $productName) {
            $costPrice = 5.00 + $index * 10.00;

            Product::updateOrCreate(['name' => $productName], [
                'code' => sprintf('PRD-%05d', $index + 1),
                'sku' => sprintf('SKU-%05d', $index + 1),
                'barcode' => sprintf('20000000%06d', $index + 1),
                'category_id' => $categoryGeneral->id,
                'brand_id' => $brandAcme->id,
                'unit_id' => $unitPiece->id,
                'purchase_price' => $costPrice,
                'selling_price' => round($costPrice * 1.5, 2),
                'min_stock' => 5,
            ]);
        }

        // Numbering
        $this->seedDocumentSequence('sale', 'SALE');
        $this->seedDocumentSequence('purchase', 'PUR');
        $this->seedDocumentSequence('payment', 'PAY');
        $this->seedDocumentSequence('expense', 'EXP');
        $this->seedDocumentSequence('sales_return', 'SRT');
        $this->seedDocumentSequence('purchase_return', 'PRT');

        // Application settings
        collect([
            ['key' => 'company_name', 'value' => 'MiniPOS Store', 'type' => 'string'],
            ['key' => 'company_phone', 'value' => '', 'type' => 'string'],
            ['key' => 'company_address', 'value' => '', 'type' => 'string'],
            ['key' => 'currency', 'value' => 'USD', 'type' => 'string'],
            ['key' => 'tax_rate', 'value' => '10', 'type' => 'decimal'],
            ['key' => 'invoice_footer', 'value' => '', 'type' => 'string'],
        ])->each(fn (array $setting): Setting => Setting::firstOrCreate(
            ['key' => $setting['key']],
            ['value' => $setting['value'], 'type' => $setting['type'], 'group' => 'general'],
        ));
    }

    /**
     * Create a document sequence row from the given next number.
     */
    private function seedDocumentSequence(string $documentType, string $prefix): void
    {
        DocumentSequence::firstOrCreate([
            'document_type' => $documentType,
        ], [
            'prefix' => $prefix,
            'reset_period' => ResetPeriod::MONTHLY,
        ]);
    }
}
