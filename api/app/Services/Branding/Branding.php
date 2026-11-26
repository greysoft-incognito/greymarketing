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

    /**
     * Text settings for the sign-in panel, keyed by the page they appear on.
     */
    public const AUTH_MESSAGES = [
        'sign_in' => 'auth_signin_message',
        'sign_up' => 'auth_signup_heading',
        'invite' => 'auth_invite_message',
    ];

    public const MESSAGE_MAX = 300;

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

    /**
     * Custom panel text per page; null where the app's own wording should show.
     *
     * @return array<string, ?string>
     */
    public function authMessages(): array
    {
        return collect(static::AUTH_MESSAGES)
            ->map(fn (string $key) => trim((string) Provider::config($key, '')) ?: null)
            ->all();
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
