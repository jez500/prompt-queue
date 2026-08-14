<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import SsoLoginButtons from '@/components/auth/SsoLoginButtons.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { SsoProvider } from '@/types/auth';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

const { ssoProviders = [], showPasswordLogin = true } = defineProps<{
    status?: string;
    canResetPassword: boolean;
    ssoProviders?: SsoProvider[];
    ssoError?: string;
    showPasswordLogin?: boolean;
}>();

/* The description in defineOptions is static, and it promises a password field
   that is not there when single sign-on is the only way in. */
if (!showPasswordLogin) {
    setLayoutProps({
        description: 'Sign in with your identity provider to continue',
    });
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-[#1F7A55] dark:text-[#6FCFA1]"
    >
        {{ status }}
    </div>

    <div
        v-if="ssoError"
        class="mb-4 text-center text-sm font-medium text-destructive"
        data-test="sso-error"
    >
        {{ ssoError }}
    </div>

    <!-- No bottom margin: AuthSimpleLayout already spaces slot children. -->
    <div v-if="ssoProviders.length" class="flex flex-col gap-6">
        <SsoLoginButtons :providers="ssoProviders" />

        <!-- The divider introduces the form, so it goes when the form goes. -->
        <div v-if="showPasswordLogin" class="relative text-center text-sm">
            <span
                class="absolute inset-0 top-1/2 border-t border-border"
                aria-hidden="true"
            />
            <span class="relative bg-background px-2 text-muted-foreground">
                or continue with email
            </span>
        </div>
    </div>

    <Form
        v-if="showPasswordLogin"
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>
    </Form>
</template>
