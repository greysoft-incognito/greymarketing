<?php

namespace App\Services\Admin;

use App\Enums\MessageStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Workspace;
use App\Models\WorkspaceUsage;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide figures for the admin console, built with aggregate queries.
 */
class PlatformStats
{
    /**
     * Workspaces listed in a "top by volume" table.
     */
    public const TOP_LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $monthStart = now()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();

        return [
            'workspaces' => [
                'total' => Workspace::query()->count(),
                'new_this_month' => Workspace::query()->where('created_at', '>=', $monthStart)->count(),
                'paying' => $this->currentSubscriptions()->where('subscriptions.amount', '>', 0)->distinct()->count('subscriptions.workspace_id'),
            ],
            'mrr' => $this->recurringRevenue(),
            'messages' => [
                'this_month' => $this->messageTotals($monthStart),
                'last_month' => $this->messageTotals($lastMonthStart),
            ],
            'plans' => $this->workspacesByPlan(),
            'providers' => $this->providerHealth(now()->subDay()),
            'over_limit' => $this->overLimit($monthStart),
            'top' => $this->topWorkspaces($monthStart, 5),
        ];
    }

    /**
     * Sending volume for one calendar month.
     *
     * @return array<string, mixed>
     */
    public function usage(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $from->copy()->addMonth();

        return [
            'period' => ['starts_at' => $from, 'ends_at' => $to->copy()->subSecond()],
            'totals' => $this->messageTotals($from),
            'delivery' => $this->deliveryRates($from, $to),
            'over_limit' => $this->overLimit($from),
            'top' => $this->topWorkspaces($from, self::TOP_LIMIT),
            'providers' => $this->providerVolume($from, $to),
        ];
    }

    /**
     * Monthly value of current paid subscriptions per currency, with yearly plans spread over twelve months.
     *
     * @return array<int, array{currency: string, amount: float}>
     */
    protected function recurringRevenue(): array
    {
        return $this->currentSubscriptions()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.amount', '>', 0)
            ->selectRaw(
                'subscriptions.currency, SUM(CASE WHEN plans.interval = ? THEN subscriptions.amount / 12 ELSE subscriptions.amount END) as total',
                [PlanInterval::YEAR->value]
            )
            ->groupBy('subscriptions.currency')
            ->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'amount' => round((float) $row->total, 2)])
            ->all();
    }

    /**
     * @return array{emails: int, sms: int}
     */
    protected function messageTotals(Carbon $month): array
    {
        $row = DB::table('workspace_usage')
            ->where('period', $month->toDateString())
            ->selectRaw('COALESCE(SUM(emails_sent), 0) as emails, COALESCE(SUM(sms_sent), 0) as sms')
            ->first();

        return ['emails' => (int) $row->emails, 'sms' => (int) $row->sms];
    }

    /**
     * Workspaces on each active plan; workspaces without a subscription count toward the default plan.
     *
     * @return array<int, array{id: string, name: string, workspaces: int}>
     */
    protected function workspacesByPlan(): array
    {
        $counts = $this->currentSubscriptions()
            ->selectRaw('subscriptions.plan_id, COUNT(DISTINCT subscriptions.workspace_id) as total')
            ->groupBy('subscriptions.plan_id')
            ->pluck('total', 'plan_id');

        $unsubscribed = Workspace::query()->count() - (int) $this->currentSubscriptions()->distinct()->count('subscriptions.workspace_id');

        return Plan::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $counts->keys()))
            ->orderBy('sort')
            ->orderBy('price')
            ->limit(50)
            ->get(['id', 'ulid', 'name', 'is_default'])
            ->map(fn (Plan $plan) => [
                'id' => $plan->ulid,
                'name' => $plan->name,
                'workspaces' => (int) ($counts[$plan->id] ?? 0) + ($plan->is_default ? max(0, $unsubscribed) : 0),
            ])
            ->all();
    }

    /**
     * Failure rate per provider for messages finished since the given time.
     *
     * @return array<int, array{provider: string, total: int, failed: int, error_rate: float}>
     */
    protected function providerHealth(Carbon $since): array
    {
        $rows = $this->messagesByProvider()
            ->where('campaign_messages.updated_at', '>=', $since)
            ->whereIn('campaign_messages.status', [MessageStatus::SENT->value, MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->selectRaw(
                'provider_connections.provider, COUNT(*) as total, SUM(CASE WHEN campaign_messages.status = ? THEN 1 ELSE 0 END) as failed',
                [MessageStatus::FAILED->value]
            )
            ->groupBy('provider_connections.provider')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'provider' => $row->provider,
            'total' => (int) $row->total,
            'failed' => (int) $row->failed,
            'error_rate' => $row->total ? round($row->failed / $row->total * 100, 1) : 0.0,
        ])->all();
    }

    /**
     * Messages handed to each provider in a period, split by channel.
     *
     * @return array<int, array{provider: string, channel: string, total: int}>
     */
    protected function providerVolume(Carbon $from, Carbon $to): array
    {
        return $this->messagesByProvider()
            ->where('campaign_messages.sent_at', '>=', $from)
            ->where('campaign_messages.sent_at', '<', $to)
            ->selectRaw('provider_connections.provider, campaigns.channel, COUNT(*) as total')
            ->groupBy('provider_connections.provider', 'campaigns.channel')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['provider' => $row->provider, 'channel' => $row->channel, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Share of sent messages that got a delivery receipt, per channel.
     *
     * @return array<string, array{sent: int, delivered: int, failed: int, delivery_rate: float}>
     */
    protected function deliveryRates(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('campaign_messages')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_messages.campaign_id')
            ->where('campaign_messages.updated_at', '>=', $from)
            ->where('campaign_messages.updated_at', '<', $to)
            ->whereIn('campaign_messages.status', [MessageStatus::SENT->value, MessageStatus::DELIVERED->value, MessageStatus::FAILED->value])
            ->selectRaw('campaigns.channel, campaign_messages.status, COUNT(*) as total')
            ->groupBy('campaigns.channel', 'campaign_messages.status')
            ->get();

        $rates = [];

        foreach (['email', 'sms'] as $channel) {
            $counts = $rows->where('channel', $channel)->pluck('total', 'status');
            $delivered = (int) ($counts[MessageStatus::DELIVERED->value] ?? 0);
            $sent = $delivered + (int) ($counts[MessageStatus::SENT->value] ?? 0);

            $rates[$channel] = [
                'sent' => $sent,
                'delivered' => $delivered,
                'failed' => (int) ($counts[MessageStatus::FAILED->value] ?? 0),
                'delivery_rate' => $sent ? round($delivered / $sent * 100, 1) : 0.0,
            ];
        }

        return $rates;
    }

    /**
     * Workspaces that used up their plan's email or SMS allowance in the month.
     */
    protected function overLimit(Carbon $month): int
    {
        return $this->usageWithLimits($month)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $q) => $q->whereNotNull('plans.emails_limit')->whereColumn('workspace_usage.emails_sent', '>=', 'plans.emails_limit'))
                ->orWhere(fn (Builder $q) => $q->whereNotNull('plans.sms_limit')->whereColumn('workspace_usage.sms_sent', '>=', 'plans.sms_limit')))
            ->distinct()
            ->count('workspace_usage.workspace_id');
    }

    /**
     * The busiest workspaces in a month, with the limits of the plan they are on.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function topWorkspaces(Carbon $month, int $limit): array
    {
        return $this->usageWithLimits($month)
            ->join('workspaces', 'workspaces.id', '=', 'workspace_usage.workspace_id')
            ->select([
                'workspaces.ulid',
                'workspaces.name',
                'plans.name as plan_name',
                'plans.emails_limit',
                'plans.sms_limit',
                'workspace_usage.emails_sent',
                'workspace_usage.sms_sent',
            ])
            ->orderByRaw('(workspace_usage.emails_sent + workspace_usage.sms_sent) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->ulid,
                'name' => $row->name,
                'plan' => $row->plan_name,
                'emails' => ['used' => (int) $row->emails_sent, 'limit' => $row->emails_limit === null ? null : (int) $row->emails_limit],
                'sms' => ['used' => (int) $row->sms_sent, 'limit' => $row->sms_limit === null ? null : (int) $row->sms_limit],
            ])
            ->all();
    }

    /**
     * A month's usage rows joined to the plan each workspace is on: its current subscription's, else the default plan.
     */
    protected function usageWithLimits(Carbon $month): Builder
    {
        $defaultPlan = Plan::query()->where('is_default', true)->value('id');

        return DB::table('workspace_usage')
            ->where('workspace_usage.period', $month->toDateString())
            ->leftJoin('subscriptions', fn (JoinClause $join) => $join
                ->on('subscriptions.workspace_id', '=', 'workspace_usage.workspace_id')
                ->whereIn('subscriptions.status', SubscriptionStatus::current())
                ->where('subscriptions.ends_at', '>', now()))
            ->leftJoin('plans', fn (JoinClause $join) => $join
                ->on('plans.id', '=', 'subscriptions.plan_id')
                ->orWhere(fn (JoinClause $q) => $q->whereNull('subscriptions.id')->where('plans.id', '=', $defaultPlan ?? 0)));
    }

    protected function currentSubscriptions(): Builder
    {
        return DB::table('subscriptions')
            ->whereIn('subscriptions.status', SubscriptionStatus::current())
            ->where('subscriptions.ends_at', '>', now());
    }

    protected function messagesByProvider(): Builder
    {
        return DB::table('campaign_messages')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_messages.campaign_id')
            ->join('provider_connections', 'provider_connections.id', '=', 'campaigns.provider_connection_id');
    }
}
