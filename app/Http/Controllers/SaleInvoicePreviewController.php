<?php

namespace App\Http\Controllers;

use App\Enums\PaymentType;
use App\Models\Sale;
use App\Support\Settings;
use Illuminate\Contracts\View\View;

class SaleInvoicePreviewController extends Controller
{
    /**
     * Render the saved sale as a printable invoice.
     */
    public function __invoke(Sale $sale): View
    {
        $sale->load([
            'items',
            'party',
            'creator',
            'payments' => fn ($query) => $query
                ->where('type', PaymentType::recipt)
                ->oldest('payment_date'),
        ]);

        $settings = Settings::all();

        return view('sales.invoice-preview', [
            'sale' => $sale,
            'store' => [
                'name' => filled($settings['company_name'] ?? null) ? (string) $settings['company_name'] : (string) config('app.name'),
                'address' => (string) ($settings['company_address'] ?? ''),
                'phone' => (string) ($settings['company_phone'] ?? ''),
            ],
            'invoiceFooter' => filled($settings['invoice_footer'] ?? null) ? (string) $settings['invoice_footer'] : (string) __('sales.thank_you'),
            'paymentMethod' => $sale->payments->first()?->method,
        ]);
    }
}
