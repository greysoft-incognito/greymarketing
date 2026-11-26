import type { RouteRecordRaw } from 'vue-router';

declare module 'vue-router' {
  interface RouteMeta {
    guest?: boolean;
    public?: boolean;
    staff?: boolean;
    title?: string;
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { guest: true },
    children: [
      { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue'), meta: { guest: true, title: 'Sign in' } },
      { path: 'signup', name: 'signup', component: () => import('@/pages/auth/SignupPage.vue'), meta: { guest: true, title: 'Create account' } },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue'), meta: { title: 'Dashboard' } },
      { path: 'campaigns', name: 'campaigns', component: () => import('@/pages/CampaignsPage.vue'), meta: { title: 'Campaigns' } },
      { path: 'campaigns/new', name: 'campaigns.new', component: () => import('@/pages/CampaignBuilderPage.vue'), meta: { title: 'New campaign' } },
      { path: 'campaigns/:campaign/edit', name: 'campaigns.edit', component: () => import('@/pages/CampaignBuilderPage.vue'), meta: { title: 'Edit campaign' } },
      { path: 'campaigns/:campaign', name: 'campaigns.report', component: () => import('@/pages/CampaignReportPage.vue'), meta: { title: 'Campaign report' } },
      { path: 'contacts', name: 'contacts', component: () => import('@/pages/ContactsPage.vue'), meta: { title: 'Contacts' } },
      { path: 'contacts/import', name: 'contacts.import', component: () => import('@/pages/ImportPage.vue'), meta: { title: 'Import contacts' } },
      { path: 'templates', name: 'templates', component: () => import('@/pages/TemplatesPage.vue'), meta: { title: 'Templates' } },
      { path: 'templates/new', name: 'templates.new', component: () => import('@/pages/TemplateEditorPage.vue'), meta: { title: 'New template' } },
      { path: 'templates/:template', name: 'templates.edit', component: () => import('@/pages/TemplateEditorPage.vue'), meta: { title: 'Edit template' } },
      { path: 'reports', name: 'reports', component: () => import('@/pages/ReportsPage.vue'), meta: { title: 'Reports' } },
      { path: 'providers', name: 'providers', component: () => import('@/pages/ProvidersPage.vue'), meta: { title: 'Providers' } },
      { path: 'billing', name: 'billing', component: () => import('@/pages/BillingPage.vue'), meta: { title: 'Billing' } },
      { path: 'team', name: 'team', component: () => import('@/pages/TeamPage.vue'), meta: { title: 'Team' } },
      { path: 'settings', name: 'settings', component: () => import('@/pages/WorkspaceSettingsPage.vue'), meta: { title: 'Workspace settings' } },
    ],
  },
  {
    path: '/invites/:token',
    name: 'invite',
    component: () => import('@/pages/InvitePage.vue'),
    meta: { public: true, title: 'Join a workspace' },
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { staff: true },
    children: [
      { path: '', name: 'admin', component: () => import('@/pages/admin/AdminOverviewPage.vue'), meta: { title: 'Overview' } },
      { path: 'customers', name: 'admin.customers', component: () => import('@/pages/admin/AdminCustomersPage.vue'), meta: { title: 'Customers' } },
      { path: 'plans', name: 'admin.plans', component: () => import('@/pages/admin/AdminPlansPage.vue'), meta: { title: 'Plans' } },
      { path: 'settings', name: 'admin.settings', component: () => import('@/pages/admin/AdminSettingsPage.vue'), meta: { title: 'System settings' } },
      { path: 'usage', name: 'admin.usage', component: () => import('@/pages/admin/AdminUsagePage.vue'), meta: { title: 'Usage' } },
    ],
  },
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/NotFoundPage.vue'),
    meta: { public: true },
  },
];

export default routes;
