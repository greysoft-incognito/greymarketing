<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ConnectionStatus;
use App\Messaging\Contracts\Driver;
use App\Messaging\ProviderManager;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderConnection extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'provider',
        'channel',
        'name',
        'credentials',
        'settings',
        'is_default',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $attributes = [
        'status' => 'untested',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_default' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * Only the ulid column is generated, the primary key stays numeric.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Build the messaging driver for this connection.
     */
    public function driver(): Driver
    {
        return app(ProviderManager::class)->driver($this);
    }

    /**
     * Make this the only default connection for its channel in the workspace.
     */
    public function makeDefault(): void
    {
        static::query()
            ->where('workspace_id', $this->workspace_id)
            ->where('channel', $this->channel)
            ->whereKeyNot($this->getKey())
            ->update(['is_default' => false]);

        $this->forceFill(['is_default' => true])->save();
    }

    /**
     * Credentials with secret values masked, safe to return to clients.
     *
     * @return array<string, string|null>
     */
    public function maskedCredentials(): array
    {
        $secrets = app(ProviderManager::class)->secretFields($this->provider);

        return collect($this->credentials)
            ->map(function ($value, $key) use ($secrets) {
                if (! in_array($key, $secrets, true) || blank($value)) {
                    return $value;
                }

                return str_repeat('•', 6) . substr((string) $value, -4);
            })
            ->all();
    }

    /**
     * Record the outcome of a connection test.
     */
    public function markTested(?string $error = null): void
    {
        $this->forceFill([
            'status' => $error ? ConnectionStatus::FAILED : ConnectionStatus::ACTIVE,
            'last_error' => $error,
            'last_tested_at' => now(),
        ])->save();
    }
}
