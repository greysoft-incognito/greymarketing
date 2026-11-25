export type Channel = 'email' | 'sms';

export interface Envelope<T> {
  data: T;
  message: string;
  status: 'success' | 'error';
  status_code: number;
}

export interface Paginated<T> extends Envelope<T[]> {
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
}

export interface User {
  id: number;
  username: string;
  firstname: string;
  lastname: string;
  email: string | null;
  phone: string | null;
  image: string | null;
  roles?: string[];
  permissions?: string[];
}

export interface AuthResponse extends Envelope<User> {
  token: string;
}

export interface Workspace {
  id: string;
  name: string;
  default_country: string;
  role?: 'owner' | 'admin' | 'member';
  is_owner: boolean;
  created_at: string;
}

export interface Plan {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  price: number;
  currency: string;
  interval: 'month' | 'year';
  limits: { contacts: number | null; emails: number | null; sms: number | null };
  is_free: boolean;
  is_default: boolean;
}

export interface Subscription {
  id: string;
  status: 'pending' | 'active' | 'cancelled' | 'expired';
  amount: number;
  currency: string;
  starts_at: string | null;
  ends_at: string | null;
  plan?: Plan;
}

export interface UsageItem {
  used: number;
  limit: number | null;
}

export interface Billing {
  plan: Plan | null;
  subscription: Subscription | null;
  usage: { contacts: UsageItem; emails: UsageItem; sms: UsageItem };
  period: { starts_at: string; ends_at: string };
}

export interface PeriodStats {
  sent: number;
  delivered: number;
  failed: number;
  delivery_rate: number;
}

export interface WorkspaceStats {
  daily: Array<{ date: string; email: number; sms: number }>;
  this_month: PeriodStats;
  last_month: PeriodStats;
  contacts: { total: number; added_this_month: number; opt_outs_this_month: number };
}

export type CampaignStatus = 'draft' | 'scheduled' | 'preparing' | 'sending' | 'completed' | 'cancelled' | 'failed';

export interface Campaign {
  id: string;
  name: string;
  channel: Channel;
  status: CampaignStatus;
  recipients_count: number;
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface ProviderConnection {
  id: string;
  name: string;
  provider: string;
  channel: Channel;
  is_default: boolean;
  status: string;
  last_error: string | null;
  last_tested_at: string | null;
}
