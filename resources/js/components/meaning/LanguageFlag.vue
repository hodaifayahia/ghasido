<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * A helper language's flag (client request 2026-10-01), from /flags/*.svg;
 * a language with no flag shows its code in a small chip instead.
 */
type Props = {
    flag: string | null;
    code: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <img
        v-if="flag"
        :src="`/flags/${flag}.svg`"
        alt=""
        aria-hidden="true"
        width="30"
        height="20"
        draggable="false"
        :class="
            cn(
                'ring-line h-5 w-[30px] shrink-0 rounded-[3px] object-cover ring-1',
                props.class,
            )
        "
    />
    <span
        v-else
        aria-hidden="true"
        :class="
            cn(
                'bg-brand-50 text-brand-800 ring-line grid h-5 w-[30px] shrink-0 place-items-center rounded-[3px] text-[10px] font-bold uppercase ring-1',
                props.class,
            )
        "
    >
        {{ code }}
    </span>
</template>
