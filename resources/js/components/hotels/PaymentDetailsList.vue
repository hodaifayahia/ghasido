<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import {
    formatMoney,
    formatSubmitted,
    paymentStatusLabel,
    paymentStatusTone,
} from '@/components/hotels/paymentFormat';
import type { ReviewPaymentStatus } from '@/components/hotels/paymentFormat';
import { cn } from '@/lib/utils';

/**
 * The "Payment details" table of the review (client request 2026-09-27):
 * plan, amount, method, reference, when it was sent and its status.
 */
type Props = {
    planName: string | null;
    amount: number;
    currency: string;
    method: string;
    reference: string | null;
    status: ReviewPaymentStatus;
    /** Translated by the server; falls back to the local label. */
    statusLabel?: string;
    submittedAt: string | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <!-- One grid, so the labels share a column sized to the longest one. -->
    <dl
        :class="
            cn(
                'border-line bg-surface grid grid-cols-[max-content_minmax(0,1fr)] overflow-hidden rounded-md border text-[12.5px] leading-5',
                '[&>dd]:border-line [&>dt]:border-line [&>dd]:border-t [&>dd]:py-2 [&>dd]:pe-3 [&>dt]:border-t [&>dt]:py-2 [&>dt]:ps-3 [&>dt]:pe-4',
                '[&>:nth-child(-n+2)]:border-t-0',
                props.class,
            )
        "
    >
        <template v-if="planName">
            <dt class="text-ink-slate">{{ $t('Plan') }}</dt>
            <dd class="text-brand-900 truncate font-semibold">
                {{ planName }}
            </dd>
        </template>
        <dt class="text-ink-slate">{{ $t('Amount') }}</dt>
        <dd
            class="font-heading text-brand-800 text-[14px] font-bold tabular-nums"
        >
            <bdi>{{ formatMoney(amount, currency) }}</bdi>
        </dd>
        <dt class="text-ink-slate">{{ $t('Payment method') }}</dt>
        <dd class="text-brand-900 truncate font-semibold">{{ method }}</dd>
        <dt class="text-ink-slate">{{ $t('Transaction reference') }}</dt>
        <dd
            v-if="reference"
            class="text-brand-900 font-mono text-[12px] font-semibold break-all"
        >
            <bdi>{{ reference }}</bdi>
        </dd>
        <dd v-else class="text-ink-muted">{{ $t('Not given') }}</dd>
        <dt class="text-ink-slate">{{ $t('Submitted') }}</dt>
        <dd class="text-brand-900">{{ formatSubmitted(submittedAt) }}</dd>
        <dt class="text-ink-slate">{{ $t('Status') }}</dt>
        <dd class="min-w-0">
            <span
                :class="
                    cn(
                        'rounded-pill text-pill inline-flex h-6 max-w-full items-center truncate px-2.5 whitespace-nowrap',
                        paymentStatusTone[status],
                    )
                "
            >
                {{ statusLabel ?? $t(paymentStatusLabel[status]) }}
            </span>
        </dd>
    </dl>
</template>
