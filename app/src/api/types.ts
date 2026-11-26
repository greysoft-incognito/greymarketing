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
  role?: WorkspaceRole;
  is_owner: boolean;
  plan?: { id: string; name: string } | null;
  created_at: string;
}

export type WorkspaceRole = 'owner' | 'admin' | 'member';

export interface Member {
  id: string;
  name: string;
  email: string;
  role: WorkspaceRole;
  is_you: boolean;
  joined_at: string;
}

export interface Invitation {
  id: string;
  email: string;
  role: Exclude<WorkspaceRole, 'owner'>;
  workspace?: { id: string; name: string };
  invited_by?: { id: string; name: string } | null;
  is_expired: boolean;
  expires_at: string;
  created_at: string;
}

export interface Branding {
  app_name: string;
  /** Admin-uploaded image for the sign-in panel; null means use the bundled default. */
  auth_image: string | null;
  /** Admin-set panel text per page; null means use the page's own wording. */
  auth_messages: { sign_in: string | null; sign_up: string | null; invite: string | null };
}

export interface Country {
  code: string;
  dial_code: string;
}

export interface Plan {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  price: number;
  currency: string;
  interval: 'month' | 'year';
  limits: { contacts: number | null; emails: number | null; sms: number | null; seats: number | null };
  is_free: boolean;
  is_default: boolean;
}

export interface Subscription {
  id: string;
  status: 'pending' | 'active' | 'cancelled' | 'expired';
  amount: number;
  currency: string;
  /** Whether the saved card is charged for the next period. */
  auto_renew: boolean;
  /** When the next renewal charge is due; null unless auto-renewal is on. */
  renews_at: string | null;
  /** The card renewals are charged to. */
  card: { brand: string | null; last4: string | null; expiry: string | null } | null;
  starts_at: string | null;
  ends_at: string | null;
  cancelled_at: string | null;
  plan?: Plan;
}

export interface Transaction {
  reference: string;
  method: string;
  status: 'pending' | 'complete' | 'rejected';
  amount: number;
  discount: number;
  due: number;
  type: string;
  /** Set once a subscription payment completes and has a PDF invoice. */
  invoice_number: string | null;
  subscription: { id: string; currency: string; plan: Pick<Plan, 'name' | 'interval'> } | null;
  created_at: string;
}

export interface Checkout extends Envelope<Subscription> {
  /** Flutterwave payment page for paid plans; null when the plan started at once. */
  checkout_url: string | null;
}

export interface UsageItem {
  used: number;
  limit: number | null;
}

export interface Billing {
  plan: Plan | null;
  subscription: Subscription | null;
  usage: { contacts: UsageItem; emails: UsageItem; sms: UsageItem; seats: UsageItem };
  period: { starts_at: string; ends_at: string };
}

export interface EmailEngagement {
  /** Emails accepted by a provider. */
  emails: number;
  /** Emails opened at least once. */
  opened: number;
  open_rate: number;
  /** Emails with at least one link clicked. */
  clicked: number;
  click_rate: number;
}

export interface PeriodStats {
  sent: number;
  delivered: number;
  failed: number;
  delivery_rate: number;
  email_engagement: EmailEngagement;
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
  template?: Template | null;
  connection?: ProviderConnection | null;
  lists?: ContactList[];
  recipients_count: number;
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface CampaignDetail extends Envelope<Campaign> {
  /** Contacts it would reach now, while it can still be edited. */
  audience: number | null;
}

export interface CampaignInput {
  name?: string;
  channel?: Channel;
  template?: string | null;
  connection?: string | null;
  lists?: string[];
  scheduled_at?: string | null;
}

export type MessageStatus = 'pending' | 'sent' | 'delivered' | 'failed' | 'skipped';

export interface CampaignReport {
  status: CampaignStatus;
  recipients: number;
  counts: Record<MessageStatus, number>;
  delivery_rate: number;
  failure_rate: number;
  /** Null for SMS campaigns. */
  engagement: EmailEngagement | null;
  opt_outs: number;
  started_at: string | null;
  completed_at: string | null;
}

export interface ReportQuery {
  days?: number;
  channel?: Channel;
}

export interface ConnectionReport {
  /** Null for campaigns sent before connections were recorded at launch. */
  id: string | null;
  name: string | null;
  provider: string | null;
  channel: Channel;
  sent: number;
  delivered: number;
  failed: number;
  delivery_rate: number;
  /** Null for SMS connections. */
  opened: number | null;
  open_rate: number | null;
  clicked: number | null;
  click_rate: number | null;
}

export interface WorkspaceReport {
  period: { from: string; to: string; days: number };
  channel: Channel | null;
  campaigns: number;
  counts: Record<MessageStatus, number>;
  delivery_rate: number;
  failure_rate: number;
  /** Null when the report is filtered to SMS. */
  engagement: EmailEngagement | null;
  opt_outs: number;
  daily: Array<{ date: string; email: number; sms: number }>;
  failure_reasons: Array<{ reason: string; total: number }>;
  connections: ConnectionReport[];
}

export interface CampaignPerformance extends Campaign {
  counts: { sent: number; delivered: number; failed: number; opened: number | null; clicked: number | null };
  delivery_rate: number;
  open_rate: number | null;
  click_rate: number | null;
}

export interface CampaignMessage {
  id: number;
  to: string;
  contact: Contact | null;
  status: MessageStatus;
  provider_message_id: string | null;
  error: string | null;
  attempts: number;
  sent_at: string | null;
  delivered_at: string | null;
  opened_at: string | null;
  opens_count: number;
  clicked_at: string | null;
  clicks_count: number;
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
