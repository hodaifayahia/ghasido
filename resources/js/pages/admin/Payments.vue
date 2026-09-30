<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import PaymentReviewSheet from '@/components/payments/PaymentReviewSheet.vue';
import PaymentsListPanel from '@/components/payments/PaymentsListPanel.vue';
import PaymentsSummary from '@/components/payments/PaymentsSummary.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { tk } from '@/lib/i18n';
import { dashboard, payments as paymentsRoute } from '@/routes';
import { read } from '@/routes/payments';
import type {
    PaymentCounts,
    PaymentFilters,
    PaymentPagination,
    PaymentRow,
    PaymentStatusFilter,
    PaymentTypeFilter,
} from '@/types';

/**
 * Payments sent from the checkout (client request 2026-09-27): who paid,
 * for which plan, how, with which reference and receipt. The review panel
 * opens from a row or from `?payment=<id>` (the notification email's link),
 * and the URL follows it so the panel can be shared.
 */
type Props = {
    payments: PaymentRow[];
    pagination: PaymentPagination;
    filters: PaymentFilters;
    counts: PaymentCounts;
    selected: PaymentRow | null;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Payments'), href: paymentsRoute() },
        ],
    },
});

type Query = Record<string, string | number>;

/** The list's current query, without the review panel's payment. */
function listQuery(overrides: Partial<PaymentFilters> = {}): Query {
    const filters = { ...props.filters, ...overrides };
    const query: Query = {};

    // Pending is the server's default tab.
    if (filters.status !== 'pending') query.status = filters.status;
    if (filters.type !== 'all') query.type = filters.type;
    if (filters.search !== '') query.search = filters.search;

    return query;
}

function visit(query: Query, only?: string[]): Promise<void> {
    return new Promise((resolve) => {
        router.get(
            paymentsRoute.url({ query }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only,
                onFinish: () => resolve(),
            },
        );
    });
}

function withPage(query: Query): Query {
    return props.pagination.currentPage > 1
        ? { ...query, page: props.pagination.currentPage }
        : query;
}

// Filters: a new list starts on its first page.
function setStatus(status: PaymentStatusFilter): void {
    void visit(listQuery({ status }));
}

function setType(type: PaymentTypeFilter): void {
    void visit(listQuery({ type }));
}

function setSearch(search: string): void {
    void visit(listQuery({ search }));
}

function clearFilters(): void {
    void visit(listQuery({ type: 'all', search: '' }));
}

function goToPage(page: number): void {
    void visit(page > 1 ? { ...listQuery(), page } : listQuery());
}

// The review panel -------------------------------------------------------

const reviewing = ref<PaymentRow | null>(props.selected);
const reviewOpen = ref(props.selected !== null);

// The server's fresh copy (after a reload or marking it read) wins.
watch(
    () => props.selected,
    (selected) => {
        if (selected !== null) {
            reviewing.value = selected;
        }
    },
);

// Keep an open payment in step with the list's copy too.
watch(
    () => props.payments,
    (rows) => {
        const current = reviewing.value;
        const fresh = current
            ? rows.find((row) => row.id === current.id)
            : null;

        if (fresh) {
            reviewing.value = fresh;
        }
    },
);

function markRead(payment: PaymentRow): void {
    if (!payment.unread) {
        return;
    }

    router.post(
        read.url(payment.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

async function review(row: PaymentRow): Promise<void> {
    reviewing.value = row;
    reviewOpen.value = true;

    await visit(withPage({ ...listQuery(), payment: row.id }), ['selected']);

    // Closed again before the visit landed: drop the payment from the URL.
    if (!reviewOpen.value) {
        void visit(withPage(listQuery()), ['selected']);

        return;
    }

    markRead(row);
}

watch(reviewOpen, (isOpen) => {
    if (!isOpen && props.selected !== null) {
        void visit(withPage(listQuery()), ['selected']);
    }
});

onMounted(() => {
    // Deep link from the notification email.
    if (props.selected !== null) {
        markRead(props.selected);
    }
});

const activeId = computed(() =>
    reviewOpen.value ? (reviewing.value?.id ?? null) : null,
);
</script>

<template>
    <Head :title="$t('Payments')" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            :title="$t('Payments')"
            :description="
                $t(
                    'Payments sent from the checkout. Check each receipt, contact the customer if needed, then approve the account.',
                )
            "
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <PaymentsSummary
            :counts="counts"
            :current="filters.status"
            @select="setStatus"
        />

        <PaymentsListPanel
            :rows="payments"
            :filters="filters"
            :counts="counts"
            :pagination="pagination"
            :active-id="activeId"
            @status="setStatus"
            @type="setType"
            @search="setSearch"
            @clear="clearFilters"
            @page="goToPage"
            @review="review"
        />
    </div>

    <PaymentReviewSheet v-model:open="reviewOpen" :payment="reviewing" />
</template>
