<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tk } from '@/lib/i18n';
import { edit } from '@/routes/profile';

/*
 * Settings → Profile. Admin accounts fill the full contact profile (owner
 * request 2026-09-25): first and last name, email, phone and address; the
 * app sends them here until it is complete. Everyone else keeps name and
 * email (PRIV-03).
 */
type AdminProfile = {
    firstName: string | null;
    lastName: string | null;
    phone: string | null;
    address: string | null;
    incomplete: boolean;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    adminProfile: AdminProfile | null;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: tk('Profile settings'),
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="$t('Profile settings')" />

    <h1 class="sr-only">{{ $t('Profile settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Profile')"
            :description="
                adminProfile
                    ? $t(
                          'Your name and how to reach you: email, phone and address',
                      )
                    : $t('Update your name and email address')
            "
        />

        <p
            v-if="adminProfile?.incomplete"
            class="bg-warning-tint text-warning-text flex gap-2 rounded-md px-3 py-2.5 text-sm"
            role="status"
            data-test="admin-profile-incomplete"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                {{
                    $t(
                        'Complete your profile to continue. The platform uses it to reach you, for example when the AI credit runs low.',
                    )
                }}
            </span>
        </p>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <template v-if="adminProfile">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">{{ $t('First name') }}</Label>
                        <Input
                            id="first_name"
                            class="mt-1 block w-full"
                            name="first_name"
                            :default-value="adminProfile.firstName ?? ''"
                            required
                            autocomplete="given-name"
                            :placeholder="$t('First name')"
                        />
                        <InputError class="mt-2" :message="errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">{{ $t('Last name') }}</Label>
                        <Input
                            id="last_name"
                            class="mt-1 block w-full"
                            name="last_name"
                            :default-value="adminProfile.lastName ?? ''"
                            required
                            autocomplete="family-name"
                            :placeholder="$t('Last name')"
                        />
                        <InputError class="mt-2" :message="errors.last_name" />
                    </div>
                </div>
            </template>

            <div v-else class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('Full name')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="email"
                    :placeholder="$t('Email address')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <template v-if="adminProfile">
                <div class="grid gap-2">
                    <Label for="phone">{{ $t('Phone') }}</Label>
                    <Input
                        id="phone"
                        type="tel"
                        class="mt-1 block w-full"
                        name="phone"
                        :default-value="adminProfile.phone ?? ''"
                        required
                        autocomplete="tel"
                        placeholder="+213 555 12 34 56"
                    />
                    <InputError class="mt-2" :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">{{ $t('Address') }}</Label>
                    <Input
                        id="address"
                        class="mt-1 block w-full"
                        name="address"
                        :default-value="adminProfile.address ?? ''"
                        required
                        autocomplete="street-address"
                        :placeholder="$t('Street, city, country')"
                    />
                    <InputError class="mt-2" :message="errors.address" />
                </div>
            </template>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    >{{ $t('Save') }}</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
