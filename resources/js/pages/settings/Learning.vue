<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpCircle, Languages, Plus } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tk } from '@/lib/i18n';
import {
    edit,
    languages as saveLanguagesRoute,
    update,
} from '@/routes/learning-settings';

/*
 * Settings → Learning (client request 2026-09-30; Super Admin only): the
 * Pre-test score at or above which a learner is offered the next level.
 * The learner still chooses to move up or stay (ADM-02).
 */
type HelperLanguageRow = {
    code: string;
    name: string;
    native: string;
    dir: 'ltr' | 'rtl';
    active: boolean;
};

type Props = {
    levelUpFrom: number;
    /** Show Meaning helper languages (client request 2026-09-30). */
    languages: HelperLanguageRow[];
    defaultLanguage: string;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('Learning'), href: edit() }],
    },
});

const percent = ref(String(props.levelUpFrom));
const errors = ref<Record<string, string>>({});
const saving = ref(false);

watch(
    () => props.levelUpFrom,
    (value) => (percent.value = String(value)),
);

// Helper languages: never removed, only switched off (their translations
// are kept).
const rows = reactive<HelperLanguageRow[]>(
    props.languages.map((row) => ({ ...row })),
);
const stored = new Set(props.languages.map((row) => row.code));
const defaultLanguage = ref(props.defaultLanguage);
const languageErrors = ref<Record<string, string>>({});
const savingLanguages = ref(false);

watch(
    () => props.languages,
    (value) => {
        rows.splice(0, rows.length, ...value.map((row) => ({ ...row })));
        value.forEach((row) => stored.add(row.code));
    },
);

function addLanguage(): void {
    rows.push({ code: '', name: '', native: '', dir: 'ltr', active: true });
}

function removeNew(index: number): void {
    rows.splice(index, 1);
}

function saveLanguages(): void {
    router.put(
        saveLanguagesRoute.url(),
        {
            languages: rows.map((row) => ({
                ...row,
                code: row.code.trim().toLowerCase(),
            })),
            default: defaultLanguage.value,
        },
        {
            preserveScroll: true,
            onStart: () => (savingLanguages.value = true),
            onFinish: () => (savingLanguages.value = false),
            onError: (bag) => (languageErrors.value = bag),
            onSuccess: () => (languageErrors.value = {}),
        },
    );
}

function save(): void {
    router.patch(
        update.url(),
        { level_up_from: Number(percent.value) },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onError: (bag) => (errors.value = bag),
            onSuccess: () => (errors.value = {}),
        },
    );
}
</script>

<template>
    <Head :title="$t('Learning')" />
    <h1 class="sr-only">{{ $t('Learning') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Learning levels')"
            :description="
                $t(
                    'Learners choose Beginner, Intermediate or Advanced. A strong Pre-test offers them the next level.',
                )
            "
        />

        <form
            class="border-line bg-surface shadow-card grid gap-4 rounded-lg border p-5"
            @submit.prevent="save"
        >
            <div class="flex items-start gap-3">
                <span
                    class="bg-success-tint text-success grid size-10 shrink-0 place-items-center rounded-xl"
                >
                    <ArrowUpCircle class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        class="font-heading text-brand-900 text-base font-semibold"
                    >
                        {{ $t('Move-up threshold') }}
                    </h2>
                    <p class="text-ink-slate text-sm leading-6">
                        {{
                            $t(
                                'A Pre-test score at or above this suggests the next level. Below it, the learner starts the lessons of the level they chose. Advanced is the last level.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="level-up-from">{{ $t('Score (%)') }}</Label>
                <div class="flex items-center gap-2">
                    <Input
                        id="level-up-from"
                        v-model="percent"
                        type="number"
                        min="1"
                        max="100"
                        required
                        class="h-11 w-28"
                        data-test="level-up-from-input"
                    />
                    <span class="text-ink-slate text-sm">%</span>
                </div>
                <InputError :message="errors.level_up_from" />
            </div>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="saving"
                    data-test="save-learning-settings-button"
                >
                    {{ $t('Save') }}
                </Button>
            </div>
        </form>

        <Heading
            variant="small"
            :title="$t('Helper languages')"
            :description="
                $t(
                    'The languages Show Meaning explains English in. Add one at any time; learners choose theirs.',
                )
            "
        />

        <form
            class="border-line bg-surface shadow-card grid gap-4 rounded-lg border p-5"
            data-test="helper-languages-form"
            @submit.prevent="saveLanguages"
        >
            <div class="flex items-start gap-3">
                <span
                    class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
                >
                    <Languages class="size-5" aria-hidden="true" />
                </span>
                <p class="text-ink-slate text-sm leading-6">
                    {{
                        $t(
                            'New content is translated into every language that is on. For content that already exists, open Translations, pick the language and press Generate missing with AI. A language is never deleted: switch it off and its translations are kept.',
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-3">
                <div
                    v-for="(row, index) in rows"
                    :key="index"
                    class="border-line grid gap-2 rounded-md border p-3 sm:grid-cols-[88px_minmax(0,1fr)_minmax(0,1fr)_96px] sm:items-end"
                    :data-test="`helper-language-${row.code || 'new'}`"
                >
                    <div class="grid gap-1">
                        <Label :for="`lang-code-${index}`" class="text-xs">{{
                            $t('Code')
                        }}</Label>
                        <Input
                            :id="`lang-code-${index}`"
                            v-model="row.code"
                            :disabled="stored.has(row.code)"
                            placeholder="ms"
                            maxlength="10"
                            class="h-10"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`lang-name-${index}`" class="text-xs">{{
                            $t('Name in English')
                        }}</Label>
                        <Input
                            :id="`lang-name-${index}`"
                            v-model="row.name"
                            placeholder="Malay"
                            maxlength="40"
                            class="h-10"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`lang-native-${index}`" class="text-xs">{{
                            $t('Name in the language')
                        }}</Label>
                        <Input
                            :id="`lang-native-${index}`"
                            v-model="row.native"
                            placeholder="Bahasa Melayu"
                            maxlength="40"
                            :dir="row.dir"
                            class="h-10"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`lang-dir-${index}`" class="text-xs">{{
                            $t('Direction')
                        }}</Label>
                        <select
                            :id="`lang-dir-${index}`"
                            v-model="row.dir"
                            class="border-line bg-surface text-ink h-10 rounded-md border px-2 text-sm"
                        >
                            <option value="ltr">
                                {{ $t('Left to right') }}
                            </option>
                            <option value="rtl">
                                {{ $t('Right to left') }}
                            </option>
                        </select>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-x-5 gap-y-2 sm:col-span-4"
                    >
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input
                                v-model="row.active"
                                type="checkbox"
                                class="accent-brand-600 size-4"
                            />
                            {{ $t('On') }}
                        </label>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input
                                v-model="defaultLanguage"
                                type="radio"
                                name="default-language"
                                :value="row.code"
                                :disabled="!row.active || row.code === ''"
                                class="accent-brand-600 size-4"
                            />
                            {{ $t('Default for new learners') }}
                        </label>
                        <button
                            v-if="!stored.has(row.code)"
                            type="button"
                            class="text-danger-text ms-auto min-h-11 text-sm font-semibold"
                            @click="removeNew(index)"
                        >
                            {{ $t('Remove') }}
                        </button>
                    </div>
                    <InputError
                        :message="
                            languageErrors[`languages.${index}.code`] ??
                            languageErrors[`languages.${index}.name`] ??
                            languageErrors[`languages.${index}.native`]
                        "
                        class="sm:col-span-4"
                    />
                </div>
            </div>
            <InputError
                :message="languageErrors.languages ?? languageErrors.default"
            />

            <div class="flex flex-wrap justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="gap-1.5"
                    data-test="add-helper-language-button"
                    @click="addLanguage"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ $t('Add a language') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="savingLanguages"
                    data-test="save-helper-languages-button"
                >
                    {{ $t('Save languages') }}
                </Button>
            </div>
        </form>
    </div>
</template>
