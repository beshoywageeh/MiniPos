<?php

namespace Database\Seeders;

use App\Models\StockAdjustment;
use Illuminate\Database\Seeder;

class StockAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StockAdjustment::factory()->count(2)->create();
    }
}
