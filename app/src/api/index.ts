import type {
  AuthResponse,
  Billing,
  Campaign,
  CampaignDetail,
  CampaignInput,
  CampaignMessage,
  CampaignPerformance,
  CampaignReport,
  Channel,
  Checkout,
  ConnectionInput,
  Contact,
  Country,
  ContactImport,
  ContactInput,
  ContactList,
  Envelope,
  MessageStatus,
  ImportUpload,
  Invitation,
  Member,
  Paginated,
  Plan,
  Provider,
  ProviderConnection,
  ReportQuery,
  Subscription,
  Template,
  TemplateInput,
  Transaction,
  User,
  Workspace,
  WorkspaceReport,
  WorkspaceRole,
  WorkspaceStats,
} from './types';
import { alova } from './client';

export { admin } from './admin';
export type * from './admin';
export { forget, forgetAll } from './cache';
export { ApiError } from './client';
export type * from './types';

export const auth = {
  login: (data: { email: string; password: string; remember?: boolean }) =>
    alova.Post<AuthResponse>('/auth/login', data),

  register: (data: { firstname: string; lastname: string; email: string; phone?: string; password: string; password_confirmation: string }) =>
    alova.Post<AuthResponse>('/auth/register', data),

  me: () => alova.Get<Envelope<User>>('/account', { cacheFor: 0 }),

  logout: () => alova.Post('/account/logout'),
};

export const providers = {
  catalog: () => alova.Get<Envelope<Provider[]>>('/providers', { name: 'providers.catalog', cacheFor: 10 * 60 * 1000 }),
};

export const countries = {
  list: () => alova.Get<Envelope<Country[]>>('/countries', { name: 'countries', cacheFor: 60 * 60 * 1000 }),
};

export const workspaces = {
  list: () => alova.Get<Paginated<Workspace>>('/workspaces', { name: 'workspaces.list', params: { limit: 100 }, hitSource: 'workspaces.write' }),

  create: (name: string) => alova.Post<Envelope<Workspace>>('/workspaces', { name }, { name: 'workspaces.write' }),

  update: (id: string, data: Partial<Pick<Workspace, 'name' | 'default_country'>>) =>
    alova.Put<Envelope<Workspace>>(`/workspaces/${id}`, data, { name: 'workspaces.write' }),

  remove: (id: string) => alova.Delete(`/workspaces/${id}`, undefined, { name: 'workspaces.write' }),

  leave: (id: string) => alova.Post(`/workspaces/${id}/leave`, undefined, { name: 'workspaces.write' }),

  stats: (id: string, days = 14) => alova.Get<Envelope<WorkspaceStats>>(`/workspaces/${id}/stats`, { name: 'stats', params: { days }, hitSource: ['contacts.write', 'imports.write', 'campaigns.write'] }),

  billing: (id: string) => alova.Get<Envelope<Billing>>(`/workspaces/${id}/billing`, { name: 'billing', hitSource: ['billing.write', 'contacts.write', 'imports.write', 'campaigns.write', 'team.write'] }),

  campaigns: (id: string, params: { status?: string; limit?: number; page?: number } = {}) =>
    alova.Get<Paginated<Campaign>>(`/workspaces/${id}/campaigns`, { name: 'campaigns.list', params, hitSource: 'campaigns.write' }),

  connections: (id: string) => alova.Get<Paginated<ProviderConnection>>(`/workspaces/${id}/connections`, { name: 'connections.list', params: { limit: 100 }, hitSource: 'connections.write' }),
};

/**
 * Provider connections in a workspace.
 */
export const connections = {
  create: (workspace: string, data: ConnectionInput) =>
    alova.Post<Envelope<ProviderConnection>>(`/workspaces/${workspace}/connections`, data, { name: 'connections.write' }),

  update: (workspace: string, id: string, data: ConnectionInput) =>
    alova.Put<Envelope<ProviderConnection>>(`/workspaces/${workspace}/connections/${id}`, data, { name: 'connections.write' }),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/connections/${id}`, undefined, { name: 'connections.write' }),

  test: (workspace: string, id: string) =>
    alova.Post<Envelope<ProviderConnection>>(`/workspaces/${workspace}/connections/${id}/test`, undefined, { name: 'connections.write' }),

  sendTest: (workspace: string, id: string, data: { to: string; subject?: string; body: string }) =>
    alova.Post<Envelope<{ message_id: string | null }>>(`/workspaces/${workspace}/connections/${id}/send-test`, data),
};

export interface ContactQuery {
  search?: string;
  list?: string;
  subscribed?: Channel;
  page?: number;
  limit?: number;
}

export const contacts = {
  list: (workspace: string, params: ContactQuery = {}) =>
    alova.Get<Paginated<Contact>>(`/workspaces/${workspace}/contacts`, { name: 'contacts.list', params, hitSource: ['contacts.write', 'lists.write', 'imports.write'] }),

  create: (workspace: string, data: ContactInput) =>
    alova.Post<Envelope<Contact>>(`/workspaces/${workspace}/contacts`, data, { name: 'contacts.write' }),

  update: (workspace: string, id: string, data: ContactInput) =>
    alova.Put<Envelope<Contact>>(`/workspaces/${workspace}/contacts/${id}`, data, { name: 'contacts.write' }),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/contacts/${id}`, undefined, { name: 'contacts.write' }),
};

export const lists = {
  list: (workspace: string) => alova.Get<Paginated<ContactList>>(`/workspaces/${workspace}/lists`, { name: 'lists.list', params: { limit: 100 }, hitSource: ['lists.write', 'contacts.write', 'imports.write'] }),

  create: (workspace: string, data: { name: string; description?: string | null }) =>
    alova.Post<Envelope<ContactList>>(`/workspaces/${workspace}/lists`, data, { name: 'lists.write' }),

  update: (workspace: string, id: string, data: { name?: string; description?: string | null }) =>
    alova.Put<Envelope<ContactList>>(`/workspaces/${workspace}/lists/${id}`, data, { name: 'lists.write' }),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/lists/${id}`, undefined, { name: 'lists.write' }),

  attach: (workspace: string, id: string, contactIds: string[]) =>
    alova.Post<Envelope<ContactList>>(`/workspaces/${workspace}/lists/${id}/contacts`, { contacts: contactIds }, { name: 'lists.write' }),

  detach: (workspace: string, id: string, contactIds: string[]) =>
    alova.Delete<Envelope<ContactList>>(`/workspaces/${workspace}/lists/${id}/contacts`, { contacts: contactIds }, { name: 'lists.write' }),
};

export const imports = {
  list: (workspace: string, page = 1, limit = 10) =>
    alova.Get<Paginated<ContactImport>>(`/workspaces/${workspace}/imports`, { name: 'imports.list', params: { page, limit }, hitSource: 'imports.write' }),

  upload: (workspace: string, file: File) => {
    const form = new FormData();

    form.append('file', file);

    return alova.Post<ImportUpload>(`/workspaces/${workspace}/imports`, form, { name: 'imports.write' });
  },

  show: (workspace: string, id: string) => alova.Get<Envelope<ContactImport>>(`/workspaces/${workspace}/imports/${id}`, { cacheFor: 0 }),

  start: (workspace: string, id: string, data: { mapping: Record<string, string | null>; list?: string | null }) =>
    alova.Post<Envelope<ContactImport>>(`/workspaces/${workspace}/imports/${id}/start`, data, { name: 'imports.write' }),
};

export const templates = {
  list: (workspace: string, params: { channel?: Channel; page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<Template>>(`/workspaces/${workspace}/templates`, { name: 'templates.list', params, hitSource: 'templates.write' }),

  show: (workspace: string, id: string) =>
    alova.Get<Envelope<Template>>(`/workspaces/${workspace}/templates/${id}`, { name: 'templates.show', hitSource: 'templates.write' }),

  create: (workspace: string, data: TemplateInput) =>
    alova.Post<Envelope<Template>>(`/workspaces/${workspace}/templates`, data, { name: 'templates.write' }),

  update: (workspace: string, id: string, data: TemplateInput) =>
    alova.Put<Envelope<Template>>(`/workspaces/${workspace}/templates/${id}`, data, { name: 'templates.write' }),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/templates/${id}`, undefined, { name: 'templates.write' }),
};

/**
 * Campaign drafts, launches and reports. Reports are never cached, since they change while sending.
 */
export const campaigns = {
  show: (workspace: string, id: string) =>
    alova.Get<CampaignDetail>(`/workspaces/${workspace}/campaigns/${id}`, { cacheFor: 0 }),

  create: (workspace: string, data: CampaignInput) =>
    alova.Post<Envelope<Campaign>>(`/workspaces/${workspace}/campaigns`, data, { name: 'campaigns.write' }),

  update: (workspace: string, id: string, data: CampaignInput) =>
    alova.Put<Envelope<Campaign>>(`/workspaces/${workspace}/campaigns/${id}`, data, { name: 'campaigns.write' }),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/campaigns/${id}`, undefined, { name: 'campaigns.write' }),

  launch: (workspace: string, id: string, scheduledAt: string | null = null) =>
    alova.Post<Envelope<Campaign>>(`/workspaces/${workspace}/campaigns/${id}/launch`, scheduledAt ? { scheduled_at: scheduledAt } : {}, { name: 'campaigns.write' }),

  cancel: (workspace: string, id: string) =>
    alova.Post<Envelope<Campaign>>(`/workspaces/${workspace}/campaigns/${id}/cancel`, undefined, { name: 'campaigns.write' }),

  report: (workspace: string, id: string) =>
    alova.Get<Envelope<CampaignReport>>(`/workspaces/${workspace}/campaigns/${id}/report`, { cacheFor: 0 }),

  messages: (workspace: string, id: string, params: { status?: MessageStatus; page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<CampaignMessage>>(`/workspaces/${workspace}/campaigns/${id}/messages`, { cacheFor: 0, params }),
};

/**
 * Workspace-wide delivery reports for a period.
 */
export const reports = {
  summary: (workspace: string, params: ReportQuery = {}) =>
    alova.Get<Envelope<WorkspaceReport>>(`/workspaces/${workspace}/reports`, { name: 'reports.summary', params, hitSource: ['campaigns.write', 'contacts.write'] }),

  campaigns: (workspace: string, params: ReportQuery & { page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<CampaignPerformance>>(`/workspaces/${workspace}/reports/campaigns`, { name: 'reports.campaigns', params, hitSource: 'campaigns.write' }),
};

/**
 * Plans and the workspace's subscription, paid through Flutterwave checkout.
 */
export const team = {
  members: (workspace: string, params: { page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<Member>>(`/workspaces/${workspace}/members`, { name: 'team.members', params, hitSource: ['team.write', 'workspaces.write'] }),

  updateRole: (workspace: string, member: string, role: Exclude<WorkspaceRole, 'owner'>) =>
    alova.Patch<Envelope<Member>>(`/workspaces/${workspace}/members/${member}`, { role }, { name: 'team.write' }),

  remove: (workspace: string, member: string) => alova.Delete<Envelope<Member>>(`/workspaces/${workspace}/members/${member}`, undefined, { name: 'team.write' }),

  invitations: (workspace: string, params: { page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<Invitation>>(`/workspaces/${workspace}/invitations`, { name: 'team.invitations', params, hitSource: 'team.write' }),

  invite: (workspace: string, data: { email: string; role: Invitation['role'] }) =>
    alova.Post<Envelope<Invitation>>(`/workspaces/${workspace}/invitations`, data, { name: 'team.write' }),

  resend: (workspace: string, id: string) =>
    alova.Post<Envelope<Invitation>>(`/workspaces/${workspace}/invitations/${id}/resend`, undefined, { name: 'team.write' }),

  revoke: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/invitations/${id}`, undefined, { name: 'team.write' }),
};

/**
 * The invitee's side of an invite, addressed by the token in the emailed link.
 */
export const invites = {
  show: (token: string) => alova.Get<Envelope<Invitation>>(`/invites/${token}`, { cacheFor: 0 }),

  accept: (token: string) => alova.Post<Envelope<Workspace>>(`/invites/${token}/accept`, undefined, { name: 'workspaces.write' }),

  decline: (token: string) => alova.Post<Envelope<Invitation>>(`/invites/${token}/decline`),
};

export const billing = {
  plans: () => alova.Get<Paginated<Plan>>('/plans', { name: 'billing.plans', cacheFor: 10 * 60 * 1000 }),

  subscribe: (workspace: string, plan: string, redirect: string) =>
    alova.Post<Checkout>(`/workspaces/${workspace}/billing/subscribe`, { plan, redirect }, { name: 'billing.write' }),

  verify: (workspace: string, reference: string) =>
    alova.Post<Envelope<Subscription>>(`/workspaces/${workspace}/billing/verify`, { reference }, { name: 'billing.write' }),

  cancel: (workspace: string) =>
    alova.Post<Envelope<Subscription>>(`/workspaces/${workspace}/billing/cancel`, undefined, { name: 'billing.write' }),

  autoRenew: (workspace: string, enabled: boolean) =>
    alova.Put<Envelope<Subscription>>(`/workspaces/${workspace}/billing/auto-renew`, { enabled }, { name: 'billing.write' }),

  transactions: (workspace: string, params: { page?: number; limit?: number } = {}) =>
    alova.Get<Paginated<Transaction>>(`/workspaces/${workspace}/billing/transactions`, { name: 'billing.transactions', params, hitSource: 'billing.write' }),

  invoice: (workspace: string, reference: string) =>
    alova.Get<Blob>(`/workspaces/${workspace}/billing/transactions/${reference}/invoice`, { name: 'billing.invoice', cacheFor: 0 }),
};
