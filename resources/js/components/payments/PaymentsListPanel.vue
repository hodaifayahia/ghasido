<script setup lang="ts">
import {
    ChevronRight,
    FileText,
    Image as ImageIcon,
    Inbox,
    Minus,
    Search,
    SearchX,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import {
    formatMoney,
    formatSubmitted,
} from '@/components/hotels/paymentFormat';
import PaymentStatusPill from '@/components/payments/PaymentStatusPill.vue';
import PaymentTypeChip from '@/components/payments/PaymentTypeChip.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    PaymentCounts,
    PaymentFilters,
    PaymentPagination,
    PaymentRow,
    PaymentStatusFilter,
    PaymentTypeFilter,
} from '@/types';

/**
 * The checkout payments (client request 2026-09-27): status tabs, a type
 * filter and a search over a table on desktop, stacked cards on a phone
 * (RESP-01). "Review" opens the payment's review panel.
 */
type Props = {
    rows: PaymentRow[];
    filters: PaymentFilters;
    counts: PaymentCounts;
    pagination: PaymentPagination;
    /** The payment open in the review panel, highlighted in the list. */
    activeId: number | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    status: [status: PaymentStatusFilter];
    type: [type: PaymentTypeFilter];
    search: [search: string];
    clear: [];
    page: [page: number];
    review: [row: PaymentRow];
}>();

const search = ref(props.filters.search);

watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function onSearch(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        emit('search', search.value.trim());
    }, 350);
}

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

type Tab = { value: PaymentStatusFilter; label: string };

const tabs: Tab[] = [
    { value: 'pending', label: tk('Pending') },
    { value: 'confirmed', label: tk('Confirmed') },
    { value: 'rejected', label: tk('Rejected') },
    { value: 'all', label: tk('All') },
];

function tabCount(tab: PaymentStatusFilter): number {
    return tab === 'all'
        ? props.counts.pending + props.counts.confirmed + props.counts.rejected
        : props.counts[tab];
}

const types: { value: PaymentTypeFilter; label: string }[] = [
    { value: 'all', label: tk('All') },
    { value: 'hotel', label: tk('Hotels') },
    { value: 'individual', label: tk('Individuals') },
];

const filtered = computed(
    () => props.filters.search !== '' || props.filters.type !== 'all',
);

const emptyText: Record<PaymentStatusFilter, string> = {
    pending: tk(
        'No payments waiting. A new one appears here as soon as a customer sends it from the checkout.',
    ),
    confirmed: tk(
        'No confirmed payments yet. A payment is confirmed when you approve its account.',
    ),
    rejected: tk('No rejected payments.'),
    all: tk('No payments yet. Payments sent from the checkout appear here.'),
};

function planLine(row: PaymentRow): string {
    return row.city !== null && row.city !== ''
        ? `${row.payerName} · ${row.city}`
        : row.payerName;
}
</script>

<template>
    <PanelCard
        :title="$t('Payment requests')"
        title-id="payment-requests-title"
        body-class="-mx-4 -mb-4 mt-2"
    >
        <!-- Status tabs: each one is a filtered visit of this page. -->
        <div
            class="border-line flex min-w-0 [scrollbar-width:none] gap-1 overflow-x-auto border-b px-4"
            role="group"
            :aria-label="$t('Filter by status')"
        >
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                :aria-pressed="filters.status === tab.value"
                :class="
                    cn(
                        'relative -mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-[13px] font-semibold whitespace-nowrap',
                        'focus-visible:ring-brand-600/40 rounded-t-md focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset',
                        filters.status === tab.value
                            ? 'border-brand-600 text-brand-600'
                            : 'text-ink-slate hover:text-brand-700 border-transparent',
                    )
                "
                :data-test="`payments-tab-${tab.value}`"
                @click="emit('status', tab.value)"
            >
                {{ $t(tab.label) }}
                <span
                    :class="
                        cn(
                            'rounded-pill inline-flex h-5 min-w-5 items-center justify-center px-1.5 text-[11px] leading-none font-bold tabular-nums',
                            tab.value === 'pending' && tabCount(tab.value) > 0
                                ? 'bg-danger text-white'
                                : filters.status === tab.value
                                  ? 'bg-brand-100 text-brand-700'
                                  : 'bg-app text-ink-slate',
                        )
                    "
                >
                    {{ tabCount(tab.value).toLocaleString('en') }}
                </span>
            </button>
        </div>

        <!-- Type filter and search -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3">
            <div
                class="border-line bg-app flex rounded-md border p-0.5"
                role="group"
                :aria-label="$t('Filter by customer type')"
            >
                <button
                    v-for="option in types"
                    :key="option.value"
                    type="button"
                    :aria-pressed="filters.type === option.value"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600 min-h-9 rounded-[8px] px-3 text-[12px] font-semibold focus-visible:ring-2 focus-visible:outline-none',
                            filters.type === option.value
                                ? 'bg-surface text-brand-700 shadow-card'
                                : 'text-ink-slate hover:text-brand-700',
                        )
                    "
                    @click="emit('type', option.value)"
                >
                    {{ $t(option.label) }}
                </button>
            </div>
            <label class="relative min-w-0 flex-1 basis-56 sm:max-w-xs">
                <span class="sr-only">{{ $t('Search payments') }}</span>
                <Search
                    class="text-ink-faint pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('Search customer, email or reference')"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-md border ps-9 pe-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                    data-test="payments-search"
                    @input="onSearch"
                />
            </label>
        </div>

        <!-- Empty state (per tab, or for a search with no match) -->
        <div
            v-if="rows.length === 0"
            class="border-line flex flex-col items-center gap-3 border-t px-6 py-12 text-center"
        >
            <span
                class="bg-brand-50 text-brand-600 grid size-12 place-items-center rounded-xl"
            >
                <SearchX v-if="filtered" class="size-6" aria-hidden="true" />
                <Inbox v-else class="size-6" aria-hidden="true" />
            </span>
            <p class="text-ink-slate max-w-sm text-[13px] leading-5">
                {{
                    filtered
                        ? $t('No payments match your search or filter.')
                        : $t(emptyText[filters.status])
                }}
            </p>
            <Button
                v-if="filtered"
                type="button"
                variant="outline"
                class="border-line text-brand-700 h-9 rounded-md px-3 text-[12px] font-semibold"
                @click="emit('clear')"
            >
                {{ $t('Clear filters') }}
            </Button>
        </div>

        <template v-else>
            <!-- Tablet / desktop: the table. Below 1280px it may scroll
                 inside the card, never the page (AGENTS.md §7). -->
            <div
                class="border-line hidden min-w-0 overflow-x-auto border-t md:block"
            >
                <table
                    class="w-full min-w-[900px] table-fixed border-collapse text-start text-[13px]"
                >
                    <caption class="sr-only">
                        {{
                            $t(
                                'Payments sent from the checkout, with their plan, amount, method and status',
                            )
                        }}
                    </caption>
                    <colgroup>
                        <col class="w-[23%]" />
                        <col class="w-[11%]" />
                        <col class="w-[12%]" />
                        <col class="w-[16%]" />
                        <col class="w-[7%]" />
                        <col class="w-[12%]" />
                        <col class="w-[11%]" />
                        <col class="w-[8%]" />
                    </colgroup>
                    <thead
                        class="bg-tint-header text-ink-slate text-[11px] tracking-wide uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-start">
                                {{ $t('Customer') }}
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-start">
                                {{ $t('Plan') }}
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-start">
                                {{ $t('Amount') }}
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-start">
                                {{ $t('Method') }}
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-center">
                                {{ $t('Receipt') }}
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-start">
                                {{ $t('Submitted') }}
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-start">
                                {{ $t('Status') }}
                            </th>
                            <th scope="col" class="px-4 py-2.5 text-end">
                                <span class="sr-only">{{ $t('Actions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            :class="
                                cn(
                                    'border-line text-ink border-t align-middle transition-colors duration-150',
                                    row.id === activeId
                                        ? 'bg-brand-50/70'
                                        : 'hover:bg-app-alt',
                                )
                            "
                            :data-test="`payment-row-${row.id}`"
                        >
                            <th
                                scope="row"
                                class="px-4 py-3 text-start font-normal"
                            >
                                <div class="flex min-w-0 items-start gap-2">
                                    <span
                                        :class="
                                            cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                row.unread
                                                    ? 'bg-brand-600'
                                                    : 'bg-transparent',
                                            )
                                        "
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            :class="
                                                cn(
                                                    'font-heading text-brand-900 block truncate text-[13px]',
                                                    row.unread
                                                        ? 'font-bold'
                                                        : 'font-semibold',
                                                )
                                            "
                                        >
                                            {{ row.customer }}
                                            <span
                                                v-if="row.unread"
                                                class="sr-only"
                                                >{{ $t('(new)') }}</span
                                            >
                                        </span>
                                        <span
                                            class="mt-1 flex min-w-0 items-center gap-1.5"
                                        >
                                            <PaymentTypeChip :type="row.type" />
                                            <span
                                                class="text-ink-slate truncate text-[11.5px]"
                                                >{{ planLine(row) }}</span
                                            >
                                        </span>
                                    </span>
                                </div>
                            </th>
                            <td
                                class="text-brand-800 truncate px-3 py-3 font-medium"
                            >
                                {{ row.planName }}
                            </td>
                            <td
                                class="text-ink truncate px-3 py-3 font-semibold tabular-nums"
                            >
                                {{ formatMoney(row.amount, row.currency) }}
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="text-ink block truncate font-medium"
                                >
                                    {{ row.method }}
                                </span>
                                <span
                                    v-if="row.reference"
                                    class="text-ink-slate block truncate font-mono text-[11px]"
                                    dir="ltr"
                                    :title="row.reference"
                                >
                                    {{ row.reference }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span
                                    v-if="row.receiptUrl !== null"
                                    class="bg-brand-50 text-brand-600 inline-grid size-8 place-items-center rounded-md"
                                    :title="
                                        row.isImage
                                            ? $t('Receipt image')
                                            : $t('PDF receipt')
                                    "
                                >
                                    <ImageIcon
                                        v-if="row.isImage"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <FileText
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span class="sr-only">{{
                                        row.isImage
                                            ? $t('Receipt image')
                                            : $t('PDF receipt')
                                    }}</span>
                                </span>
                                <span
                                    v-else
                                    class="text-ink-faint inline-grid size-8 place-items-center"
                                    :title="$t('No receipt')"
                                >
                                    <Minus class="size-4" aria-hidden="true" />
                                    <span class="sr-only">{{
                                        $t('No receipt')
                                    }}</span>
                                </span>
                            </td>
                            <td class="text-ink-slate px-3 py-3 text-[12px]">
                                {{ formatSubmitted(row.submittedAt) }}
                            </td>
                            <td class="px-3 py-3">
                                <PaymentStatusPill
                                    :status="row.status"
                                    :label="row.statusLabel"
                                />
                            </td>
                            <td class="px-4 py-3 text-end">
                                <Button
                                    type="button"
                                    :variant="
                                        row.status === 'pending'
                                            ? 'default'
                                            : 'outline'
                                    "
                                    :class="
                                        cn(
                                            'h-9 rounded-md px-3 text-[12px] font-semibold',
                                            row.status === 'pending'
                                                ? 'bg-brand-600 shadow-btn hover:bg-brand-700 text-white'
                                                : 'border-line text-brand-700',
                                        )
                                    "
                                    :aria-label="
                                        $t('Review the payment from :name', {
                                            name: row.customer,
                                        })
                                    "
                                    :data-test="`review-payment-${row.id}`"
                                    @click="emit('review', row)"
                                >
                                    {{
                                        row.status === 'pending'
                                            ? $t('Review')
                                            : $t('View')
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Phone: stacked cards -->
            <ul class="grid gap-2 px-4 pb-4 md:hidden">
                <li v-for="row in rows" :key="row.id">
                    <button
                        type="button"
                        :class="
                            cn(
                                'border-line bg-surface grid w-full gap-2.5 rounded-md border p-3 text-start',
                                'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
                                row.id === activeId && 'border-brand-300',
                            )
                        "
                        :data-test="`payment-card-${row.id}`"
                        @click="emit('review', row)"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="flex min-w-0 items-start gap-2">
                                <span
                                    :class="
                                        cn(
                                            'mt-1.5 size-2 shrink-0 rounded-full',
                                            row.unread
                                                ? 'bg-brand-600'
                                                : 'bg-transparent',
                                        )
                                    "
                                    aria-hidden="true"
                                />
                                <span class="min-w-0">
                                    <span
                                        class="font-heading text-brand-900 block truncate text-[14px] font-semibold"
                                    >
                                        {{ row.customer }}
                                        <span
                                            v-if="row.unread"
                                            class="sr-only"
                                            >{{ $t('(new)') }}</span
                                        >
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[12px]"
                                        >{{ row.planName }} ·
                                        {{ row.method }}</span
                                    >
                                </span>
                            </span>
                            <PaymentStatusPill
                                :status="row.status"
                                :label="row.statusLabel"
                                class="shrink-0"
                            />
                        </span>
                        <span class="flex items-end justify-between gap-2">
                            <span class="min-w-0">
                                <span
                                    class="text-ink block text-[15px] font-semibold tabular-nums"
                                >
                                    {{ formatMoney(row.amount, row.currency) }}
                                </span>
                                <span
                                    class="text-ink-slate mt-0.5 flex items-center gap-1.5 text-[11.5px]"
                                >
                                    <PaymentTypeChip :type="row.type" />
                                    {{ formatSubmitted(row.submittedAt) }}
                                </span>
                            </span>
                            <span
                                class="text-brand-600 inline-flex min-h-11 shrink-0 items-center gap-0.5 text-[12.5px] font-semibold"
                            >
                                {{
                                    row.status === 'pending'
                                        ? $t('Review')
                                        : $t('View')
                                }}
                                <ChevronRight
                                    class="size-4 rtl:-scale-x-100"
                                    aria-hidden="true"
                                />
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
        </template>

        <nav
            v-if="pagination.lastPage > 1"
            :aria-label="$t('Payment pages')"
            class="border-line flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-[12px]"
        >
            <span class="text-ink-slate">
                {{
                    $t(':from–:to of :total', {
                        from: pagination.from,
                        to: pagination.to,
                        total: pagination.total,
                    })
                }}
            </span>
            <div class="flex gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-9 rounded-md px-3 text-[12px]"
                    :disabled="pagination.currentPage <= 1"
                    @click="emit('page', pagination.currentPage - 1)"
                >
                    {{ $t('Previous') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-9 rounded-md px-3 text-[12px]"
                    :disabled="pagination.currentPage >= pagination.lastPage"
                    @click="emit('page', pagination.currentPage + 1)"
                >
                    {{ $t('Next') }}
                </Button>
            </div>
        </nav>
    </PanelCard>
</template>
