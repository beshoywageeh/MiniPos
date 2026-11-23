<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a missing setting returns the caller default', function () {
    expect(Settings::get('missing_key'))->toBeNull()
        ->and(Settings::get('missing_key', 'fallback'))->toBe('fallback')
        ->and(Settings::get('missing_key', 0))->toBe(0);
});

test('settings round-trip with their declared types', function () {
    Settings::set('string_key', 'hello');
    Settings::set('integer_key', 42, 'integer');
    Settings::set('true_key', true, 'boolean');
    Settings::set('false_key', false, 'boolean');
    Settings::set('decimal_key', '12.50', 'decimal');
    Settings::set('json_key', ['a' => 1, 'b' => [2, 3]], 'json');
    Setting::factory()->create(['key' => 'unknown_type', 'value' => 'plain', 'type' => 'custom']);

    expect(Settings::get('string_key'))->toBe('hello')
        ->and(Settings::get('integer_key'))->toBe(42)
        ->and(Settings::get('true_key'))->toBeTrue()
        ->and(Settings::get('false_key'))->toBeFalse()
        ->and(Settings::get('decimal_key'))->toBe('12.50')
        ->and(Settings::get('json_key'))->toBe(['a' => 1, 'b' => [2, 3]])
        ->and(Settings::get('unknown_type'))->toBe('plain')
        ->and(Setting::where('key', 'true_key')->value('value'))->toBe('1')
        ->and(Setting::where('key', 'false_key')->value('value'))->toBe('0')
        ->and(Setting::where('key', 'integer_key')->value('type'))->toBe('integer');
});

test('all returns only the requested group with typed values', function () {
    Setting::factory()->create(['key' => 'company_name', 'value' => 'MiniPOS Store', 'type' => 'string', 'group' => 'general']);
    Setting::factory()->create(['key' => 'tax_rate', 'value' => '10.50', 'type' => 'decimal', 'group' => 'general']);
    Setting::factory()->create(['key' => 'unrelated', 'value' => 'x', 'type' => 'string', 'group' => 'other']);

    $all = Settings::all('general');

    expect($all)->toMatchArray([
        'company_name' => 'MiniPOS Store',
        'tax_rate' => '10.50',
    ])->and($all)->not->toHaveKey('unrelated')
        ->and($all)->toHaveCount(2);
});

test('settings route exists and requires auth', function () {
    expect(route('settings.index'))->toBe(url('/settings'));

    $this->get(route('settings.index'))->assertRedirect();
});

test('the sidebar links to the settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('settings.index'));
});

test('the settings page loads seeded values', function () {
    $this->seed(TestSeeder::class);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('livewire.settings.index')
        ->assertSet('companyName', 'MiniPOS Store')
        ->assertSet('companyPhone', '')
        ->assertSet('companyAddress', '')
        ->assertSet('currency', 'USD')
        ->assertSet('taxRate', '10')
        ->assertSet('invoiceFooter', '');
});

test('the settings page falls back to defaults without stored rows', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('livewire.settings.index')
        ->assertSet('companyName', 'MiniPOS Store')
        ->assertSet('currency', 'USD')
        ->assertSet('taxRate', '0')
        ->assertSet('companyPhone', '')
        ->assertSet('companyAddress', '')
        ->assertSet('invoiceFooter', '');
});

test('saving persists every settings field with its type', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('livewire.settings.index')
        ->set('companyName', 'Acme Store')
        ->set('companyPhone', '+1 555 0100')
        ->set('companyAddress', '1 Main Street')
        ->set('currency', 'EUR')
        ->set('taxRate', '7.5')
        ->set('invoiceFooter', 'Thank you for shopping')
        ->call('save')
        ->assertSet('saved', true)
        ->assertHasNoErrors();

    expect(Settings::get('company_name'))->toBe('Acme Store')
        ->and(Settings::get('company_phone'))->toBe('+1 555 0100')
        ->and(Settings::get('company_address'))->toBe('1 Main Street')
        ->and(Settings::get('currency'))->toBe('EUR')
        ->and(Settings::get('tax_rate'))->toBe('7.5')
        ->and(Settings::get('invoice_footer'))->toBe('Thank you for shopping')
        ->and(Setting::where('key', 'tax_rate')->value('type'))->toBe('decimal');
});

test('saving rejects an empty company name and an out-of-range tax rate', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('livewire.settings.index')
        ->set('companyName', '')
        ->set('taxRate', '150')
        ->call('save')
        ->assertHasErrors([
            'companyName' => 'required',
            'taxRate' => 'max',
        ])
        ->assertSet('saved', false);
});
