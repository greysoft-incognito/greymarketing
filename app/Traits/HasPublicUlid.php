<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Exposes a generated `ulid` column as the route key while the primary key stays numeric.
 */
trait HasPublicUlid
{
    use HasUlids;

    /**
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
}
