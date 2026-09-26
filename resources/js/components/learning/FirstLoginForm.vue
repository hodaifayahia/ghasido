<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { update } from '@/routes/learn/first-login';
import type { FirstLoginUser } from '@/types';

/*
 * The first-login screen (AUTH-04, AUTH-05, AUTH-06, PRIV-01, PRIV-02):
 * an email (mandatory when the hotel says so), the reminder consent and
 * the research notice with its acknowledgement. Consent is recorded as a
 * dated, revocable timestamp server-side (REM-05); both fields stay
 * editable from the profile afterwards.
 */
type Props = {
    user: FirstLoginUser;
    requireEmail: boolean;
    hotelName: string | null;
    departmentName: string | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <Form
        v-bind="update.form()"
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-6 rounded-lg border p-5 md:p-6',
                props.class,
            )
        "
        v-slot="{ errors, processing }"
    >
        <p class="text-ink-graphite text-base leading-7">
            {{ $t('Welcome, :name.', { name: user.name }) }}
            <template v-if="hotelName && departmentName">
                {{
                    $t(
                        'You are training with :hotel in the :department department.',
                        { hotel: hotelName, department: departmentName },
                    )
                }}
            </template>
            <template v-else-if="hotelName">
                {{ $t('You are training with :hotel.', { hotel: hotelName }) }}
            </template>
            {{ $t('Two quick things before you start.') }}
        </p>

        <div class="grid gap-2">
            <Label for="email">
                {{ $t('Email address') }}
                <span class="text-ink-slate font-normal">
                    {{ requireEmail ? $t('(required)') : $t('(optional)') }}
                </span>
            </Label>
            <Input
                id="email"
                name="email"
                type="email"
                autocomplete="email"
                :required="requireEmail"
                :default-value="user.email ?? ''"
                placeholder="you@example.com"
                class="h-11"
            />
            <p class="text-ink-slate text-sm">
                {{
                    $t(
                        'Used only for reminders about your training and to reset your password.',
                    )
                }}
            </p>
            <InputError :message="errors.email" />
        </div>

        <div class="flex items-start gap-3">
            <Checkbox
                id="reminder_consent"
                name="reminder_consent"
                :default-value="user.reminderConsent"
                class="mt-0.5 size-5"
            />
            <Label
                for="reminder_consent"
                class="text-ink leading-6 font-normal"
            >
                {{
                    $t(
                        'Yes, send me reminders about my training by email. I can change this at any time from my profile.',
                    )
                }}
            </Label>
        </div>

        <section
            class="bg-brand-50 flex flex-col gap-3 rounded-md p-4"
            aria-labelledby="research-notice"
        >
            <h2
                id="research-notice"
                class="font-heading text-brand-700 text-base font-semibold"
            >
                {{ $t('About your data') }}
            </h2>
            <p class="text-ink-graphite text-sm leading-6">
                {{
                    $t(
                        'Your answers, recordings and progress are recorded so your trainer can follow your training, and are used for research on English training for hotel staff. Personal details are limited to your name, username, email, hotel and department.',
                    )
                }}
            </p>
            <div class="flex items-start gap-3">
                <Checkbox
                    id="research_notice_acknowledged"
                    name="research_notice_acknowledged"
                    required
                    class="mt-0.5 size-5"
                />
                <Label
                    for="research_notice_acknowledged"
                    class="text-ink leading-6 font-normal"
                >
                    {{ $t('I have read this notice.') }}
                </Label>
            </div>
            <InputError :message="errors.research_notice_acknowledged" />
        </section>

        <div class="flex justify-end">
            <button
                type="submit"
                :disabled="processing"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-12 items-center rounded-md px-6 text-base font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60"
                data-test="complete-first-login-button"
            >
                {{ $t('Continue to my training') }}
            </button>
        </div>
    </Form>
</template>
