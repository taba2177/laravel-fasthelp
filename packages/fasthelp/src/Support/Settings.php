<?php

namespace Tabadev\FastHelp\Support;

use Tabadev\FastHelp\Models\Setting;

/**
 * Runtime-editable settings store, backed by the `fasthelp_settings` table.
 *
 * Storage strategy: `Setting::$value` is cast to `json`, so `set()` simply
 * assigns the raw PHP value (scalar, bool, or array) and Eloquent's `json`
 * cast handles json_encode/json_decode on save/read. This round-trips
 * arrays, booleans (including `false`), and strings/numbers faithfully —
 * unlike the `array` cast, which cannot store a bare scalar/bool value.
 *
 * `get()` always falls back to the matching `config('fasthelp.<key>')`
 * value when there is no DB override, so introducing this store does not
 * change any existing behavior/tests until an admin explicitly saves a
 * setting via the Filament Settings page.
 */
class Settings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = Setting::query()->where('key', $key)->first();

        if ($row !== null) {
            return $row->value;
        }

        return $default !== null ? $default : config("fasthelp.{$key}");
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Setting::query()
            ->get()
            ->pluck('value', 'key')
            ->all();
    }
}
