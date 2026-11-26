<script setup lang="ts">
import { useRequest } from 'alova/client';
import { Ban, CircleAlert, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { invites } from '@/api';
import AuthShell from '@/components/app/AuthShell.vue';
import { Button } from '@/components/ui/button';
import { useBranding } from '@/composables/useBranding';
import { notifyError, toast } from '@/lib/notify';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

const route = useRoute();
const router = useRouter();
const session = useSession();
const workspace = useWorkspace();
const panelMessage = useBranding().message('invite');

const token = String(route.params.token);
const { data: invite, loading, error } = useRequest(() => invites.show(token));

const roles = { admin: 'an Admin', member: 'a Member' } as const;

const invitation = computed(() => invite.value?.data ?? null);
const back = computed(() => ({ redirect: route.fullPath, email: invitation.value?.email ?? '' }));

const state = computed(() => {
  if (loading.value) {
    return 'loading';
  }

  if (error.value || !invitation.value) {
    return 'gone';
  }

  if (!session.signedIn) {
    return 'out';
  }

  return session.user?.email?.toLowerCase() === invitation.value.email ? 'in' : 'wrong';
});

const initials = computed(() => (invitation.value?.workspace?.name ?? '')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map(word => word[0]?.toUpperCase())
  .join(''));

const busy = ref<'accept' | 'decline' | null>(null);

async function accept() {
  busy.value = 'accept';

  try {
    const joined = (await invites.accept(token)).data;

    await workspace.load();
    await workspace.select(joined.id);
    toast.success(`You joined ${joined.name}.`);
    await router.replace({ name: 'dashboard' });
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;
  }
}

async function decline() {
  busy.value = 'decline';

  try {
    await invites.decline(token);
    toast.success('Invite declined.');
    await router.replace({ name: 'dashboard' });
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;
  }
}
</script>

<template>
  <AuthShell>
    <template #aside>
      <p class="m-0 max-w-[46ch] text-lg leading-normal">
        {{ panelMessage }}
      </p>
    </template>

    <div v-if="state === 'loading'" class="grid place-items-center py-10">
      <Loader2 class="size-5 animate-spin text-muted-foreground" />
    </div>

    <template v-else-if="state === 'gone'">
      <div class="grid gap-2 text-center">
        <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <Ban class="size-5" />
        </span>
        <h1 class="text-[22px] font-semibold">
          This invite no longer works
        </h1>
        <p class="text-muted-foreground">
          It expired or was revoked. Ask whoever invited you to send a new one.
        </p>
      </div>
      <Button variant="secondary" as-child>
        <RouterLink :to="{ name: 'dashboard' }">
          Go to my workspace
        </RouterLink>
      </Button>
    </template>

    <template v-else-if="state === 'wrong'">
      <div class="grid gap-2 text-center">
        <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <CircleAlert class="size-5" />
        </span>
        <h1 class="text-[22px] font-semibold">
          This invite is for someone else
        </h1>
        <p class="text-muted-foreground">
          It was sent to <b class="text-foreground">{{ invitation!.email }}</b>, but you're signed in as <b class="text-foreground">{{ session.user?.email }}</b>.
        </p>
      </div>
      <Button @click="session.logout()">
        Sign out and switch account
      </Button>
    </template>

    <template v-else>
      <div class="grid gap-2 text-center">
        <span class="mx-auto flex size-12 items-center justify-center rounded-lg bg-primary text-base font-semibold text-primary-foreground">
          {{ initials }}
        </span>
        <h1 class="text-[22px] font-semibold">
          Join {{ invitation!.workspace?.name }}
        </h1>
        <p class="text-muted-foreground">
          {{ invitation!.invited_by?.name ?? 'A teammate' }} invited <b class="text-foreground">{{ invitation!.email }}</b> to join as {{ roles[invitation!.role] }}.
        </p>
      </div>

      <template v-if="state === 'in'">
        <Button :disabled="!!busy" @click="accept">
          <Loader2 v-if="busy === 'accept'" class="animate-spin" />
          Accept and open {{ invitation!.workspace?.name }}
        </Button>
        <Button variant="secondary" :disabled="!!busy" @click="decline">
          <Loader2 v-if="busy === 'decline'" class="animate-spin" />
          Decline
        </Button>
        <p class="text-center text-xs text-muted-foreground">
          Signed in as {{ session.user?.email }}
        </p>
      </template>

      <template v-else>
        <Button as-child>
          <RouterLink :to="{ name: 'signup', query: back }">
            Create an account
          </RouterLink>
        </Button>
        <Button variant="secondary" as-child>
          <RouterLink :to="{ name: 'login', query: back }">
            I already have an account
          </RouterLink>
        </Button>
        <p class="text-center text-xs text-muted-foreground">
          Use {{ invitation!.email }}. We bring you back here after you sign in.
        </p>
      </template>
    </template>
  </AuthShell>
</template>
