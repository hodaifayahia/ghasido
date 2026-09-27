<script setup lang="ts">
import { CircleCheck, CircleX, Hourglass } from '@lucide/vue';
import type { Component } from 'vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    PaymentCounts,
    PaymentStatus,
    PaymentStatusFilter,
} from '@/types';

/**
 * The three payment totals as stat cards. Each one is also a shortcut to
 * its tab below, so the numbers are never a dead end.
 */
type Props = {
    counts: PaymentCounts;
    current: PaymentStatusFilter;
};

defineProps<Props>();

const emit = defineEmits<{ select: [status: PaymentStatus] }>();

type Card = {
    status: PaymentStatus;
    label: string;
    short: string;
    hint: string;
    icon: Component;
    chip: string;
    ring: string;
};

// Tint/base pairs per metric (AGENTS.md §3 colour law).
const cards: Card[] = [
    {
        status: 'pending',
        label: tk('Awaiting approval'),
        short: tk('Pending'),
        hint: tk('Check the receipt, then approve the account'),
        icon: Hourglass,
        chip: 'bg-warning-tint text-warning',
        ring: 'border-warning/60',
    },
    {
        status: 'confirmed',
        label: tk('Confirmed'),
        short: tk('Confirmed'),
        hint: tk('Accounts approved and active'),
        icon: CircleCheck,
        chip: 'bg-success-tint text-success',
        ring: 'border-success/60',
    },
    {
        status: 'rejected',
        label: tk('Rejected'),
        short: tk('Rejected'),
        hint: tk('The customer was told why'),
        icon: CircleX,
        chip: 'bg-danger-tint text-danger',
        ring: 'border-danger/50',
    },
];
</script>

<template>
    <div class="grid min-w-0 grid-cols-3 gap-2 md:gap-3">
        <button
            v-for="card in cards"
            :key="card.status"
            type="button"
            :aria-pressed="current === card.status"
            :class="
                cn(
                    'border-line bg-surface shadow-card flex min-w-0 flex-col items-start gap-2 rounded-lg border p-3 text-start md:flex-row md:items-center md:gap-4 md:p-4',
                    'ease-brand hover:shadow-hover transition-[transform,box-shadow,border-color] duration-150 hover:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0',
                    'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
                    current === card.status && card.ring,
                )
            "
            :data-test="`payments-summary-${card.status}`"
            @click="emit('select', card.status)"
        >
            <span
                :class="
                    cn(
                        'grid size-9 shrink-0 place-items-center rounded-xl md:size-11',
                        card.chip,
                    )
                "
            >
                <component
                    :is="card.icon"
                    class="size-[18px] md:size-[22px]"
                    aria-hidden="true"
                />
            </span>
            <span class="min-w-0">
                <span
                    class="font-heading text-brand-800 block text-[22px] leading-none font-bold tabular-nums md:text-[26px]"
                >
                    {{ counts[card.status].toLocaleString('en') }}
                </span>
                <span
                    class="text-ink-indigo mt-1 block truncate text-[11.5px] font-semibold md:text-[13px]"
                >
                    <span class="md:hidden">{{ $t(card.short) }}</span>
                    <span class="hidden md:inline">{{ $t(card.label) }}</span>
                </span>
                <span
                    class="text-ink-slate mt-0.5 hidden truncate text-[12px] xl:block"
                >
                    {{ $t(card.hint) }}
                </span>
            </span>
        </button>
    </div>
</template>
