<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Support\Settings;
use Illuminate\Contracts\View\View;

class StockAdjustmentPreviewController extends Controller
{
    /**
     * Render the saved stock adjustment as a printable document.
     */
    public function __invoke(StockAdjustment $stockAdjustment): View
    {
        $stockAdjustment->load(['product', 'warehouse', 'creator', 'movement']);

        $settings = Settings::all();

        return view('stock-adjustments.preview', [
            'adjustment' => $stockAdjustment,
            'store' => [
                'name' => filled($settings['company_name'] ?? null) ? (string) $settings['company_name'] : (string) config('app.name'),
                'address' => (string) ($settings['company_address'] ?? ''),
                'phone' => (string) ($settings['company_phone'] ?? ''),
            ],
            'footer' => filled($settings['invoice_footer'] ?? null) ? (string) $settings['invoice_footer'] : (string) __('stock_adjustments.footer'),
        ]);
    }
}
