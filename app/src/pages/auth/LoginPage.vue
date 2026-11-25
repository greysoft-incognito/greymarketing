<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiError } from '@/api';
import AuthShell from '@/components/app/AuthShell.vue';
import FormField from '@/components/app/FormField.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useSession } from '@/stores/session';

const route = useRoute();
const router = useRouter();
const session = useSession();

const form = reactive({ email: '', password: '' });
const error = ref<ApiError | null>(null);
const loading = ref(false);

const feed = [
  ['16:02:11', 'DELIVRD', '+234 803 ••• 4410', "via Africa's Talking"],
  ['16:02:11', 'DELIVRD', '+234 812 ••• 9031', "via Africa's Talking"],
  ['16:02:12', 'SENT   ', 'ada•••@gmail.com', 'via SendPulse'],
  ['16:02:13', 'STOP   ', '+234 706 ••• 2287', 'unsubscribed'],
];

async function submit() {
  loading.value = true;
  error.value = null;

  try {
    await session.login(form.email, form.password);
    await router.replace(typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'dashboard' });
  } catch (e) {
    error.value = e instanceof ApiError ? e : new ApiError('Something went wrong. Please try again.', 0);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <AuthShell>
    <template #aside>
      <div class="grid gap-6">
        <blockquote class="m-0 max-w-[46ch] text-lg leading-normal">
          Plug in the SMS and email providers you already pay for. Send campaigns, see every delivery receipt, keep one contact list.
        </blockquote>
        <div class="grid gap-2.5 font-mono text-xs opacity-85" aria-hidden="true">
          <div v-for="line in feed" :key="line[2]" class="flex gap-2.5 whitespace-pre">
            <span class="opacity-60">{{ line[0] }}</span><span>{{ line[1] }}</span><span>{{ line[2] }}</span><span class="opacity-60">{{ line[3] }}</span>
          </div>
        </div>
      </div>
    </template>

    <form class="grid gap-4" novalidate @submit.prevent="submit">
      <div class="grid gap-1.5 text-center">
        <h2 class="text-[22px] font-semibold">
          Welcome back
        </h2>
        <p class="text-muted-foreground">
          Sign in to your workspace
        </p>
      </div>

      <Alert v-if="error && !error.field('email')" variant="destructive">
        <AlertDescription>{{ error.message }}</AlertDescription>
      </Alert>

      <FormField id="email" label="Email" :error="error?.field('email')">
        <Input id="email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="!!error?.field('email')" />
      </FormField>

      <FormField id="password" label="Password" :error="error?.field('password')">
        <Input id="password" v-model="form.password" type="password" autocomplete="current-password" required />
      </FormField>

      <Button type="submit" class="w-full" :disabled="loading">
        <LoaderCircle v-if="loading" class="animate-spin" />
        Sign in
      </Button>

      <p class="text-center text-[13px] text-muted-foreground">
        New here?
        <RouterLink :to="{ name: 'signup' }" class="font-medium text-foreground">
          Create an account
        </RouterLink>
      </p>
    </form>
  </AuthShell>
</template>
