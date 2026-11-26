<script setup lang="ts">
import { Check, LoaderCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiError } from '@/api';
import AuthShell from '@/components/app/AuthShell.vue';
import FormField from '@/components/app/FormField.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBranding } from '@/composables/useBranding';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

const route = useRoute();
const router = useRouter();
const session = useSession();
const heading = useBranding().message('sign_up');
const workspace = useWorkspace();

const form = reactive({ firstname: '', lastname: '', business: '', email: typeof route.query.email === 'string' ? route.query.email : '', phone: '', password: '' });
const error = ref<ApiError | null>(null);
const loading = ref(false);

const perks = [
  "Connect Africa's Talking, Termii, Twilio, SendPulse, Mailgun or any SMTP server",
  'Import contacts from CSV, with +234 numbers cleaned for you',
  'Unsubscribes and STOP replies respected on every send',
  'Start on the free plan, no card needed',
];

async function submit() {
  loading.value = true;
  error.value = null;

  try {
    await session.register({
      firstname: form.firstname,
      lastname: form.lastname,
      email: form.email,
      password: form.password,
      password_confirmation: form.password,
      ...(form.phone ? { phone: form.phone } : {}),
    });

    await workspace.load();

    if (form.business.trim()) {
      await workspace.rename(form.business.trim());
    }

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
      <div class="grid max-w-[42ch] gap-4">
        <h2 class="text-[26px] font-semibold">
          {{ heading }}
        </h2>
        <ul class="m-0 grid list-none gap-2.5 p-0">
          <li v-for="perk in perks" :key="perk" class="flex items-start gap-2">
            <Check class="mt-0.5 size-4 shrink-0" />
            <span>{{ perk }}</span>
          </li>
        </ul>
      </div>
    </template>

    <form class="grid gap-4" novalidate @submit.prevent="submit">
      <div class="grid gap-1.5">
        <h2 class="text-[22px] font-semibold">
          Create your account
        </h2>
        <p class="text-muted-foreground">
          We'll set up your first workspace for you.
        </p>
      </div>

      <Alert v-if="error && !Object.keys(error.errors).length" variant="destructive">
        <AlertDescription>{{ error.message }}</AlertDescription>
      </Alert>

      <div class="grid gap-3 sm:grid-cols-2">
        <FormField id="firstname" label="First name" :error="error?.field('firstname') ?? error?.field('name')">
          <Input id="firstname" v-model="form.firstname" autocomplete="given-name" required />
        </FormField>
        <FormField id="lastname" label="Last name" :error="error?.field('lastname')">
          <Input id="lastname" v-model="form.lastname" autocomplete="family-name" />
        </FormField>
      </div>

      <FormField id="business" label="Business name" hint="Becomes your workspace name. You can change it later.">
        <Input id="business" v-model="form.business" autocomplete="organization" />
      </FormField>

      <FormField id="email" label="Work email" :error="error?.field('email')">
        <Input id="email" v-model="form.email" type="email" autocomplete="email" required />
      </FormField>

      <FormField id="phone" label="Phone" hint="Optional" :error="error?.field('phone')">
        <Input id="phone" v-model="form.phone" type="tel" autocomplete="tel" placeholder="+234 803 555 0142" />
      </FormField>

      <FormField id="password" label="Password" hint="At least 8 characters." :error="error?.field('password')">
        <Input id="password" v-model="form.password" type="password" autocomplete="new-password" required />
      </FormField>

      <Button type="submit" class="w-full" :disabled="loading">
        <LoaderCircle v-if="loading" class="animate-spin" />
        Create account
      </Button>

      <p class="text-center text-[13px] text-muted-foreground">
        Already have an account?
        <RouterLink :to="{ name: 'login', query: route.query }" class="font-medium text-foreground">
          Sign in
        </RouterLink>
      </p>
    </form>
  </AuthShell>
</template>
