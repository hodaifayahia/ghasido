<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Languages, MessageCircleQuestion } from '@lucide/vue';
import { ref } from 'vue';
import StatCard from '@/components/common/StatCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import TranslationsPanel from '@/components/translations/TranslationsPanel.vue';
import { useCan } from '@/composables/useCan';
import { dashboard, translations } from '@/routes';
import { generate } from '@/routes/translations';
import type {
    TranslationFilters,
    TranslationOptions,
    TranslationRow,
    TranslationScope,
} from '@/types';

/**
 * Show Meaning translations (user request 2026-09-26): made when content is
 * written, by one AI draft or by hand. Learners only read them.
 */
type Props = {
    rows: TranslationRow[];
    pagination: { currentPage: number; lastPage: number; total: number };
    scope: TranslationScope | null;
    filters: TranslationFilters;
    counts: { waiting: number; total: number };
    options: TranslationOptions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Translations', href: translations() },
        ],
    },
});

const { can } = useCan();
const canEdit = can('lessons.manage') || can('tests.manage');
const generating = ref(false);

function query(
    filters: TranslationFilters & { content: string },
    page = 1,
): Record<string, string | number> {
    const q: Record<string, string | number> = {};

    if (filters.content !== '') q.content = filters.content;
    if (filters.search !== '') q.search = filters.search;
    if (filters.state !== 'all') q.state = filters.state;
    if (page > 1) q.page = page;

    return q;
}

function currentContent(): string {
    return props.scope === null ? '' : `${props.scope.kind}:${props.scope.id}`;
}

function visit(q: Record<string, string | number>): void {
    router.get(
        translations.url({ query: q }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function filter(filters: TranslationFilters & { content: string }): void {
    visit(query(filters));
}

function goToPage(page: number): void {
    visit(query({ ...props.filters, content: currentContent() }, page));
}

function generateMissing(): void {
    const content = currentContent();

    router.post(generate.url(), content === '' ? {} : { content }, {
        preserveScroll: true,
        onStart: () => {
            generating.value = true;
        },
        onFinish: () => {
            generating.value = false;
        },
    });
}
</script>

<template>
    <Head title="Translations" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">Show Meaning translations</h1>
        <PageHeader
            title="Show Meaning translations"
            description="The Arabic meaning behind every English text learners read. Drafted once by AI when content is saved, or written by hand; learners never trigger the AI."
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <div class="grid min-w-0 grid-cols-2 gap-2 md:max-w-xl">
            <StatCard
                :value="counts.total"
                label="Texts with a meaning"
                tone="success"
            >
                <template #icon>
                    <Languages class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="counts.waiting"
                label="Asked for by learners"
                tone="warning"
            >
                <template #icon>
                    <MessageCircleQuestion class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
        </div>

        <TranslationsPanel
            :rows="rows"
            :scope="scope"
            :filters="filters"
            :options="options"
            :page="pagination.currentPage"
            :last-page="pagination.lastPage"
            :total="pagination.total"
            :can-edit="canEdit"
            :generating="generating"
            @filter="filter"
            @generate="generateMissing"
            @page="goToPage"
        />
    </div>
</template>
