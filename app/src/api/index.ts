import type { AuthResponse, Billing, Campaign, Envelope, Paginated, ProviderConnection, User, Workspace, WorkspaceStats } from './types';
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

export const workspaces = {
  list: () => alova.Get<Paginated<Workspace>>('/workspaces', { params: { limit: 100 } }),

  create: (name: string) => alova.Post<Envelope<Workspace>>('/workspaces', { name }),

  update: (id: string, data: Partial<Pick<Workspace, 'name' | 'default_country'>>) =>
    alova.Put<Envelope<Workspace>>(`/workspaces/${id}`, data),

  stats: (id: string, days = 14) => alova.Get<Envelope<WorkspaceStats>>(`/workspaces/${id}/stats`, { params: { days } }),

  billing: (id: string) => alova.Get<Envelope<Billing>>(`/workspaces/${id}/billing`),

  campaigns: (id: string, params: { status?: string; limit?: number; page?: number } = {}) =>
    alova.Get<Paginated<Campaign>>(`/workspaces/${id}/campaigns`, { params }),

  connections: (id: string) => alova.Get<Paginated<ProviderConnection>>(`/workspaces/${id}/connections`),
};
