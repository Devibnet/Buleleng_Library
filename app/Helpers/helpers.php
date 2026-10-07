<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('get_setting')) {
    /**
     * Ambil nilai pengaturan sistem berdasarkan key dengan Caching.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_setting(string $key, $default = null)
    {
        $settings = Cache::remember('system_settings', 86400, function () {
            try {
                return Setting::all()->pluck('value', 'key')->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        });

        return $settings[$key] ?? $default;
    }
}
