<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the create user component renders the modal trigger', function () {
    $actingUser = User::factory()->create();

    $component = Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create');

    expect($component->html())->toContain(__('users.add'));
});

test('a user can be created via the extracted create component', function () {
    $actingUser = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.create')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'super-secret-123')
        ->set('password_confirmation', 'super-secret-123')
        ->call('create')
        ->assertOk();

    $created = User::where('email', 'jane@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Jane Doe');
});
