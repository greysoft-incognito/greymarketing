<?php

namespace App\Services\Billing;

use App\Enums\Channel;
use App\Models\Workspace;
use App\Models\WorkspaceUsage;

/**
 * Reads and records a workspace's usage against its plan limits. A null limit means unlimited.
 */
class UsageLimits
{
    /**
     * How many more contacts the workspace may add, or null for no limit.
     */
    public function contactRoom(Workspace $workspace): ?int
    {
        $limit = $workspace->plan()?->contacts_limit;

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $workspace->contacts()->count());
    }

    /**
     * How many more messages the workspace may send on a channel this month, or null for no limit.
     */
    public function messageRoom(Workspace $workspace, Channel $channel): ?int
    {
        $limit = $workspace->plan()?->messageLimit($channel);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->sent($workspace, $channel));
    }

    public function sent(Workspace $workspace, Channel $channel): int
    {
        return (int) $workspace->usage()
            ->where('period', WorkspaceUsage::currentPeriod())
            ->value(WorkspaceUsage::column($channel));
    }

    public function recordSent(Workspace $workspace, Channel $channel, int $count): void
    {
        if ($count < 1) {
            return;
        }

        $period = WorkspaceUsage::currentPeriod();

        WorkspaceUsage::query()->insertOrIgnore([
            'workspace_id' => $workspace->id,
            'period' => $period,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workspace->usage()->where('period', $period)->increment(WorkspaceUsage::column($channel), $count);
    }

    /**
     * Usage and limits for display.
     *
     * @return array<string, array{used: int, limit: ?int}>
     */
    public function summary(Workspace $workspace): array
    {
        $plan = $workspace->plan();

        return [
            'contacts' => ['used' => $workspace->contacts()->count(), 'limit' => $plan?->contacts_limit],
            'emails' => ['used' => $this->sent($workspace, Channel::EMAIL), 'limit' => $plan?->emails_limit],
            'sms' => ['used' => $this->sent($workspace, Channel::SMS), 'limit' => $plan?->sms_limit],
        ];
    }
}
