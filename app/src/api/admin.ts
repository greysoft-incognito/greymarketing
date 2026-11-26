import type { Billing, Envelope, Paginated, Plan, Subscription, Workspace } from './types';
import { alova } from './client';

export interface AdminPlan extends Plan {
  is_active: boolean;
  sort: number;
  active_subscriptions_count?: number;
}

export interface PlanInput {
  name?: string;
  slug?: string;
  description?: string | null;
  price?: number;
  currency?: string;
  interval?: Plan['interval'];
  contacts_limit?: number | null;
  emails_limit?: number | null;
  sms_limit?: number | null;
  seats_limit?: number | null;
  is_active?: boolean;
  is_default?: boolean;
  sort?: number;
}

export interface AdminWorkspace extends Workspace {
  owner?: { id: string; name: string; email: string | null } | null;
  subscription?: Subscription | null;
  contacts_count?: number;
  campaigns_count?: number;
  usage_this_month?: { emails: number; sms: number };
}

export interface AdminSubscription extends Subscription {
  cancelled_at: string | null;
  workspace?: { id: string; name: string };
  created_at: string;
}

export interface AdminWorkspaceBilling extends Billing {
  workspace: { id: string; name: string };
}

export interface MessageTotals {
  emails: number;
  sms: number;
}

export interface TopWorkspace {
  id: string;
  name: string;
  plan: string | null;
  emails: { used: number; limit: number | null };
  sms: { used: number; limit: number | null };
}

export interface PlatformOverview {
  workspaces: { total: number; new_this_month: number; paying: number };
  mrr: Array<{ currency: string; amount: number }>;
  messages: { this_month: MessageTotals; last_month: MessageTotals };
  plans: Array<{ id: string; name: string; workspaces: number }>;
  providers: Array<{ provider: string; total: number; failed: number; error_rate: number }>;
  over_limit: number;
  top: TopWorkspace[];
}

export interface DeliveryRate {
  sent: number;
  delivered: number;
  failed: number;
  delivery_rate: number;
}

export interface PlatformUsage {
  period: { starts_at: string; ends_at: string };
  totals: MessageTotals;
  delivery: { email: DeliveryRate; sms: DeliveryRate };
  over_limit: number;
  top: TopWorkspace[];
  providers: Array<{ provider: string; channel: 'email' | 'sms'; total: number }>;
}

export interface AdminWorkspaceQuery {
  search?: string;
  plan?: string;
  page?: number;
  limit?: number;
}

/**
 * The staff-only admin API. Plan and subscription changes go stale every admin read that shows them.
 */
export const admin = {
  overview: () => alova.Get<Envelope<PlatformOverview>>('/admin/stats', { name: 'admin.stats', hitSource: ['admin.plans.write', 'admin.subscriptions.write'] }),

  usage: (month?: string) =>
    alova.Get<Envelope<PlatformUsage>>('/admin/stats/usage', { name: 'admin.usage', params: month ? { month } : {}, hitSource: 'admin.subscriptions.write' }),

  plans: (params: { page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<AdminPlan>>('/admin/plans', { name: 'admin.plans.list', params, hitSource: ['admin.plans.write', 'admin.subscriptions.write'] }),

  createPlan: (data: PlanInput) => alova.Post<Envelope<AdminPlan>>('/admin/plans', data, { name: 'admin.plans.write' }),

  updatePlan: (id: string, data: PlanInput) => alova.Put<Envelope<AdminPlan>>(`/admin/plans/${id}`, data, { name: 'admin.plans.write' }),

  removePlan: (id: string) => alova.Delete(`/admin/plans/${id}`, undefined, { name: 'admin.plans.write' }),

  workspaces: (params: AdminWorkspaceQuery = {}) =>
    alova.Get<Paginated<AdminWorkspace>>('/admin/workspaces', { name: 'admin.workspaces.list', params, hitSource: ['admin.subscriptions.write', 'admin.plans.write'] }),

  workspace: (id: string) =>
    alova.Get<Envelope<AdminWorkspaceBilling>>(`/admin/workspaces/${id}`, { name: 'admin.workspaces.show', hitSource: ['admin.subscriptions.write', 'admin.plans.write'] }),

  grant: (id: string, data: { plan: string; ends_at?: string | null }) =>
    alova.Post<Envelope<AdminSubscription>>(`/admin/workspaces/${id}/subscription`, data, { name: 'admin.subscriptions.write' }),

  subscriptions: (params: { workspace?: string; status?: Subscription['status']; page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<AdminSubscription>>('/admin/subscriptions', { name: 'admin.subscriptions.list', params, hitSource: 'admin.subscriptions.write' }),

  cancelSubscription: (id: string) =>
    alova.Post<Envelope<AdminSubscription>>(`/admin/subscriptions/${id}/cancel`, undefined, { name: 'admin.subscriptions.write' }),
};
