<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('document sequences route exists and requires auth', function () {
    expect(route('document-sequences.index'))->toBe(url('/document-sequences'));

    $this->get(route('document-sequences.index'))->assertRedirect();
});

test('the sidebar links to the document sequences page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('document-sequences.index'));
});
