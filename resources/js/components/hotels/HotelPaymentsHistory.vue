<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, FileText, Receipt } from '@lucide/vue';
import PanelCard from '@/components/common/PanelCard.vue';
import {
    formatMoney,
    formatSubmitted,
    paymentStatusTone,
} from '@/components/hotels/paymentFormat';
import { cn } from '@/lib/utils';
import type { HotelApprovalPayment } from '@/types';

/**
 * The payments a hotel sent from the checkout, once its request has been
 * decided (client request 2026-09-27). Newest first.
 */
type Props = {
    payments: HotelApprovalPayment[];
};

defineProps<Props>();
</script>

<template>
    <PanelCard :title="$t('Payments')" title-id="hotel-payments-heading">
        <template #icon>
            <span
                class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
            >
                <Receipt class="size-4.5" aria-hidden="true" />
            </span>
        </template>

        <ul class="divide-line -my-1 divide-y">
            <li
                v-for="payment in payments"
                :key="payment.id"
                class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2 py-2.5"
            >
                <div class="min-w-0 flex-1 basis-48">
                    <p
                        class="text-brand-900 truncate text-[13px] font-semibold"
                    >
                        {{ payment.planName }}
                        <span class="text-ink-slate font-normal">
                            · {{ payment.method }}</span
                        >
                    </p>
                    <p class="text-ink-muted mt-0.5 truncate text-[11.5px]">
                        {{ formatSubmitted(payment.submittedAt) }}
                        <template v-if="payment.reference">
                            · <bdi>{{ payment.reference }}</bdi>
                        </template>
                    </p>
                </div>
                <p
                    class="font-heading text-brand-800 text-[14px] font-bold tabular-nums"
                >
                    <bdi>{{
                        formatMoney(payment.amount, payment.currency)
                    }}</bdi>
                </p>
                <span
                    :class="
                        cn(
                            'rounded-pill text-pill inline-flex h-6 items-center px-2.5 whitespace-nowrap',
                            paymentStatusTone[payment.status],
                        )
                    "
                >
                    {{ payment.statusLabel }}
                </span>
                <div class="flex items-center gap-1">
                    <a
                        v-if="payment.receiptUrl"
                        :href="payment.receiptUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-brand-600 hover:bg-brand-50 focus-visible:ring-brand-600/15 inline-flex min-h-10 items-center gap-1.5 rounded-md px-2 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    >
                        <FileText class="size-4" aria-hidden="true" />
                        {{ $t('Receipt') }}
                        <span class="sr-only">{{
                            $t('(opens in a new tab)')
                        }}</span>
                    </a>
                    <Link
                        :href="payment.reviewUrl"
                        class="text-brand-600 hover:bg-brand-50 focus-visible:ring-brand-600/15 inline-flex min-h-10 items-center gap-1 rounded-md px-2 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    >
                        {{ $t('Review in Payments') }}
                        <ArrowUpRight
                            class="size-4 rtl:-scale-x-100"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </li>
        </ul>
    </PanelCard>
</template>
