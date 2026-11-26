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
  User,
  Workspace,
  WorkspaceStats,
} from './types';
import { alova } from './client';

export { ApiError } from './client';
export type * from './types';

export const auth = {
  login: (data: { email: string; password: string; remember?: boolean }) =>
    alova.Post<AuthResponse>('/auth/login', data),

  register: (data: { firstname: string; lastname: string; email: string; phone?: string; password: string; password_confirmation: string }) =>
    alova.Post<AuthResponse>('/auth/register', data),

  me: () => alova.Get<Envelope<User>>('/account'),

  logout: () => alova.Post('/account/logout'),
};

export const providers = {
  catalog: () => alova.Get<Envelope<Provider[]>>('/providers', { cacheFor: 10 * 60 * 1000 }),
};

export const workspaces = {
  list: () => alova.Get<Paginated<Workspace>>('/workspaces', { params: { limit: 100 } }),

  create: (name: string) => alova.Post<Envelope<Workspace>>('/workspaces', { name }),

  update: (id: string, data: Partial<Pick<Workspace, 'name' | 'default_country'>>) =>
    alova.Put<Envelope<Workspace>>(`/workspaces/${id}`, data),

  stats: (id: string, days = 14) => alova.Get<Envelope<WorkspaceStats>>(`/workspaces/${id}/stats`, { params: { days } }),

  billing: (id: string) => alova.Get<Envelope<Billing>>(`/workspaces/${id}/billing`),

  campaigns: (id: string, params: { status?: string; limit?: number; page?: number } = {}) =>
    alova.Get<Paginated<Campaign>>(`/workspaces/${id}/campaigns`, { params }),

  connections: (id: string) => alova.Get<Paginated<ProviderConnection>>(`/workspaces/${id}/connections`, { params: { limit: 100 } }),
};

/**
 * Provider connections in a workspace.
 */
export const connections = {
  create: (workspace: string, data: ConnectionInput) =>
    alova.Post<Envelope<ProviderConnection>>(`/workspaces/${workspace}/connections`, data),

  update: (workspace: string, id: string, data: ConnectionInput) =>
    alova.Put<Envelope<ProviderConnection>>(`/workspaces/${workspace}/connections/${id}`, data),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/connections/${id}`),

  test: (workspace: string, id: string) =>
    alova.Post<Envelope<ProviderConnection>>(`/workspaces/${workspace}/connections/${id}/test`),

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
    alova.Get<Paginated<Contact>>(`/workspaces/${workspace}/contacts`, { params }),

  create: (workspace: string, data: ContactInput) =>
    alova.Post<Envelope<Contact>>(`/workspaces/${workspace}/contacts`, data),

  update: (workspace: string, id: string, data: ContactInput) =>
    alova.Put<Envelope<Contact>>(`/workspaces/${workspace}/contacts/${id}`, data),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/contacts/${id}`),
};

export const lists = {
  list: (workspace: string) => alova.Get<Paginated<ContactList>>(`/workspaces/${workspace}/lists`, { params: { limit: 100 } }),

  create: (workspace: string, data: { name: string; description?: string | null }) =>
    alova.Post<Envelope<ContactList>>(`/workspaces/${workspace}/lists`, data),

  update: (workspace: string, id: string, data: { name?: string; description?: string | null }) =>
    alova.Put<Envelope<ContactList>>(`/workspaces/${workspace}/lists/${id}`, data),

  remove: (workspace: string, id: string) => alova.Delete(`/workspaces/${workspace}/lists/${id}`),

  attach: (workspace: string, id: string, contactIds: string[]) =>
    alova.Post<Envelope<ContactList>>(`/workspaces/${workspace}/lists/${id}/contacts`, { contacts: contactIds }),

  detach: (workspace: string, id: string, contactIds: string[]) =>
    alova.Delete<Envelope<ContactList>>(`/workspaces/${workspace}/lists/${id}/contacts`, { contacts: contactIds }),
};

export const imports = {
  list: (workspace: string, page = 1, limit = 10) =>
    alova.Get<Paginated<ContactImport>>(`/workspaces/${workspace}/imports`, { params: { page, limit } }),

  upload: (workspace: string, file: File) => {
    const form = new FormData();

    form.append('file', file);

    return alova.Post<ImportUpload>(`/workspaces/${workspace}/imports`, form);
  },

  show: (workspace: string, id: string) => alova.Get<Envelope<ContactImport>>(`/workspaces/${workspace}/imports/${id}`),

  start: (workspace: string, id: string, data: { mapping: Record<string, string | null>; list?: string | null }) =>
    alova.Post<Envelope<ContactImport>>(`/workspaces/${workspace}/imports/${id}/start`, data),
};
