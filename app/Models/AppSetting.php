<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = self::where('key', $key)->first();

        return $setting ? (string) $setting->value : $default;
    }

    public static function set(string $key, ?string $value, string $group = 'general', string $type = 'string'): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type]
        );
    }

    public static function setIfNotBlank(string $key, ?string $value, string $group = 'general', string $type = 'string'): void
    {
        if ($value !== null && trim($value) !== '') {
            self::set($key, $value, $group, $type);
        }
    }
}
