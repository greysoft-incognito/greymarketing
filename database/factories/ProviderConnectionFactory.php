<?php

namespace Database\Factories;

use App\Models\ProviderConnection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderConnection>
 */
class ProviderConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'provider' => 'termii',
            'channel' => 'sms',
            'name' => 'Termii',
            'credentials' => ['api_key' => 'tm_live_secret_1234'],
            'settings' => ['sender_id' => 'Grey'],
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function provider(string $provider, string $channel, array $credentials, array $settings = []): static
    {
        return $this->state(compact('provider', 'channel', 'credentials', 'settings') + ['name' => $provider]);
    }
}
