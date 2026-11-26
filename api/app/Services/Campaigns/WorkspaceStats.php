<?php

namespace App\Services\Campaigns;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sending and audience figures for a workspace dashboard, built with aggregate queries.
 */
class WorkspaceStats
{
    /**
     * @return array<string, mixed>
     */
    public function build(Workspace $workspace, int $days): array
    {
        $monthStart = now()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();

        return [
            'daily' => $this->daily($workspace, now()->startOfDay()->subDays($days - 1)),
            'this_month' => $this->period($workspace, $monthStart),
            'last_month' => $this->period($workspace, $lastMonthStart, $monthStart),
            'contacts' => [
                'total' => $workspace->contacts()->count(),
                'added_this_month' => $workspace->contacts()->where('created_at', '>=', $monthStart)->count(),
                'opt_outs_this_month' => $workspace->contacts()
                    ->where(fn (Builder $query) => $query
                        ->where('email_unsubscribed_at', '>=', $monthStart)
                        ->orWhere('sms_unsubscribed_at', '>=', $monthStart))
                    ->count(),
            ],
        ];
    }

    /**
     * Messages sent per day and channel, with a zero for days without sends.
     *
     * @return array<int, array{date: string, email: int, sms: int}>
     */
    public function daily(Workspace $workspace, Carbon $from, ?Channel $channel = null): array
    {
        $rows = $this->messages($workspace)
            ->where('campaign_messages.sent_at', '>=', $from)
            ->when($channel, fn (QueryBuilder $query) => $query->where('campaigns.channel', $channel->value))
            ->selectRaw('DATE(campaign_messages.sent_at) as day, campaigns.channel, COUNT(*) as total')
            ->groupByRaw('DATE(campaign_messages.sent_at), campaigns.channel')
            ->get();

        $days = [];

        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'email' => 0, 'sms' => 0];
        }

        foreach ($rows as $row) {
            $date = Carbon::parse($row->day)->toDateString();

            if (isset($days[$date])) {
                $days[$date][$row->channel] = (int) $row->total;
            }
        }

        return array_values($days);
    }

    /**
     * @return array{sent: int, delivered: int, failed: int, delivery_rate: float}
     */
    protected function period(Workspace $workspace, Carbon $from, ?Carbon $to = null): array
    {
        $counts = $this->messages($workspace)
            ->where('campaign_messages.updated_at', '>=', $from)
            ->when($to, fn (QueryBuilder $query) => $query->where('campaign_messages.updated_at', '<', $to))
            ->whereIn('campaign_messages.status', [MessageStatus::SENT->value, MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->selectRaw('campaign_messages.status, COUNT(*) as total')
            ->groupBy('campaign_messages.status')
            ->pluck('total', 'status');

        $delivered = (int) ($counts[MessageStatus::DELIVERED->value] ?? 0);
        $sent = $delivered + (int) ($counts[MessageStatus::SENT->value] ?? 0);

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => (int) ($counts[MessageStatus::FAILED->value] ?? 0),
            'delivery_rate' => $sent ? round($delivered / $sent * 100, 1) : 0.0,
        ];
    }

    protected function messages(Workspace $workspace): QueryBuilder
    {
        return DB::table('campaign_messages')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_messages.campaign_id')
            ->where('campaigns.workspace_id', $workspace->id);
    }
}
