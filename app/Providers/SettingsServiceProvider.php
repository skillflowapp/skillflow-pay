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
            'malipo.public_key' => 'malipo_public_key',
            'malipo.secret_key' => 'malipo_secret_key',
            'malipo.project' => 'malipo_project',
            'malipo.merchant_account_id' => 'malipo_merchant_account_id',
            'malipo.webhook_secret' => 'malipo_webhook_secret',
            'malipo.timeout' => 'malipo_timeout',
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
    }
}
