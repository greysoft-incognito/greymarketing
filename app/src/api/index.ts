import type {
  AuthResponse,
  Billing,
  Campaign,
  Channel,
  ConnectionInput,
  Contact,
  ContactImport,
  ContactInput,
  ContactList,
  Envelope,
  ImportUpload,
  Paginated,
  Provider,
  ProviderConnection,
  Template,
  TemplateInput,
  User,
  Workspace,
  WorkspaceStats,
} from './types';
import { alova } from './client';

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

export const workspaces = {
  list: () => alova.Get<Paginated<Workspace>>('/workspaces', { name: 'workspaces.list', params: { limit: 100 }, hitSource: 'workspaces.write' }),

  create: (name: string) => alova.Post<Envelope<Workspace>>('/workspaces', { name }, { name: 'workspaces.write' }),

  update: (id: string, data: Partial<Pick<Workspace, 'name' | 'default_country'>>) =>
    alova.Put<Envelope<Workspace>>(`/workspaces/${id}`, data, { name: 'workspaces.write' }),

  stats: (id: string, days = 14) => alova.Get<Envelope<WorkspaceStats>>(`/workspaces/${id}/stats`, { name: 'stats', params: { days }, hitSource: ['contacts.write', 'imports.write'] }),

  billing: (id: string) => alova.Get<Envelope<Billing>>(`/workspaces/${id}/billing`, { name: 'billing', hitSource: ['contacts.write', 'imports.write'] }),

  campaigns: (id: string, params: { status?: string; limit?: number; page?: number } = {}) =>
    alova.Get<Paginated<Campaign>>(`/workspaces/${id}/campaigns`, { name: 'campaigns.list', params }),

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
