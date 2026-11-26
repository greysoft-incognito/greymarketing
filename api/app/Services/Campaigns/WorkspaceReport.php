<?php

namespace App\Services\Campaigns;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Delivery figures for the campaigns a workspace started in a period, built with aggregate queries.
 */
class WorkspaceReport
{
    public const FAILURE_REASONS = 5;

    public function __construct(
        protected WorkspaceStats $stats,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Workspace $workspace, int $days, ?Channel $channel = null): array
    {
        $from = now()->startOfDay()->subDays($days - 1);
        $counts = $this->counts($workspace, $from, $channel);
        $attempted = $counts['sent'] + $counts['delivered'] + $counts['failed'];
        $reached = $counts['sent'] + $counts['delivered'];

        return [
            'period' => ['from' => $from, 'to' => now(), 'days' => $days],
            'channel' => $channel?->value,
            'campaigns' => $this->campaigns($workspace, $from, $channel)->count(),
            'counts' => $counts,
            'delivery_rate' => $reached ? round($counts['delivered'] / $reached * 100, 1) : 0.0,
            'failure_rate' => $attempted ? round($counts['failed'] / $attempted * 100, 1) : 0.0,
            'opt_outs' => $this->optOuts($workspace, $from, $channel),
            'daily' => $this->stats->daily($workspace, $from, $channel),
            'failure_reasons' => $this->failureReasons($workspace, $from, $channel),
            'connections' => $this->connections($workspace, $from, $channel),
        ];
    }

    /**
     * Campaigns started in the period, optionally for one channel.
     *
     * @return Builder<Campaign>
     */
    public function campaigns(Workspace $workspace, Carbon $from, ?Channel $channel = null): Builder
    {
        return $workspace->campaigns()
            ->getQuery()
            ->where('started_at', '>=', $from)
            ->when($channel, fn (Builder $query) => $query->where('channel', $channel));
    }

    /**
     * @return array<string, int>
     */
    protected function counts(Workspace $workspace, Carbon $from, ?Channel $channel): array
    {
        $counts = $this->messages($workspace, $from, $channel)
            ->selectRaw('campaign_messages.status, COUNT(*) as total')
            ->groupBy('campaign_messages.status')
            ->pluck('total', 'status');

        return collect(MessageStatus::cases())
            ->mapWithKeys(fn (MessageStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * The most common provider errors among failed messages.
     *
     * @return array<int, array{reason: string, total: int}>
     */
    protected function failureReasons(Workspace $workspace, Carbon $from, ?Channel $channel): array
    {
        return $this->messages($workspace, $from, $channel)
            ->where('campaign_messages.status', MessageStatus::FAILED->value)
            ->selectRaw("COALESCE(campaign_messages.error, '') as reason, COUNT(*) as total")
            ->groupByRaw("COALESCE(campaign_messages.error, '')")
            ->orderByDesc('total')
            ->limit(self::FAILURE_REASONS)
            ->get()
            ->map(fn (object $row) => ['reason' => $row->reason ?: 'No reason given', 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Totals per provider connection, busiest first.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function connections(Workspace $workspace, Carbon $from, ?Channel $channel): array
    {
        $sent = MessageStatus::SENT->value;
        $delivered = MessageStatus::DELIVERED->value;
        $failed = MessageStatus::FAILED->value;

        return $this->messages($workspace, $from, $channel)
            ->leftJoin('provider_connections', 'provider_connections.id', '=', 'campaigns.provider_connection_id')
            ->whereIn('campaign_messages.status', [$sent, $delivered, $failed])
            ->selectRaw('provider_connections.ulid as id, provider_connections.name, provider_connections.provider, campaigns.channel')
            ->selectRaw('SUM(CASE WHEN campaign_messages.status = ? THEN 1 ELSE 0 END) as sent', [$sent])
            ->selectRaw('SUM(CASE WHEN campaign_messages.status = ? THEN 1 ELSE 0 END) as delivered', [$delivered])
            ->selectRaw('SUM(CASE WHEN campaign_messages.status = ? THEN 1 ELSE 0 END) as failed', [$failed])
            ->groupBy('provider_connections.ulid', 'provider_connections.name', 'provider_connections.provider', 'campaigns.channel')
            ->get()
            ->map(function (object $row) {
                $reached = (int) $row->sent + (int) $row->delivered;

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'provider' => $row->provider,
                    'channel' => $row->channel,
                    'sent' => $reached,
                    'delivered' => (int) $row->delivered,
                    'failed' => (int) $row->failed,
                    'delivery_rate' => $reached ? round($row->delivered / $reached * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['sent'] + $row['failed'])
            ->values()
            ->all();
    }

    /**
     * Contacts who unsubscribed from the channel, or either channel, during the period.
     */
    protected function optOuts(Workspace $workspace, Carbon $from, ?Channel $channel): int
    {
        $columns = $channel ? ["{$channel->value}_unsubscribed_at"] : ['email_unsubscribed_at', 'sms_unsubscribed_at'];

        return $workspace->contacts()
            ->where(function (Builder $query) use ($columns, $from) {
                foreach ($columns as $column) {
                    $query->orWhere($column, '>=', $from);
                }
            })
            ->count();
    }

    protected function messages(Workspace $workspace, Carbon $from, ?Channel $channel): QueryBuilder
    {
        return DB::table('campaign_messages')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_messages.campaign_id')
            ->where('campaigns.workspace_id', $workspace->id)
            ->where('campaigns.started_at', '>=', $from)
            ->when($channel, fn (QueryBuilder $query) => $query->where('campaigns.channel', $channel->value));
    }
}
