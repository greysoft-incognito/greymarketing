<?php

namespace App\Services\Branding;

use App\Helpers\Provider;
use Illuminate\Support\Facades\Cache;
use Toneflix\DbConfig\Models\Configuration;

/**
 * Public look-and-feel settings that admins upload through the configurations API.
 */
class Branding
{
    public const AUTH_IMAGE = 'auth_image';

    public function appName(): string
    {
        return (string) Provider::config('app_name', config('app.name'));
    }

    /**
     * The uploaded image for the sign-in panel, or null to use the app's own default.
     */
    public function authImage(): ?string
    {
        $file = $this->authImageConfig()?->files()->latest('id')->first();

        return $file?->files['file'] ?? null;
    }

    public function resetAuthImage(): void
    {
        $this->authImageConfig()?->files()->get()->each->delete();

        Cache::forget('laravel-dbconfig.configurations::build');
    }

    protected function authImageConfig(): ?Configuration
    {
        return Configuration::query()->where('key', static::AUTH_IMAGE)->first();
    }
}
