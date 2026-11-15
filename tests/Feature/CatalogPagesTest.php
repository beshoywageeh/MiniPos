<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('categories page route exists and requires auth', function () {
    expect(route('categories.index'))->toBe(url('/categories'));

    $this->get(route('categories.index'))->assertRedirect();
});

test('brands page route exists and requires auth', function () {
    expect(route('brands.index'))->toBe(url('/brands'));

    $this->get(route('brands.index'))->assertRedirect();
});

test('categories page lists categories with parent names', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->create(['name' => 'Beverages']);
    Category::factory()->create(['name' => 'Soda', 'parent_id' => $parent->id]);

    Livewire::actingAs($user)->test('livewire.catalog.categories.index')
        ->assertSee('Beverages')
        ->assertSee('Soda');
});

test('categories search filters by name', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Beverages']);
    Category::factory()->create(['name' => 'Snacks']);

    Livewire::actingAs($user)->test('livewire.catalog.categories.index')
        ->set('search', 'Snacks')
        ->assertSee('Snacks')
        ->assertDontSee('Beverages');
});

test('categories search with no match shows empty state', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Beverages']);

    Livewire::actingAs($user)->test('livewire.catalog.categories.index')
        ->set('search', 'zzzz')
        ->assertSee(__('categories.no_records'));
});

test('categories list refreshes when an event fires', function () {
    $user = User::factory()->create();
    $target = Category::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($user)
        ->test('livewire.catalog.categories.index');

    expect($component->html())->toContain('Before Update');

    $target->update(['name' => 'After Update']);

    $component->dispatch('category-updated');

    expect($component->html())->toContain('After Update');
});
