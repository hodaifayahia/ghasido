<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Sign in to Guesvia',
        description:
            'Super Admin, approved hotel managers and invited staff sign in here.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Sign in" />

    <div
        v-if="status"
        class="border-success/20 bg-success-tint text-body-sm text-success-text mb-4 rounded-lg border px-3 py-2.5 font-medium"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div class="grid gap-4">
            <div class="grid gap-1.5">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    type="text"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="manager.username"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <p class="text-body-sm text-ink-muted">
                    Hotel teams use their username. Platform owner accounts can
                    still use email if needed.
                </p>
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
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
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="text-body-sm text-ink-muted flex items-center gap-3"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 font-heading mt-2 h-11 w-full rounded-md text-sm font-semibold text-white active:scale-[.97]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Sign in
            </Button>
        </div>
    </Form>

    <div
        class="border-line bg-app-alt shadow-card mt-4 flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0">
            <p class="text-label text-brand-700">New hotel?</p>
            <p class="text-body-sm text-ink-slate mt-1">
                Choose a plan to start a hotel request. Managers invite their
                employees after approval.
            </p>
        </div>
        <Link
            href="/#pricing"
            class="focus-visible:border-brand-600 focus-visible:ring-brand-600/15 border-line bg-surface font-heading text-brand-700 shadow-card ease-brand hover:bg-brand-50 hover:shadow-hover inline-flex min-h-10 shrink-0 items-center justify-center rounded-md border px-4 text-sm font-semibold transition duration-150 hover:-translate-y-0.5 focus-visible:ring-3 focus-visible:outline-none"
        >
            Get started
        </Link>
    </div>
</template>
