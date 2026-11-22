<?php

use App\Enums\MovementType;
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
    Route::livewire('/products', 'livewire.catalog.products.index')->name('products.index');
    Route::livewire('/units', 'livewire.unit.index')->name('units.index');
    Route::livewire('/parties', 'livewire.party.index')->name('parties.index');

    Route::view('/sales/create', 'sales.create')->name('sales.create');
    Route::get('/sales/invoice-preview', fn() => view('sales.invoice-preview'))->name('sales.invoice_preview');

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
            ->withSum(['stockMovements as stock_in' => fn($query) => $query->where('movement_type', MovementType::IN)], 'quantity')
            ->withSum(['stockMovements as stock_out' => fn($query) => $query->where('movement_type', MovementType::OUT)], 'quantity')
            ->get()
            ->filter(fn(Product $product) => ($product->stock_in - $product->stock_out) <= $product->min_stock)
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
require __DIR__ . '/Auth.php';
