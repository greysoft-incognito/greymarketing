<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImport extends Model
{
    use HasFactory;
    use HasPublicUlid;

    /**
     * Most failed rows kept for display, so the column stays small.
     */
    public const MAX_FAILURES = 100;

    protected $fillable = [
        'file_path',
        'mapping',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'mapping' => 'array',
            'failures' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }
}
