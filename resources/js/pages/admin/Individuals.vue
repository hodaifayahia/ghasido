<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bot, CalendarClock, UserCheck, UsersRound } from '@lucide/vue';
import { ref } from 'vue';
import StatCard from '@/components/common/StatCard.vue';
import IndividualFormDialog from '@/components/individuals/IndividualFormDialog.vue';
import IndividualReviewDialog from '@/components/individuals/IndividualReviewDialog.vue';
import IndividualsListPanel from '@/components/individuals/IndividualsListPanel.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { useCan } from '@/composables/useCan';
import { dashboard, individuals, subscriptions } from '@/routes';
import { toggle } from '@/routes/individuals';
import type {
    IndividualDefaults,
    IndividualFilters,
    IndividualOption,
    IndividualPagination,
    IndividualRow,
    IndividualStats,
} from '@/types';
import { tk } from '@/lib/i18n';

/**
 * Individual subscribers (user request 2026-09-25): people who learn with
 * GHASIDO without a hotel, each on their own access dates and AI allowance.
 */
type Props = {
    individuals: IndividualRow[];
    pagination: IndividualPagination;
    filters: IndividualFilters;
    stats: IndividualStats;
    departments: IndividualOption[];
    defaults: IndividualDefaults;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Subscriptions'), href: subscriptions() },
            { title: tk('Individuals'), href: individuals() },
        ],
    },
});

const { can } = useCan();
const canManage = can('subscriptions.manage');

const dialogOpen = ref(false);
const selected = ref<IndividualRow | null>(null);

// Bought online and waiting for the payment to be checked (client request
// 2026-09-27): the review shows the contact, payment and receipt.
const reviewOpen = ref(false);
const reviewing = ref<IndividualRow | null>(null);

function add(): void {
    selected.value = null;
    dialogOpen.value = true;
}

function edit(row: IndividualRow): void {
    reviewOpen.value = false;
    selected.value = row;
    dialogOpen.value = true;
}

function review(row: IndividualRow): void {
    reviewing.value = row;
    reviewOpen.value = true;
}

function toggleRow(row: IndividualRow): void {
    // The server refuses the switch until the purchase is approved (409).
    if (row.approvalState !== 'approved') {
        review(row);
        return;
    }

    router.post(toggle.url(row.id), {}, { preserveScroll: true });
}

function visit(query: Record<string, string | number>): void {
    router.get(
        individuals.url({ query }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function filter(filters: IndividualFilters): void {
    const query: Record<string, string> = {};

    if (filters.search !== '') query.search = filters.search;
    if (filters.state !== 'all') query.state = filters.state;

    visit(query);
}

function goToPage(page: number): void {
    const query: Record<string, string | number> = { page };

    if (props.filters.search !== '') query.search = props.filters.search;
    if (props.filters.state !== 'all') query.state = props.filters.state;

    visit(query);
}
</script>

<template>
    <Head :title="$t('Individual Subscribers')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            :title="$t('Individual Subscribers')"
            :description="
                $t(
                    'People who learn with GHASIDO on their own, without a hotel. Each one has their own access dates and AI allowance.',
                )
            "
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <div class="grid min-w-0 grid-cols-2 gap-2 md:grid-cols-4">
            <StatCard
                :value="stats.total"
                :label="$t('Individual subscribers')"
                tone="brand"
            >
                <template #icon>
                    <UsersRound class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="stats.active"
                :label="$t('Active now')"
                tone="success"
            >
                <template #icon>
                    <UserCheck class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="stats.endingSoon"
                :label="$t('Ending in 14 days')"
                tone="warning"
            >
                <template #icon>
                    <CalendarClock class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="stats.withAi"
                :label="$t('With AI practice')"
                tone="ai"
            >
                <template #icon>
                    <Bot class="size-5" aria-hidden="true" />
                </template>
            </StatCard>
        </div>

        <IndividualsListPanel
            :rows="props.individuals"
            :filters="filters"
            :pagination="pagination"
            :can-manage="canManage"
            :pending-count="stats.pending"
            @add="add"
            @edit="edit"
            @review="review"
            @toggle="toggleRow"
            @filter="filter"
            @page="goToPage"
        />
    </div>

    <IndividualReviewDialog
        v-model:open="reviewOpen"
        :individual="reviewing"
        :can-manage="canManage"
        @edit="edit"
    />

    <IndividualFormDialog
        v-if="canManage"
        v-model:open="dialogOpen"
        :individual="selected"
        :departments="departments"
        :defaults="defaults"
    />
</template>
