<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the table refreshes to show updated data when the event fires', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($actingUser)
        ->test('livewire.users.index');

    expect($component->html())->toContain('Before Update');

    $target->update(['name' => 'After Update']);

    $component->dispatch('user-updated');

    expect($component->html())->toContain('After Update')
        ->and($component->html())->not->toContain('Before Update');
});

test('an existing user can be updated from the users page', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target])
        ->assertSet('name', 'Old Name')
        ->assertSet('email', 'old@example.com')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('update');

    $target->refresh();
    expect($target->name)->toBe('New Name')
        ->and($target->email)->toBe('new@example.com');
});

test('keeping the email unchanged does not trigger the unique validation', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create(['email' => 'mine@example.com']);

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target])
        ->set('name', 'Still Fine')
        ->call('update')
        ->assertHasNoErrors();

    expect($target->refresh()->name)->toBe('Still Fine');
});

test('leaving the password blank keeps the current password', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create(['password' => 'original-secret-1']);

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target])
        ->set('name', 'Kept Password')
        ->call('update');

    expect(Hash::check('original-secret-1', $target->refresh()->password))->toBeTrue();
});

test('entering a new password changes it', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create(['password' => 'original-secret-1']);

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target])
        ->set('name', 'New Password')
        ->set('password', 'brand-new-secret-1')
        ->set('password_confirmation', 'brand-new-secret-1')
        ->call('update');

    expect(Hash::check('brand-new-secret-1', $target->refresh()->password))->toBeTrue()
        ->and(Hash::check('original-secret-1', $target->password))->toBeFalse();
});

test('validation rejects missing or invalid fields', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target])
        ->set('name', 'a')
        ->set('email', 'not-an-email')
        ->set('password', 'short')
        ->call('update')
        ->assertHasErrors([
            'name' => 'min',
            'email' => 'email',
            'password' => 'confirmed',
        ])
        ->assertNotDispatched('user-updated');
});

test('a successful update resets the password fields and dispatches the event', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create();

    Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target])
        ->set('name', 'Reset After Save')
        ->set('password', 'temporary-secret-1')
        ->set('password_confirmation', 'temporary-secret-1')
        ->call('update')
        ->assertDispatched('user-updated')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    expect(Hash::check('temporary-secret-1', $target->refresh()->password))->toBeTrue();
});

test('the edit button renders for the row user', function () {
    $actingUser = User::factory()->create();
    $target = User::factory()->create();

    $component = Livewire::actingAs($actingUser)
        ->test('livewire.users.actions.update', ['user' => $target]);

    expect($component->html())->toContain(__('users.edit'));
});
