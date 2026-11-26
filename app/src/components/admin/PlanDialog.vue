<script setup lang="ts">
import type { AdminPlan, ApiError, PlanInput } from '@/api';
import { Loader2 } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import { admin } from '@/api';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { notifyError, toast } from '@/lib/notify';

const props = defineProps<{ plan?: AdminPlan | null }>();
const emit = defineEmits<{ saved: [plan: AdminPlan] }>();

const open = defineModel<boolean>('open', { default: false });

/**
 * Limits are typed as text so an empty field can mean "no limit".
 */
const form = reactive({
  name: '',
  slug: '',
  description: '',
  price: '0',
  currency: 'NGN',
  interval: 'month' as AdminPlan['interval'],
  contacts_limit: '',
  emails_limit: '',
  sms_limit: '',
  sort: '0',
  is_active: true,
  is_default: false,
});

const slugTouched = ref(false);
const busy = ref(false);
const error = ref<ApiError | null>(null);

const limit = (value: number | null) => value === null ? '' : String(value);

watch(open, (value) => {
  if (!value) {
    return;
  }

  const plan = props.plan;

  error.value = null;
  slugTouched.value = !!plan;
  Object.assign(form, {
    name: plan?.name ?? '',
    slug: plan?.slug ?? '',
    description: plan?.description ?? '',
    price: String(plan?.price ?? 0),
    currency: plan?.currency ?? 'NGN',
    interval: plan?.interval ?? 'month',
    contacts_limit: limit(plan?.limits.contacts ?? null),
    emails_limit: limit(plan?.limits.emails ?? null),
    sms_limit: limit(plan?.limits.sms ?? null),
    sort: String(plan?.sort ?? 0),
    is_active: plan?.is_active ?? true,
    is_default: plan?.is_default ?? false,
  });
}, { immediate: true });

watch(() => form.name, (name) => {
  if (!slugTouched.value) {
    form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
});

const toLimit = (value: string) => value.trim() === '' ? null : Number(value);

async function save() {
  busy.value = true;
  error.value = null;

  const data: PlanInput = {
    name: form.name,
    slug: form.slug,
    description: form.description || null,
    price: Number(form.price),
    currency: form.currency,
    interval: form.interval,
    contacts_limit: toLimit(form.contacts_limit),
    emails_limit: toLimit(form.emails_limit),
    sms_limit: toLimit(form.sms_limit),
    sort: Number(form.sort),
    is_active: form.is_active,
    is_default: form.is_default,
  };

  try {
    const result = props.plan ? await admin.updatePlan(props.plan.id, data) : await admin.createPlan(data);

    toast.success(result.message);
    emit('saved', result.data);
    open.value = false;
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-2xl">
      <form class="contents" @submit.prevent="save">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ plan ? `Edit ${plan.name}` : 'New plan' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Limits apply to current subscribers straight away. A new price applies from their next payment.
          </DialogDescription>
        </DialogHeader>
        <DialogBody class="grid gap-4 sm:grid-cols-2">
          <FormField id="plan-name" label="Name" :error="error?.field('name')">
            <Input id="plan-name" v-model="form.name" placeholder="Growth" />
          </FormField>
          <FormField id="plan-slug" label="Slug" :error="error?.field('slug')">
            <Input id="plan-slug" v-model="form.slug" placeholder="growth" @input="slugTouched = true" />
          </FormField>
          <FormField id="plan-description" label="Description" hint="Optional. Shown to customers choosing a plan." :error="error?.field('description')" class="sm:col-span-2">
            <Textarea id="plan-description" v-model="form.description" rows="2" />
          </FormField>
          <FormField id="plan-price" label="Price" :error="error?.field('price')">
            <Input id="plan-price" v-model="form.price" type="number" min="0" step="0.01" class="tabular-nums" />
          </FormField>
          <div class="grid grid-cols-2 gap-4">
            <FormField id="plan-currency" label="Currency" :error="error?.field('currency')">
              <Input id="plan-currency" v-model="form.currency" maxlength="3" class="uppercase" />
            </FormField>
            <FormField id="plan-interval" label="Billed" :error="error?.field('interval')">
              <NativeSelect id="plan-interval" v-model="form.interval">
                <option value="month">
                  Monthly
                </option>
                <option value="year">
                  Yearly
                </option>
              </NativeSelect>
            </FormField>
          </div>
          <FormField id="plan-contacts" label="Contact limit" hint="Empty means no limit." :error="error?.field('contacts_limit')">
            <Input id="plan-contacts" v-model="form.contacts_limit" type="number" min="0" class="tabular-nums" />
          </FormField>
          <FormField id="plan-emails" label="Emails per month" hint="Empty means no limit." :error="error?.field('emails_limit')">
            <Input id="plan-emails" v-model="form.emails_limit" type="number" min="0" class="tabular-nums" />
          </FormField>
          <FormField id="plan-sms" label="SMS per month" hint="Empty means no limit." :error="error?.field('sms_limit')">
            <Input id="plan-sms" v-model="form.sms_limit" type="number" min="0" class="tabular-nums" />
          </FormField>
          <FormField id="plan-sort" label="Order" hint="Lower numbers are listed first." :error="error?.field('sort')">
            <Input id="plan-sort" v-model="form.sort" type="number" min="0" max="1000" class="tabular-nums" />
          </FormField>
          <div class="grid gap-3 sm:col-span-2">
            <div class="flex items-center gap-3">
              <Switch id="plan-active" v-model="form.is_active" />
              <Label for="plan-active">Customers can choose this plan</Label>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="plan-default" v-model="form.is_default" />
              <Label for="plan-default">Default plan for workspaces without a subscription</Label>
            </div>
          </div>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="secondary" :disabled="busy" @click="open = false">
            Cancel
          </Button>
          <Button type="submit" :disabled="busy || !form.name.trim() || !form.slug.trim()">
            <Loader2 v-if="busy" class="animate-spin" />
            {{ plan ? 'Save plan' : 'Create plan' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
