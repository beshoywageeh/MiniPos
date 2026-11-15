<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('categories page route exists and requires auth', function () {
    expect(route('categories.index'))->toBe(url('/categories'));

    $this->get(route('categories.index'))->assertRedirect();
});

test('brands page route exists and requires auth', function () {
    expect(route('brands.index'))->toBe(url('/brands'));

    $this->get(route('brands.index'))->assertRedirect();
});
