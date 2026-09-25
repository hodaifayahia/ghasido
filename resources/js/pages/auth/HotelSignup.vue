<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import HotelSignupController from '@/actions/App/Http/Controllers/HotelSignupController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Register your hotel',
        description:
            'Share the hotel details and create the first manager login for Super Admin approval.',
    },
});
</script>

<template>
    <Head title="Register your hotel" />

    <Form
        v-bind="HotelSignupController.store.form()"
        class="mt-4 grid gap-3"
        v-slot="{ errors, processing }"
    >
        <div class="grid grid-cols-2 gap-x-3 gap-y-2.5 sm:gap-3">
            <div class="col-span-2 grid gap-1.5">
                <Label for="hotel-name">Hotel name</Label>
                <Input
                    id="hotel-name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="organization"
                    placeholder="Blue Coast Hotel"
                    data-test="hotel-signup-name-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="hotel-city">City</Label>
                <Input
                    id="hotel-city"
                    name="city"
                    type="text"
                    required
                    autocomplete="address-level2"
                    placeholder="Oran"
                    data-test="hotel-signup-city-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.city" />
            </div>

            <div class="grid gap-1.5">
                <Label for="manager-name">Manager name</Label>
                <Input
                    id="manager-name"
                    name="manager_name"
                    type="text"
                    required
                    autocomplete="name"
                    placeholder="Nassim Benali"
                    data-test="hotel-signup-manager-name-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.manager_name" />
            </div>

            <div class="col-span-2 grid gap-1.5">
                <Label for="manager-email">Manager email</Label>
                <Input
                    id="manager-email"
                    name="manager_email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="manager@hotel.com"
                    data-test="hotel-signup-manager-email-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.manager_email" />
            </div>

            <div class="grid gap-1.5">
                <Label for="manager-username">Manager username</Label>
                <Input
                    id="manager-username"
                    name="manager_username"
                    type="text"
                    required
                    autocomplete="username"
                    placeholder="blue.coast.manager"
                    data-test="hotel-signup-manager-username-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.manager_username" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Create a password"
                    data-test="hotel-signup-password-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="col-span-2 grid gap-1.5">
                <Label for="password-confirmation">Confirm password</Label>
                <PasswordInput
                    id="password-confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Repeat the password"
                    data-test="hotel-signup-password-confirmation-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
            </div>
        </div>

        <Button
            type="submit"
            :disabled="processing"
            class="bg-brand-600 shadow-btn hover:bg-brand-700 font-heading mt-1 h-10 w-full rounded-md text-sm font-semibold text-white active:scale-[.97]"
            data-test="hotel-signup-submit-button"
        >
            <Spinner v-if="processing" />
            Submit hotel request
        </Button>
    </Form>

    <div class="text-body-sm text-ink-muted mt-3 text-center">
        Already approved?
        <Link
            :href="login()"
            class="text-brand-700 font-semibold underline underline-offset-4"
        >
            Sign in
        </Link>
    </div>
</template>
