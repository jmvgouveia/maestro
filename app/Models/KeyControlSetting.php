<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeyControlSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function value(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
