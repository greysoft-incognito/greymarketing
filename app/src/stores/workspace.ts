import type { Billing, Workspace } from '@/api';
import { useStorage } from '@vueuse/core';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { workspaces as api } from '@/api';

/**
 * The user's workspaces and the one they are working in.
 */
export const useWorkspace = defineStore('workspace', () => {
  const list = ref<Workspace[]>([]);
  const currentId = useStorage<string | null>('gm.workspace', null);
  const billing = ref<Billing | null>(null);

  const current = computed(() => list.value.find(workspace => workspace.id === currentId.value) ?? list.value[0] ?? null);
  const canManage = computed(() => ['owner', 'admin'].includes(current.value?.role ?? ''));

  async function load() {
    list.value = (await api.list()).data;

    if (!list.value.some(workspace => workspace.id === currentId.value)) {
      currentId.value = list.value[0]?.id ?? null;
    }

    await loadBilling();
  }

  async function loadBilling() {
    billing.value = current.value ? (await api.billing(current.value.id)).data : null;
  }

  async function select(id: string) {
    currentId.value = id;

    await loadBilling();
  }

  async function rename(name: string) {
    if (!current.value) {
      return;
    }

    const updated = (await api.update(current.value.id, { name })).data;

    list.value = list.value.map(workspace => workspace.id === updated.id ? { ...workspace, ...updated } : workspace);
  }

  function reset() {
    list.value = [];
    billing.value = null;
  }

  return { list, currentId, current, billing, canManage, load, loadBilling, select, rename, reset };
});
