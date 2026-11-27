<?php

namespace App\Http\Controllers;

use App\Enums\PaymentType;
use App\Models\Purchase;
use App\Support\Settings;
use Illuminate\Contracts\View\View;

class PurchaseInvoicePreviewController extends Controller
{
    /**
     * Render the saved purchase as a printable invoice.
     */
    public function __invoke(Purchase $purchase): View
    {
        $purchase->load([
            'items',
            'party',
            'creator',
            'payments' => fn ($query) => $query
                ->where('type', PaymentType::payment)
                ->oldest('payment_date'),
        ]);

        $settings = Settings::all();

        return view('purchases.invoice-preview', [
            'purchase' => $purchase,
            'store' => [
                'name' => filled($settings['company_name'] ?? null) ? (string) $settings['company_name'] : (string) config('app.name'),
                'address' => (string) ($settings['company_address'] ?? ''),
                'phone' => (string) ($settings['company_phone'] ?? ''),
            ],
            'invoiceFooter' => filled($settings['invoice_footer'] ?? null) ? (string) $settings['invoice_footer'] : (string) __('purchases.thank_you'),
            'paymentMethod' => $purchase->payments->first()?->method,
        ]);
    }
}
