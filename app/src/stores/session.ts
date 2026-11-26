import type { User } from '@/api';
import { useStorage } from '@vueuse/core';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { auth, forgetAll } from '@/api';

/**
 * The signed-in user and their API token.
 */
export const useSession = defineStore('session', () => {
  const token = useStorage<string | null>('gm.token', null);
  const user = ref<User | null>(null);

  const signedIn = computed(() => !!token.value);
  const name = computed(() => [user.value?.firstname, user.value?.lastname].filter(Boolean).join(' '));
  const isStaff = computed(() => (user.value?.roles ?? []).some(role => ['admin', 'manager', 'super-admin'].includes(role)));

  function start(newToken: string, newUser: User) {
    token.value = newToken;
    user.value = newUser;
  }

  async function login(email: string, password: string, remember = true) {
    const response = await auth.login({ email, password, remember });

    start(response.token, response.data);
  }

  async function register(data: Parameters<typeof auth.register>[0]) {
    const response = await auth.register(data);

    start(response.token, response.data);
  }

  async function load() {
    if (token.value && !user.value) {
      user.value = (await auth.me()).data;
    }
  }

  async function logout() {
    await auth.logout().catch(() => null);

    expire();
  }

  /**
   * Forget the session locally, e.g. when the API says the token is no longer valid.
   * Cached responses belong to this user, so they go too.
   */
  function expire() {
    token.value = null;
    user.value = null;
    forgetAll();
  }

  return { token, user, signedIn, name, isStaff, login, register, load, logout, expire };
});
