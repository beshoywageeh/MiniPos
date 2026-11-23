<?php

namespace App\Support;

use App\Models\Setting;

class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return self::castRead($setting->value, $setting->type);
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?string $group = 'general'): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => self::castWrite($value, $type),
                'type' => $type,
                'group' => $group,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(?string $group = null): array
    {
        return Setting::query()
            ->when($group !== null, fn ($query) => $query->where('group', $group))
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [
                $setting->key => self::castRead($setting->value, $setting->type),
            ])
            ->all();
    }

    private static function castRead(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $value, true),
            'decimal' => (string) $value,
            default => (string) $value,
        };
    }

    private static function castWrite(mixed $value, string $type): ?string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            'integer' => (string) (int) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
