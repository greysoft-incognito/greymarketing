<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sign-in page settings: the panel image and the text shown on it.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    protected array $settings = [
        ['auth_image', 'Sign-in Image', 'file', 'Shown beside the sign-in, sign-up and invite forms. Leave empty to use the default.'],
        ['auth_signin_message', 'Sign-in Message', 'text', 'Shown beside the sign-in form. Leave empty to use the default.'],
        ['auth_signup_heading', 'Sign-up Heading', 'text', 'Shown above the list of perks beside the sign-up form. Leave empty to use the default.'],
        ['auth_invite_message', 'Invite Message', 'text', 'Shown beside the accept-invite page. Leave empty to use the default.'],
    ];

    public function up(): void
    {
        foreach ($this->settings as [$key, $title, $type, $hint]) {
            DB::table('configurations')->insertOrIgnore([
                'key' => $key,
                'title' => $title,
                'value' => null,
                'type' => $type,
                'col' => 12,
                'autogrow' => false,
                'hint' => $hint,
                'secret' => false,
                'group' => 'appearance',
                'choices' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('laravel-dbconfig.configurations::build');
    }

    public function down(): void
    {
        DB::table('configurations')->whereIn('key', array_column($this->settings, 0))->delete();

        Cache::forget('laravel-dbconfig.configurations::build');
    }
};
