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

export interface SmsInfo {
  encoding: 'gsm7' | 'ucs2';
  length: number;
  segments: number;
}

export interface Template {
  id: string;
  channel: Channel;
  name: string;
  subject: string | null;
  html: string | null;
  body: string | null;
  tags: string[];
  sms?: SmsInfo;
  created_at: string;
  updated_at: string;
}

export interface TemplateInput {
  channel?: Channel;
  name?: string;
  subject?: string | null;
  html?: string | null;
  body?: string | null;
}

export interface ProviderField {
  key: string;
  label: string;
  required: boolean;
  secret: boolean;
}

export interface Provider {
  key: string;
  name: string;
  channels: Channel[];
  credentials: ProviderField[];
  settings: ProviderField[];
}

export type ConnectionStatus = 'untested' | 'active' | 'failed';

export interface ProviderConnection {
  id: string;
  name: string;
  provider: string;
  channel: Channel;
  credentials: Record<string, string | null>;
  settings: Record<string, string | number | null>;
  is_default: boolean;
  status: ConnectionStatus;
  last_error: string | null;
  last_tested_at: string | null;
  status_url?: string;
  inbound_url?: string;
  created_at: string;
}

export interface ConnectionInput {
  name?: string;
  provider?: string;
  channel?: Channel;
  is_default?: boolean;
  credentials?: Record<string, string>;
  settings?: Record<string, string>;
}

export interface ContactList {
  id: string;
  name: string;
  description: string | null;
  contacts_count?: number;
  created_at: string;
}

export interface Contact {
  id: string;
  email: string | null;
  phone: string | null;
  first_name: string | null;
  last_name: string | null;
  fields: Record<string, string | null>;
  email_subscribed: boolean;
  sms_subscribed: boolean;
  email_unsubscribed_at: string | null;
  sms_unsubscribed_at: string | null;
  lists?: ContactList[];
  created_at: string;
}

export interface ContactInput {
  email?: string | null;
  phone?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  fields?: Record<string, string>;
  email_subscribed?: boolean;
  sms_subscribed?: boolean;
  lists?: string[];
}

export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ContactImport {
  id: string;
  status: ImportStatus;
  mapping: Record<string, string | null>;
  list?: ContactList | null;
  processed: number;
  imported: number;
  skipped: number;
  failures: Array<{ line: number; reason: string }>;
  completed_at: string | null;
  created_at: string;
}

export interface ImportUpload extends Envelope<ContactImport> {
  headers: string[];
  sample: Array<Record<string, string>>;
  fields: string[];
}
