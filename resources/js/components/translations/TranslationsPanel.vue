<script setup lang="ts">
import { Search, Sparkles } from '@lucide/vue';
import { ref, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import TranslationRowCard from '@/components/translations/TranslationRowCard.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    TranslationFilters,
    TranslationOptions,
    TranslationRow,
    TranslationScope,
    TranslationLanguage,
} from '@/types';

/*
 * The admin's list of Show Meaning translations (user request 2026-09-26):
 * pick a lesson, test or course to see every text it shows, or see all of
 * them with the ones learners asked for first. "Generate with AI" drafts
 * only the texts that have no meaning yet.
 */
type Props = {
    rows: TranslationRow[];
    scope: TranslationScope | null;
    filters: TranslationFilters;
    options: TranslationOptions;
    page: number;
    lastPage: number;
    total: number;
    canEdit: boolean;
    generating: boolean;
    language: TranslationLanguage;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    filter: [filters: TranslationFilters & { content: string }];
    generate: [];
    page: [page: number];
}>();

const content = ref(
    props.scope === null ? '' : `${props.scope.kind}:${props.scope.id}`,
);
const search = ref(props.filters.search);
const state = ref(props.filters.state);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        state.value = filters.state;
    },
);

let timer: ReturnType<typeof setTimeout> | null = null;

function apply(): void {
    emit('filter', {
        content: content.value,
        search: search.value.trim(),
        state: state.value,
    });
}

function onSearch(): void {
    if (timer !== null) clearTimeout(timer);
    timer = setTimeout(apply, 350);
}

function onState(value: string): void {
    state.value = value;
    apply();
}

const states = [
    { value: 'all', label: tk('All') },
    { value: 'missing', label: tk('Needs a meaning') },
    { value: 'ai', label: tk('AI drafts') },
    { value: 'manual', label: tk('By hand') },
];

const selectClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 min-w-0 rounded-md border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <PanelCard
        :title="scope ? scope.title : $t('All texts')"
        title-id="translations-title"
        body-class="-mx-4 -mb-4 mt-3"
    >
        <template #actions>
            <Button
                v-if="canEdit"
                type="button"
                :disabled="generating"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                data-test="generate-translations-button"
                @click="emit('generate')"
            >
                <Sparkles class="size-4" aria-hidden="true" />
                <span>{{
                    scope
                        ? $t('Generate missing with AI')
                        : $t('Generate requested with AI')
                }}</span>
            </Button>
        </template>

        <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
            <label class="min-w-0 flex-1 sm:max-w-xs">
                <span class="sr-only">{{ $t('Lesson, test or course') }}</span>
                <select
                    v-model="content"
                    :class="cn(selectClass, 'w-full')"
                    data-test="translations-content-select"
                    @change="apply"
                >
                    <option value="">{{ $t('All texts') }}</option>
                    <optgroup
                        v-if="options.courses.length"
                        :label="$t('Courses')"
                    >
                        <option
                            v-for="option in options.courses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </optgroup>
                    <optgroup
                        v-if="options.lessons.length"
                        :label="$t('Lessons')"
                    >
                        <option
                            v-for="option in options.lessons"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </optgroup>
                    <optgroup
                        v-if="options.tests.length"
                        :label="$t('Pre-test & Post-test')"
                    >
                        <option
                            v-for="option in options.tests"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </optgroup>
                </select>
            </label>
            <label class="relative min-w-0 flex-1 sm:max-w-xs">
                <span class="sr-only">{{ $t('Search the texts') }}</span>
                <Search
                    class="text-ink-faint pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="
                        $t('Search English or :language', {
                            language: language.name,
                        })
                    "
                    :class="cn(selectClass, 'w-full ps-9')"
                    @input="onSearch"
                />
            </label>
            <div
                class="border-line bg-app flex flex-wrap rounded-md border p-0.5"
                role="group"
                :aria-label="$t('Filter by state')"
            >
                <button
                    v-for="option in states"
                    :key="option.value"
                    type="button"
                    :aria-pressed="state === option.value"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600 min-h-9 rounded-[8px] px-3 text-[12px] font-semibold focus-visible:ring-2 focus-visible:outline-none',
                            state === option.value
                                ? 'bg-surface text-brand-700 shadow-card'
                                : 'text-ink-slate hover:text-brand-700',
                        )
                    "
                    @click="onState(option.value)"
                >
                    {{ $t(option.label) }}
                </button>
            </div>
        </div>

        <ul v-if="rows.length" class="border-line border-t">
            <TranslationRowCard
                v-for="row in rows"
                :key="row.id ?? row.text"
                :row="row"
                :can-edit="canEdit"
                :language="language"
            />
        </ul>
        <p
            v-else
            class="text-ink-slate border-line border-t px-4 py-10 text-center text-[13px]"
        >
            {{
                $t(
                    'No texts match. Pick a lesson, test or course above to see all of its texts.',
                )
            }}
        </p>

        <div
            v-if="lastPage > 1"
            class="border-line flex items-center justify-between gap-2 border-t px-4 py-3 text-[12px]"
        >
            <span class="text-ink-slate">
                {{
                    $t('Page :page of :last · :total texts', {
                        page,
                        last: lastPage,
                        total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 rounded-md px-3"
                    :disabled="page <= 1"
                    @click="emit('page', page - 1)"
                >
                    {{ $t('Previous') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 rounded-md px-3"
                    :disabled="page >= lastPage"
                    @click="emit('page', page + 1)"
                >
                    {{ $t('Next') }}
                </Button>
            </div>
        </div>
    </PanelCard>
</template>
