import type { RouteRecordRaw } from 'vue-router';

declare module 'vue-router' {
  interface RouteMeta {
    guest?: boolean;
    public?: boolean;
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
      { path: 'campaigns', name: 'campaigns', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'Campaigns' } },
      { path: 'campaigns/new', name: 'campaigns.new', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'New campaign' } },
      { path: 'contacts', name: 'contacts', component: () => import('@/pages/ContactsPage.vue'), meta: { title: 'Contacts' } },
      { path: 'contacts/import', name: 'contacts.import', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'Import contacts' } },
      { path: 'templates', name: 'templates', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'Templates' } },
      { path: 'reports', name: 'reports', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'Reports' } },
      { path: 'providers', name: 'providers', component: () => import('@/pages/ProvidersPage.vue'), meta: { title: 'Providers' } },
      { path: 'billing', name: 'billing', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'Billing' } },
      { path: 'settings', name: 'settings', component: () => import('@/pages/ComingSoonPage.vue'), meta: { title: 'Workspace settings' } },
    ],
  },
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/NotFoundPage.vue'),
    meta: { public: true },
  },
];

export default routes;
