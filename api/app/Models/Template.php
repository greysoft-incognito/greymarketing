<?php

namespace App\Models;

use App\Enums\Channel;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'channel',
        'name',
        'subject',
        'html',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
