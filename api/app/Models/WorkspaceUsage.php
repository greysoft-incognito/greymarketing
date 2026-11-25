<?php

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Messages a workspace sent in one calendar month.
 */
class WorkspaceUsage extends Model
{
    protected $table = 'workspace_usage';

    protected $fillable = [
        'period',
        'emails_sent',
        'sms_sent',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'emails_sent' => 'integer',
            'sms_sent' => 'integer',
        ];
    }

    public static function column(Channel $channel): string
    {
        return match ($channel) {
            Channel::EMAIL => 'emails_sent',
            Channel::SMS => 'sms_sent',
        };
    }

    public static function currentPeriod(): string
    {
        return now()->startOfMonth()->toDateString();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
