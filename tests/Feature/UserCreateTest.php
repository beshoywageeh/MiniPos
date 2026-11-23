<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a user can be created from the users page', function () {
    $actingUser = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'super-secret-123')
        ->set('password_confirmation', 'super-secret-123')
        ->call('create');

    $created = User::where('email', 'jane@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Jane Doe')
        ->and(Hash::check('super-secret-123', $created->password))->toBeTrue();
});

test('validation rejects missing or invalid fields', function () {
    $actingUser = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create')
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('password', 'short')
        ->call('create')
        ->assertHasErrors([
            'name' => 'required',
            'email' => 'email',
            'password' => 'confirmed',
        ])
        ->assertNotDispatched('user-created');
});

test('validation rejects a duplicate email address', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $actingUser = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create')
        ->set('name', 'Jane Doe')
        ->set('email', 'taken@example.com')
        ->set('password', 'super-secret-123')
        ->set('password_confirmation', 'super-secret-123')
        ->call('create')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::where('name', 'Jane Doe')->exists())->toBeFalse();
});

test('a successful creation resets the form fields', function () {
    $actingUser = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'super-secret-123')
        ->set('password_confirmation', 'super-secret-123')
        ->call('create')
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

test('newly created users appear in the rendered list', function () {
    $actingUser = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'super-secret-123')
        ->set('password_confirmation', 'super-secret-123')
        ->call('create')
        ->assertOk();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
