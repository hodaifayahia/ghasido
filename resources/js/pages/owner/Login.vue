<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/owner/login';

/*
 * The owner console's own sign-in (spec 0007, D1): the platform owner's
 * account, created on the server with `php artisan owner:create`. Not the
 * app login: hotel teams and the Super Admin cannot sign in here. Same
 * card and field recipe as auth/Login.
 */
defineOptions({
    layout: {
        title: 'Owner console',
        description:
            'Sign in to manage the API keys, credit and prices behind GHASIDO’s AI features.',
    },
});

const fieldClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-sm text-[15px] shadow-none focus-visible:ring-3';
</script>

<template>
    <Head title="Owner sign in" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div class="grid gap-4">
            <div class="grid gap-1.5">
                <Label for="owner-email">E-mail</Label>
                <Input
                    id="owner-email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="you@example.com"
                    :class="fieldClass"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <Label for="owner-password">Password</Label>
                <PasswordInput
                    id="owner-password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                    :class="fieldClass"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 font-heading mt-2 h-11 w-full rounded-md text-sm font-semibold text-white active:scale-[.97]"
                :disabled="processing"
                data-test="owner-login-button"
            >
                <Spinner v-if="processing" />
                Sign in
            </Button>
        </div>
    </Form>

    <p
        class="border-line bg-app-alt text-body-sm text-ink-slate mt-4 rounded-lg border p-3"
    >
        Forgot the password? Reset it on the server:
        <code class="text-ink font-mono text-[12.5px] whitespace-nowrap"
            >php artisan owner:create</code
        >
        followed by your e-mail.
    </p>
</template>
