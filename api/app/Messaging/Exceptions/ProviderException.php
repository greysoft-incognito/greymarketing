<?php

namespace App\Messaging\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class ProviderException extends RuntimeException
{
    /**
     * Build an exception from a failed provider HTTP response.
     */
    public static function fromResponse(string $provider, Response $response, ?string $message = null): self
    {
        $message ??= $response->json('message')
            ?? $response->json('error_description')
            ?? $response->json('error')
            ?? $response->reason();

        return new self("{$provider}: " . (is_string($message) ? $message : json_encode($message)), $response->status());
    }
}
