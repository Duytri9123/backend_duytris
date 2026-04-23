<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    // Lấy value theo key, có default
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    // Set value theo key
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting_{$key}");
        Cache::forget('site_settings_all');
    }

    // Lấy tất cả settings dạng key => value
    public static function all_settings(): array
    {
        return Cache::remember('site_settings_all', 3600, function () {
            return static::all()->pluck('value', 'key')->toArray();
        });
    }

    // Xóa cache khi update
    protected static function booted(): void
    {
        static::saved(function ($setting) {
            Cache::forget("setting_{$setting->key}");
            Cache::forget('site_settings_all');
        });
    }
}
