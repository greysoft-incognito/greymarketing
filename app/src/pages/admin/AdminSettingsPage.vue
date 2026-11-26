<script setup lang="ts">
import { invalidateCache } from 'alova';
import { useRequest } from 'alova/client';
import { ImageUp, Loader2, RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { admin, branding } from '@/api';
import PageHeader from '@/components/app/PageHeader.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import defaultImage from '@/assets/auth-panel.svg';
import { notifyError, toast } from '@/lib/notify';

const MAX_BYTES = 5 * 1024 * 1024;

const brandingMethod = () => branding.show();
const { data, send } = useRequest(brandingMethod);

const uploaded = computed(() => data.value?.data.auth_image ?? null);
const picked = ref<File | null>(null);
const pickedUrl = ref<string | null>(null);
const busy = ref<'upload' | 'reset' | null>(null);
const input = ref<HTMLInputElement | null>(null);

const preview = computed(() => pickedUrl.value ?? uploaded.value ?? defaultImage);

function clearPicked() {
  if (pickedUrl.value) {
    URL.revokeObjectURL(pickedUrl.value);
  }

  picked.value = null;
  pickedUrl.value = null;

  if (input.value) {
    input.value.value = '';
  }
}

function pick(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];

  if (!file) {
    return;
  }

  if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > MAX_BYTES) {
    toast.error('Choose a JPG, PNG or WebP image up to 5 MB.');
    clearPicked();

    return;
  }

  clearPicked();
  picked.value = file;
  pickedUrl.value = URL.createObjectURL(file);
}

async function reload() {
  await invalidateCache(brandingMethod());
  await send();
}

async function save() {
  busy.value = 'upload';

  try {
    await admin.uploadAuthImage(picked.value!);
    clearPicked();
    await send();
    toast.success('Sign-in image updated.');
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;
  }
}

async function reset() {
  busy.value = 'reset';

  try {
    toast.success((await admin.resetAuthImage()).message);
    await send();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;
  }
}

onBeforeUnmount(clearPicked);
</script>

<template>
  <PageHeader title="System settings" description="Settings that apply to every customer.">
    <template #actions>
      <RefreshButton :refresh="reload" />
    </template>
  </PageHeader>

  <div class="grid max-w-[880px] gap-5">
    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-center gap-2">
          <CardTitle>Sign-in image</CardTitle>
          <Badge v-if="picked" variant="warning">
            Not saved
          </Badge>
          <Badge v-else-if="uploaded" variant="brand">
            Custom
          </Badge>
          <Badge v-else>
            Default
          </Badge>
        </div>
        <CardDescription>Shown beside the sign-in, sign-up and invite forms on larger screens. A dark tint keeps the text readable.</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-4 sm:grid-cols-[220px_1fr] sm:items-start">
        <div class="relative aspect-[3/4] overflow-hidden rounded-lg bg-ink text-ink-foreground">
          <img :src="preview" alt="Sign-in image preview" class="absolute inset-0 size-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/55 to-ink/10" />
          <p class="relative m-0 p-4 pt-[45%] text-xs leading-normal">
            Plug in the SMS and email providers you already pay for.
          </p>
        </div>
        <div class="grid gap-3 text-sm">
          <p class="m-0 text-muted-foreground">
            Use a tall JPG, PNG or WebP image, at least 1200 × 1600 pixels and up to 5 MB. It is cropped to fill the panel, so keep the main subject near the centre.
          </p>
          <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick">
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" :disabled="!!busy" @click="input?.click()">
              <ImageUp />
              {{ uploaded || picked ? 'Choose another image' : 'Choose image' }}
            </Button>
            <Button v-if="uploaded && !picked" variant="ghost" :disabled="!!busy" @click="reset">
              <Loader2 v-if="busy === 'reset'" class="animate-spin" />
              <RotateCcw v-else />
              Use the default
            </Button>
          </div>
          <span v-if="picked" class="truncate text-xs text-muted-foreground">{{ picked.name }}</span>
        </div>
      </CardContent>
      <CardFooter v-if="picked" class="gap-2">
        <Button :disabled="!!busy" @click="save">
          <Loader2 v-if="busy === 'upload'" class="animate-spin" />
          Save image
        </Button>
        <Button variant="secondary" :disabled="!!busy" @click="clearPicked">
          Cancel
        </Button>
      </CardFooter>
    </Card>
  </div>
</template>
