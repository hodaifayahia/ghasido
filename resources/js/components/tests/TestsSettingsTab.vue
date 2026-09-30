<script setup lang="ts">
import { Award, Eye, Languages, ListChecks, Shuffle } from '@lucide/vue';
import type { Component } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TestEditorSettings } from '@/types';

/*
 * The per-test rules, edited in the builder's Settings tab (client request
 * 2026-09-29; TEST-04, TSTM-02, JOURNEY-05, CERT-04). The parent owns the
 * state and sends it with Save Test; every control writes a new copy of it,
 * so a click always shows (the old card bound its checkboxes to props and
 * looked frozen).
 */
type Props = {
    errors?: Partial<Record<string, string>>;
};

withDefaults(defineProps<Props>(), { errors: () => ({}) });

const settings = defineModel<TestEditorSettings>({ required: true });

type BooleanKey =
    | 'shuffle_questions'
    | 'shuffle_options'
    | 'single_attempt'
    | 'show_answers'
    | 'motivational_message'
    | 'show_meaning';

type Toggle = { key: BooleanKey; label: string; hint: string };

type Group = { title: string; icon: Component; toggles: Toggle[] };

const groups: Group[] = [
    {
        title: tk('Order and attempts'),
        icon: Shuffle,
        toggles: [
            {
                key: 'shuffle_questions',
                label: tk('Show questions in random order'),
                hint: tk(
                    'Each employee gets the questions in a different order.',
                ),
            },
            {
                key: 'shuffle_options',
                label: tk('Show options in random order'),
                hint: tk('Answer options are mixed for each employee.'),
            },
            {
                key: 'single_attempt',
                label: tk('Allow only one attempt'),
                hint: tk('The employee cannot sit this test a second time.'),
            },
        ],
    },
    {
        title: tk('Help during the test'),
        icon: Languages,
        toggles: [
            {
                key: 'show_meaning',
                label: tk('Allow Show Meaning on questions'),
                hint: tk(
                    'Employees can tap to read a question’s meaning in their helper language (Arabic, French…).',
                ),
            },
        ],
    },
    {
        title: tk('After the test'),
        icon: ListChecks,
        toggles: [
            {
                key: 'show_answers',
                label: tk('Show correct answers'),
                hint: tk('Only when results are shown to the employee.'),
            },
            {
                key: 'motivational_message',
                label: tk('Add motivational message at the end'),
                hint: tk('A short encouraging message on the last screen.'),
            },
        ],
    },
];

const visibilityOptions: {
    value: TestEditorSettings['results_visibility'];
    label: string;
    hint: string;
}[] = [
    {
        value: 'hidden',
        label: tk('Hidden'),
        hint: tk('The employee sees no score. Recommended for research.'),
    },
    {
        value: 'score',
        label: tk('Score only'),
        hint: tk('The employee sees their total score.'),
    },
    {
        value: 'score_breakdown',
        label: tk('Score and breakdown'),
        hint: tk('The score and how each question went.'),
    },
];

function set<K extends keyof TestEditorSettings>(
    key: K,
    value: TestEditorSettings[K],
): void {
    settings.value = { ...settings.value, [key]: value };
}
</script>

<template>
    <div class="grid min-w-0 gap-4">
        <section
            v-for="group in groups.slice(0, 2)"
            :key="group.title"
            class="border-line grid gap-2 rounded-md border p-3"
        >
            <h3
                class="text-brand-900 flex items-center gap-2 text-[13px] font-semibold"
            >
                <component
                    :is="group.icon"
                    class="text-brand-600 size-4"
                    aria-hidden="true"
                />
                {{ $t(group.title) }}
            </h3>
            <div
                v-for="toggle in group.toggles"
                :key="toggle.key"
                class="grid gap-0.5"
            >
                <label
                    :for="`test-setting-${toggle.key}`"
                    class="text-brand-900 flex min-h-11 cursor-pointer items-center gap-2.5 text-[12.5px] leading-5 md:min-h-8"
                >
                    <Checkbox
                        :id="`test-setting-${toggle.key}`"
                        :model-value="settings[toggle.key]"
                        class="size-4.5 shrink-0"
                        :data-test="`test-setting-${toggle.key}`"
                        @update:model-value="set(toggle.key, $event === true)"
                    />
                    <span class="grid">
                        <span class="font-semibold">{{
                            $t(toggle.label)
                        }}</span>
                        <span class="text-ink-slate text-[11.5px]">{{
                            $t(toggle.hint)
                        }}</span>
                    </span>
                </label>
                <p
                    v-if="errors[toggle.key]"
                    class="text-danger-text ps-7 text-[12px]"
                >
                    {{ errors[toggle.key] }}
                </p>
            </div>
        </section>

        <!-- TEST-04: what the employee sees after submitting. Never assume
             results are shown; hidden is the stored default. -->
        <section class="border-line grid gap-2 rounded-md border p-3">
            <h3
                id="test-setting-results-title"
                class="text-brand-900 flex items-center gap-2 text-[13px] font-semibold"
            >
                <Eye class="text-brand-600 size-4" aria-hidden="true" />
                {{ $t('Results shown to the employee') }}
            </h3>
            <div
                role="radiogroup"
                aria-labelledby="test-setting-results-title"
                class="grid gap-2 sm:grid-cols-3"
            >
                <label
                    v-for="option in visibilityOptions"
                    :key="option.value"
                    :class="
                        cn(
                            'has-[:focus-visible]:ring-brand-600/15 flex min-h-11 cursor-pointer items-start gap-2 rounded-md border px-3 py-2 transition-colors duration-150 has-[:focus-visible]:ring-3',
                            settings.results_visibility === option.value
                                ? 'border-brand-600 bg-brand-50'
                                : 'border-line hover:bg-brand-50/60',
                        )
                    "
                >
                    <input
                        type="radio"
                        name="results_visibility"
                        class="accent-brand-600 mt-0.5 size-4 shrink-0"
                        :value="option.value"
                        :checked="settings.results_visibility === option.value"
                        :data-test="`test-setting-results-${option.value}`"
                        @change="set('results_visibility', option.value)"
                    />
                    <span class="grid">
                        <span
                            class="text-brand-900 text-[12.5px] font-semibold"
                        >
                            {{ $t(option.label) }}
                        </span>
                        <span class="text-ink-slate text-[11.5px] leading-4">
                            {{ $t(option.hint) }}
                        </span>
                    </span>
                </label>
            </div>
            <p
                v-if="errors.results_visibility"
                class="text-danger-text text-[12px]"
            >
                {{ errors.results_visibility }}
            </p>

            <div
                v-for="toggle in groups[2]?.toggles ?? []"
                :key="toggle.key"
                class="grid gap-0.5"
            >
                <label
                    :for="`test-setting-${toggle.key}`"
                    class="text-brand-900 flex min-h-11 cursor-pointer items-center gap-2.5 text-[12.5px] leading-5 md:min-h-8"
                >
                    <Checkbox
                        :id="`test-setting-${toggle.key}`"
                        :model-value="settings[toggle.key]"
                        class="size-4.5 shrink-0"
                        :data-test="`test-setting-${toggle.key}`"
                        @update:model-value="set(toggle.key, $event === true)"
                    />
                    <span class="grid">
                        <span class="font-semibold">{{
                            $t(toggle.label)
                        }}</span>
                        <span class="text-ink-slate text-[11.5px]">{{
                            $t(toggle.hint)
                        }}</span>
                    </span>
                </label>
                <p
                    v-if="errors[toggle.key]"
                    class="text-danger-text ps-7 text-[12px]"
                >
                    {{ errors[toggle.key] }}
                </p>
            </div>
        </section>

        <!-- JOURNEY-05, CERT-04: the score the certificate condition reads. -->
        <section class="border-line grid gap-2 rounded-md border p-3">
            <h3
                class="text-brand-900 flex items-center gap-2 text-[13px] font-semibold"
            >
                <Award class="text-brand-600 size-4" aria-hidden="true" />
                {{ $t('Pass mark') }}
            </h3>
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid w-32 gap-1.5">
                    <label
                        for="test-setting-pass-mark"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        {{ $t('Pass mark (%)') }}
                    </label>
                    <Input
                        id="test-setting-pass-mark"
                        type="number"
                        min="0"
                        max="100"
                        step="1"
                        inputmode="numeric"
                        :model-value="settings.passMark"
                        :aria-invalid="errors.pass_mark ? true : undefined"
                        class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                        data-test="test-setting-pass-mark"
                        @update:model-value="set('passMark', String($event))"
                    />
                </div>
                <p class="text-ink-slate min-w-0 flex-1 pb-2 text-[11.5px]">
                    {{
                        $t(
                            'From 0 to 100. Leave it empty for no pass mark. A certificate can require it.',
                        )
                    }}
                </p>
            </div>
            <p v-if="errors.pass_mark" class="text-danger-text text-[12px]">
                {{ errors.pass_mark }}
            </p>
        </section>
    </div>
</template>
