<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'group', 'type', 'description'])]
class SystemSetting extends Model
{
    use HasFactory;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_setting:{$key}", function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type]
        );

        Cache::forget("system_setting:{$key}");
    }
}
