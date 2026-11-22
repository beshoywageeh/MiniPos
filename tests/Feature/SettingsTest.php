<?php

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
