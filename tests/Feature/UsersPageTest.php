<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/users')->assertRedirect(route('login'));
});

test('users page lists users for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/users');

    $response->assertOk();
    $response->assertSee($user->name);
    $response->assertSee($user->email);
});

test('dashboard no longer embeds the users table', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertDontSee('livewire.users.index', false);
});
