<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() && $this->databaseHasSettings()) {
            $this->loadSettings();
        }
    }

    private function databaseHasSettings(): bool
    {
        try {
            return \Schema::hasTable('app_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    private function loadSettings(): void
    {
        $keys = [
            'malipo.base_url' => 'malipo_base_url',
            'malipo.api_token' => 'malipo_api_token',
            'malipo.secret_key' => 'malipo_secret_key',
            'malipo.webhook_secret' => 'malipo_webhook_secret',
            'malipo.timeout' => 'malipo_timeout',
            'textify.api_key' => 'textify_api_key',
            'textify.sender_name' => 'textify_sender_name',
            'notifications.admin_phone' => 'admin_notification_phone',
            'firebase.project_id' => 'firebase_project_id',
        ];

        foreach ($keys as $configKey => $settingKey) {
            $value = AppSetting::get($settingKey);
            if ($value !== null && $value !== '') {
                $casted = match (true) {
                    $configKey === 'malipo.timeout' => (int) $value,
                    default => $value,
                };
                Config::set($configKey, $casted);
            }
        }

        // Fallback textify.sender_name to env if not in DB
        if (config('textify.sender_name') === null || config('textify.sender_name') === '') {
            Config::set('textify.sender_name', env('TEXTIFY_SENDER_NAME', 'UPDATE'));
        }
    }
}
