<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/sso';
import type { SsoProvider } from '@/types/auth';

const { providers } = defineProps<{
    providers: SsoProvider[];
}>();

/*
  A full page load, not an Inertia visit: the destination is the identity
  provider, not this app, so there is no Inertia response to swap in.
*/
</script>

<template>
    <div class="grid gap-3">
        <Button
            v-for="provider in providers"
            :key="provider.name"
            as="a"
            variant="outline"
            class="w-full"
            :href="redirect.url(provider.name)"
            :data-test="`sso-${provider.name}`"
        >
            Continue with {{ provider.label }}
        </Button>
    </div>
</template>
