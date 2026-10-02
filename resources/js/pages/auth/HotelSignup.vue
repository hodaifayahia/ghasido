<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { tk } from '@/lib/i18n';
import HotelSignupController from '@/actions/App/Http/Controllers/HotelSignupController';
import TransText from '@/components/common/TransText.vue';
import InputError from '@/components/InputError.vue';
import HelperLanguagesField from '@/components/meaning/HelperLanguagesField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import type { HelperLanguageOption } from '@/types';

type Props = {
    /** Offered helper languages (client request 2026-10-01). */
    helperLanguages?: HelperLanguageOption[];
};

withDefaults(defineProps<Props>(), { helperLanguages: () => [] });

const languages = ref<string[]>([]);
const otherLanguage = ref('');

defineOptions({
    layout: {
        title: tk('Register your hotel'),
        description: tk(
            'Share the hotel details and create the first manager login for Super Admin approval.',
        ),
    },
});
</script>

<template>
    <Head :title="$t('Register your hotel')" />

    <Form
        v-bind="HotelSignupController.store.form()"
        class="mt-4 grid gap-3"
        v-slot="{ errors, processing }"
    >
        <div class="grid grid-cols-2 gap-x-3 gap-y-2.5 sm:gap-3">
            <div class="col-span-2 grid gap-1.5">
                <Label for="hotel-name">{{ $t('Hotel name') }}</Label>
                <Input
                    id="hotel-name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="organization"
                    :placeholder="$t('Blue Coast Hotel')"
                    data-test="hotel-signup-name-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="hotel-city">{{ $t('City') }}</Label>
                <Input
                    id="hotel-city"
                    name="city"
                    type="text"
                    required
                    autocomplete="address-level2"
                    :placeholder="$t('Oran')"
                    data-test="hotel-signup-city-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.city" />
            </div>

            <div class="grid gap-1.5">
                <Label for="manager-name">{{ $t('Manager name') }}</Label>
                <Input
                    id="manager-name"
                    name="manager_name"
                    type="text"
                    required
                    autocomplete="name"
                    :placeholder="$t('Nassim Benali')"
                    data-test="hotel-signup-manager-name-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.manager_name" />
            </div>

            <div class="col-span-2 grid gap-1.5">
                <Label for="manager-email">{{ $t('Manager email') }}</Label>
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
                <Label for="manager-username">{{
                    $t('Manager username')
                }}</Label>
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
                <Label for="password">{{ $t('Password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :placeholder="$t('Create a password')"
                    data-test="hotel-signup-password-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="col-span-2 grid gap-1.5">
                <Label for="password-confirmation">{{
                    $t('Confirm password')
                }}</Label>
                <PasswordInput
                    id="password-confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :placeholder="$t('Repeat the password')"
                    data-test="hotel-signup-password-confirmation-input"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[15px] shadow-none focus-visible:ring-3"
                />
            </div>
        </div>

        <HelperLanguagesField
            v-if="helperLanguages.length > 0"
            v-model:languages="languages"
            v-model:other="otherLanguage"
            class="mt-1"
            :options="helperLanguages"
            :error="errors.helper_languages"
            :other-error="errors.helper_language_other"
        />

        <Button
            type="submit"
            :disabled="processing"
            class="bg-brand-600 shadow-btn hover:bg-brand-700 font-heading mt-1 h-10 w-full rounded-md text-sm font-semibold text-white active:scale-[.97]"
            data-test="hotel-signup-submit-button"
        >
            <Spinner v-if="processing" />
            {{ $t('Submit hotel request') }}
        </Button>
    </Form>

    <div class="text-body-sm text-ink-muted mt-3 text-center">
        <TransText text="Already approved? :link">
            <template #link>
                <Link
                    :href="login()"
                    class="text-brand-700 font-semibold underline underline-offset-4"
                    >{{ $t('Sign in') }}</Link
                >
            </template>
        </TransText>
    </div>
</template>
