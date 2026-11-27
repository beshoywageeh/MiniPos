<?php

use App\Enums\MovementType;
use App\Http\Controllers\ProductLookupController;
use App\Http\Controllers\PurchaseInvoicePreviewController;
use App\Http\Controllers\SaleInvoicePreviewController;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth']], function () {

    Route::livewire('/users', 'livewire.users.index')->name('users.index');
    Route::livewire('/categories', 'livewire.catalog.categories.index')->name('categories.index');
    Route::livewire('/brands', 'livewire.catalog.brands.index')->name('brands.index');
    Route::get('/products/lookup', ProductLookupController::class)
        ->name('products.lookup');
    Route::livewire('/products', 'livewire.catalog.products.index')->name('products.index');
    Route::livewire('/products/{product}', 'livewire.catalog.products.show')
        ->whereNumber('product')
        ->name('products.show');
    Route::livewire('/units', 'livewire.unit.index')->name('units.index');
    Route::livewire('/warehouses', 'livewire.warehouse.index')->name('warehouses.index');
    Route::livewire('/parties', 'livewire.party.index')->name('parties.index');
    Route::livewire('/parties/{party}', 'livewire.party.show')
        ->whereNumber('party')
        ->name('parties.show');
    Route::livewire('/settings', 'livewire.settings.index')->name('settings.index');
    Route::livewire('/document-sequences', 'livewire.document-sequences.index')->name('document-sequences.index');

    Route::livewire('/sales', 'livewire.sales.index')->name('sales.index');
    Route::livewire('/sales/create', 'livewire.sales.create')->name('sales.create');
    Route::get('/sales/{sale}/invoice-preview', SaleInvoicePreviewController::class)
        ->whereNumber('sale')
        ->name('sales.invoice_preview');

    Route::livewire('/purchases', 'livewire.purchase.index')->name('purchases.index');
    Route::livewire('/purchases/create', 'livewire.purchase.create')->name('purchases.create');
    Route::get('/purchases/{purchase}/invoice-preview', PurchaseInvoicePreviewController::class)
        ->whereNumber('purchase')
        ->name('purchases.invoice_preview');

    Route::view('/reports', 'reports.index')->name('reports.index');

    Route::get('/dashboard', function () {
        $today = today();

        $todaysSales = Sale::whereDate('invoice_date', $today)->sum('total_amount');
        $todaysPurchases = Purchase::whereDate('invoice_date', $today)->sum('total_amount');
        $todaysExpenses = Expense::whereDate('expense_date', $today)->sum('amount');
        $cashBalance = Cashbox::query()->sum('opening_balance');

        $recentSales = Sale::query()
            ->latest('invoice_date')
            ->latest('id')
            ->limit(5)
            ->get();

        $lowStockProducts = Product::query()
            ->where('is_active', true)
            ->withSum(['stockMovements as stock_in' => fn ($query) => $query->where('movement_type', MovementType::IN)], 'quantity')
            ->withSum(['stockMovements as stock_out' => fn ($query) => $query->where('movement_type', MovementType::OUT)], 'quantity')
            ->get()
            ->filter(fn (Product $product) => ($product->stock_in - $product->stock_out) <= $product->min_stock)
            ->take(5);

        return view('dashboard', [
            'todaysSales' => $todaysSales,
            'todaysPurchases' => $todaysPurchases,
            'todaysExpenses' => $todaysExpenses,
            'cashBalance' => $cashBalance,
            'recentSales' => $recentSales,
            'lowStockProducts' => $lowStockProducts,
        ]);
    })->name('dashboard');
});
require __DIR__.'/Auth.php';
