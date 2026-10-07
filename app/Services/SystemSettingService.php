<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    /**
     * Operational setting groups accessible by 'admin' role
     */
    const ALLOWED_ADMIN_GROUPS = ['circulation', 'general'];

    /**
     * Get all settings grouped by tab group, filtered by role
     */
    public function getSettingsGrouped(?string $role = 'super_admin'): array
    {
        if ($role === 'operator') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Akses ditolak. Role Operator tidak memiliki izin untuk mengelola Pengaturan Umum.');
        }

        $query = Setting::query();

        if ($role === 'admin') {
            $query->whereIn('group', self::ALLOWED_ADMIN_GROUPS);
        }

        $settings = $query->orderBy('group')->get();

        return $settings->groupBy('group')->map(function ($groupSettings) {
            return $groupSettings->values();
        })->toArray();
    }

    /**
     * Update settings values by key and clear cache
     */
    public function updateSettings(array $settingsData, ?string $role = 'super_admin'): array
    {
        if ($role === 'operator') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Akses ditolak. Role Operator tidak memiliki izin untuk mengelola Pengaturan Umum.');
        }

        // Convert key-value array format if passed directly
        $items = isset($settingsData['settings']) && is_array($settingsData['settings'])
            ? $settingsData['settings']
            : $settingsData;

        $updated = [];

        foreach ($items as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            // Check admin role restrictions
            if ($role === 'admin' && ! in_array($setting->group, self::ALLOWED_ADMIN_GROUPS)) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Akses ditolak. Role Admin tidak memiliki izin untuk mengedit pengaturan pada kelompok '{$setting->group}'.");
            }

            $setting->update([
                'value' => (string) $value,
            ]);

            $updated[$key] = $setting->value;
        }

        // Bersihkan Cache system_settings agar perubahan langsung berlaku di modul sirkulasi & modul lainnya
        Cache::forget('system_settings');

        return $this->getSettingsGrouped($role);
    }
}
