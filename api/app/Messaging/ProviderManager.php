<?php

namespace App\Messaging;

use App\Enums\Channel;
use App\Messaging\Contracts\Driver;
use App\Models\ProviderConnection;
use InvalidArgumentException;

class ProviderManager
{
    /**
     * @param  array<string, array<string, mixed>>  $providers
     */
    public function __construct(protected array $providers) {}

    /**
     * Public catalog of providers, without driver classes or validation rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        $fields = fn (array $defs) => collect($defs)->map(fn ($def, $key) => [
            'key' => $key,
            'label' => $def['label'],
            'required' => in_array('required', $def['rules'], true),
            'secret' => $def['secret'] ?? false,
        ])->values()->all();

        return collect($this->providers)->map(fn ($def, $key) => [
            'key' => $key,
            'name' => $def['name'],
            'channels' => $def['channels'],
            'credentials' => $fields($def['credentials']),
            'settings' => $fields($def['settings'] ?? []),
        ])->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }

    public function supports(string $provider, Channel $channel): bool
    {
        return in_array($channel->value, $this->definition($provider)['channels'], true);
    }

    /**
     * Validation rules for a provider's credentials and settings, prefixed for request input.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $provider, bool $partial = false): array
    {
        $definition = $this->definition($provider);

        $credentials = collect($definition['credentials'])->mapWithKeys(fn ($def, $key) => [
            "credentials.{$key}" => $partial
                ? array_map(fn ($rule) => $rule === 'required' ? 'sometimes' : $rule, $def['rules'])
                : $def['rules'],
        ]);

        $settings = collect($definition['settings'] ?? [])->mapWithKeys(fn ($def, $key) => [
            "settings.{$key}" => $def['rules'],
        ]);

        return $credentials->merge($settings)->all();
    }

    /**
     * @return array<int, string>
     */
    public function secretFields(string $provider): array
    {
        return collect($this->definition($provider)['credentials'])
            ->filter(fn ($def) => $def['secret'] ?? false)
            ->keys()
            ->all();
    }

    public function driver(ProviderConnection $connection): Driver
    {
        $class = $this->definition($connection->provider)['driver'];

        return new $class($connection->credentials ?? [], $connection->settings ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function definition(string $provider): array
    {
        return $this->providers[$provider]
            ?? throw new InvalidArgumentException("Unknown messaging provider [{$provider}].");
    }
}
