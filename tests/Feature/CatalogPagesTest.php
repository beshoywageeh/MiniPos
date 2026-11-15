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

test('a category can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.create')
        ->set('code', 'CAT-TEST')
        ->set('name', 'Beverages')
        ->call('create')
        ->assertDispatched('category-created');

    expect(Category::where('code', 'CAT-TEST')->where('name', 'Beverages')->exists())->toBeTrue();
});

test('category creation validates duplicate code and required name', function () {
    Category::factory()->create(['code' => 'CAT-TAKEN']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.create')
        ->set('code', 'CAT-TAKEN')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['code' => 'unique', 'name' => 'required'])
        ->assertNotDispatched('category-created');
});

test('a category can be created with a parent', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->create(['name' => 'Root']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.create')
        ->set('code', 'CAT-CHILD')
        ->set('name', 'Soda')
        ->set('parentId', $parent->id)
        ->call('create')
        ->assertDispatched('category-created');

    expect(Category::where('code', 'CAT-CHILD')->first()->parent_id)->toBe($parent->id);
});

test('a category can be updated including its parent', function () {
    $user = User::factory()->create();
    $target = Category::factory()->create(['name' => 'Old']);
    $parent = Category::factory()->create(['name' => 'Root']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.update', ['category' => $target])
        ->assertSet('name', 'Old')
        ->set('name', 'New')
        ->set('parentId', $parent->id)
        ->call('update')
        ->assertDispatched('category-updated');

    expect($target->refresh()->name)->toBe('New')
        ->and($target->parent_id)->toBe($parent->id);
});

test('keeping own code passes unique validation on category update', function () {
    $user = User::factory()->create();
    $target = Category::factory()->create(['code' => 'CAT-KEEP']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.update', ['category' => $target])
        ->set('name', 'Renamed')
        ->call('update')
        ->assertHasNoErrors();

    expect($target->refresh()->code)->toBe('CAT-KEEP');
});

test('a category cannot select itself as parent', function () {
    $user = User::factory()->create();
    $target = Category::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.update', ['category' => $target]);

    expect($component->instance()->parentOptions->keys())->not->toContain($target->id);
});
