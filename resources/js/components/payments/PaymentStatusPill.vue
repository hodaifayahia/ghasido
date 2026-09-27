<script setup lang="ts">
import { CircleCheck, CircleX, Hourglass } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { paymentStatusTone } from '@/components/hotels/paymentFormat';
import { cn } from '@/lib/utils';
import type { PaymentStatus } from '@/types';

/**
 * A payment's status as a pill: the design system's tint/text pair plus an
 * icon, so the state never rests on colour alone (ACC-02).
 */
type Props = {
    status: PaymentStatus;
    /** The server's translated label. */
    label: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const icons: Record<PaymentStatus, Component> = {
    pending: Hourglass,
    confirmed: CircleCheck,
    rejected: CircleX,
};
</script>

<template>
    <span
        :class="
            cn(
                'rounded-pill inline-flex h-6 items-center gap-1 px-2.5 text-[11.5px] leading-none font-semibold whitespace-nowrap',
                paymentStatusTone[status],
                props.class,
            )
        "
    >
        <component
            :is="icons[status]"
            class="size-3.5 shrink-0"
            aria-hidden="true"
        />
        {{ label }}
    </span>
</template>
