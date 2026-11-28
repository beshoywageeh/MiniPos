<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/reports')->assertRedirect(route('login'));
});

test('reports page renders all three report tabs and datatables', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/reports');

    $response->assertOk();
    $response->assertSee(__('reports.tab_sales'));
    $response->assertSee(__('reports.tab_inventory'));
    $response->assertSee(__('reports.tab_expenses'));
    $response->assertSee('grid-sales');
    $response->assertSee('grid-inventory');
    $response->assertSee('grid-expenses');
});

test('sidebar reports link points to the reports page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(route('reports.index'));
});
