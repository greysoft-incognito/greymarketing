<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\Driver;
use App\Messaging\Exceptions\ProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractDriver implements Driver
{
    /**
     * Display name used in error messages.
     */
    protected string $label = 'Provider';

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        protected array $credentials,
        protected array $settings = [],
    ) {}

    protected function credential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(20);
    }

    /**
     * Return the response, or throw when the provider reported a failure.
     */
    protected function ensureOk(Response $response, ?string $message = null): Response
    {
        if ($response->failed()) {
            throw ProviderException::fromResponse($this->label, $response, $message);
        }

        return $response;
    }

    protected function fail(string $message): never
    {
        throw new ProviderException("{$this->label}: {$message}");
    }
}
