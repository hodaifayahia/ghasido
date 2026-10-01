<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { update } from '@/routes/learn/first-login';
import type { FirstLoginUser, LevelOption } from '@/types';

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
    /** Only when the account has no department yet. */
    departments?: LevelOption[];
    level?: string | null;
    levels?: LevelOption[];
    /** The Show Meaning language (client request 2026-09-30). */
    helperLanguage?: string;
    helperLanguages?: LevelOption[];
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    departments: () => [],
    level: null,
    levels: () => [],
    helperLanguage: 'ar',
    helperLanguages: () => [],
});

// Department first, then level (client decision 2026-09-30).
const chosenLevel = ref(props.level ?? '');
const levelHints: Record<string, string> = {
    beginner: 'I know a few words and simple sentences.',
    intermediate: 'I can have short conversations with guests.',
    advanced: 'I speak with guests easily and want to polish my English.',
};
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
            {{
                user.completed
                    ? $t('Choose your English level to continue.')
                    : $t('A few quick things before you start.')
            }}
        </p>

        <div v-if="departments.length" class="grid gap-2">
            <Label for="department_id">{{ $t('Your department') }}</Label>
            <select
                id="department_id"
                name="department_id"
                required
                class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-md border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
                data-test="first-login-department"
            >
                <option value="" disabled selected>
                    {{ $t('Choose your department') }}
                </option>
                <option
                    v-for="department in departments"
                    :key="department.value"
                    :value="department.value"
                >
                    {{ department.label }}
                </option>
            </select>
            <InputError :message="errors.department_id" />
        </div>

        <fieldset class="grid gap-2" data-test="first-login-level">
            <legend class="text-ink mb-1 text-sm font-medium">
                {{ $t('Your English level') }}
            </legend>
            <div class="grid gap-2 sm:grid-cols-3">
                <label
                    v-for="option in levels"
                    :key="option.value"
                    :class="
                        cn(
                            'border-line bg-surface hover:border-brand-300 has-[:focus-visible]:ring-brand-600/15 flex min-h-14 cursor-pointer flex-col gap-1 rounded-md border p-3 transition-colors has-[:focus-visible]:ring-3',
                            chosenLevel === option.value &&
                                'border-brand-600 bg-brand-50',
                        )
                    "
                >
                    <span class="flex items-center gap-2">
                        <input
                            v-model="chosenLevel"
                            type="radio"
                            name="level"
                            :value="option.value"
                            required
                            class="accent-brand-600 size-4"
                        />
                        <span class="text-brand-900 text-base font-semibold">
                            {{ option.label }}
                        </span>
                    </span>
                    <span
                        v-if="levelHints[option.value]"
                        class="text-ink-slate text-sm leading-5"
                    >
                        {{ $t(levelHints[option.value] ?? '') }}
                    </span>
                </label>
            </div>
            <p class="text-ink-slate text-sm">
                {{
                    $t(
                        'You will see the lessons and tests for your level. You can change it later.',
                    )
                }}
            </p>
            <InputError :message="errors.level" />
        </fieldset>

        <div v-if="helperLanguages.length > 1" class="grid gap-2">
            <Label for="helper_language">{{
                $t('Language for word meanings')
            }}</Label>
            <select
                id="helper_language"
                name="helper_language"
                class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-md border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
                data-test="first-login-helper-language"
            >
                <option
                    v-for="option in helperLanguages"
                    :key="option.value"
                    :value="option.value"
                    :selected="option.value === helperLanguage"
                >
                    {{ option.label }}
                </option>
            </select>
            <p class="text-ink-slate text-sm">
                {{
                    $t(
                        'Show Meaning explains English words in this language. You can change it later.',
                    )
                }}
            </p>
            <InputError :message="errors.helper_language" />
        </div>

        <template v-if="!user.completed">
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
                    {{ $t('Your learning journey') }}
                </h2>
                <p class="text-ink-graphite text-sm leading-6">
                    {{
                        $t(
                            'Your answers, voice recordings and progress help us support your learning and prepare your training reports. They are saved as part of your training.',
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
        </template>

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
