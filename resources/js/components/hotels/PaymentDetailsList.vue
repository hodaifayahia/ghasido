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
    <dl
        :class="
            cn(
                'border-line divide-line bg-surface divide-y overflow-hidden rounded-md border text-[12.5px]',
                props.class,
            )
        "
    >
        <div
            v-if="planName"
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2"
        >
            <dt class="text-ink-slate">{{ $t('Plan') }}</dt>
            <dd class="text-brand-900 truncate font-semibold">
                {{ planName }}
            </dd>
        </div>
        <div
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2"
        >
            <dt class="text-ink-slate">{{ $t('Amount') }}</dt>
            <dd
                class="font-heading text-brand-800 text-[14px] font-bold tabular-nums"
            >
                <bdi>{{ formatMoney(amount, currency) }}</bdi>
            </dd>
        </div>
        <div
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2"
        >
            <dt class="text-ink-slate">{{ $t('Payment method') }}</dt>
            <dd class="text-brand-900 truncate font-semibold">{{ method }}</dd>
        </div>
        <div
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2"
        >
            <dt class="text-ink-slate">{{ $t('Transaction reference') }}</dt>
            <dd
                v-if="reference"
                class="text-brand-900 font-mono text-[12px] font-semibold break-all"
            >
                <bdi>{{ reference }}</bdi>
            </dd>
            <dd v-else class="text-ink-muted">{{ $t('Not given') }}</dd>
        </div>
        <div
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2"
        >
            <dt class="text-ink-slate">{{ $t('Submitted') }}</dt>
            <dd class="text-brand-900">{{ formatSubmitted(submittedAt) }}</dd>
        </div>
        <div
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] items-center gap-3 px-3 py-2"
        >
            <dt class="text-ink-slate">{{ $t('Status') }}</dt>
            <dd>
                <span
                    :class="
                        cn(
                            'rounded-pill text-pill inline-flex h-6 items-center px-2.5 whitespace-nowrap',
                            paymentStatusTone[status],
                        )
                    "
                >
                    {{ statusLabel ?? $t(paymentStatusLabel[status]) }}
                </span>
            </dd>
        </div>
    </dl>
</template>
