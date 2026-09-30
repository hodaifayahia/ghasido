<script setup lang="ts">
import { Hotel as HotelIcon, UserRound } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { PaymentCustomerType } from '@/types';

/** Who paid: a hotel or an individual subscriber. */
type Props = {
    type: PaymentCustomerType;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex h-5 items-center gap-1 rounded-[5px] px-1.5 text-[10.5px] leading-none font-semibold whitespace-nowrap',
                type === 'hotel'
                    ? 'bg-brand-50 text-brand-700'
                    : 'bg-aqua-tint text-ink',
                props.class,
            )
        "
    >
        <HotelIcon
            v-if="type === 'hotel'"
            class="size-3 shrink-0"
            aria-hidden="true"
        />
        <UserRound
            v-else
            class="text-aqua size-3 shrink-0"
            aria-hidden="true"
        />
        {{ type === 'hotel' ? $t('Hotel') : $t('Individual') }}
    </span>
</template>
