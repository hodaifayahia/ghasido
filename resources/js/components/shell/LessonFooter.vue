<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The lesson runner's footer line (spec 0003 H.1), measured on photo_1:
 * "Guesvia | Real English for a Warmer Welcome" starts at x 31 and "Hotel
 * People. Brighter Futures." ends at x 1250, both 12.5px with their text
 * box at y 817–830 (21px above the window bottom). photo_7/12/14–19 also
 * draw the client's palm sketch just left of the right-hand line; `palm`
 * switches it on for pages with room under their content.
 */
type Props = {
    palm?: boolean;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <footer
        :class="
            cn(
                'mt-auto flex items-end justify-between gap-4 px-4 pt-8 pb-[21px] md:px-8',
                props.class,
            )
        "
    >
        <p class="text-[12.5px] leading-4 whitespace-nowrap">
            <span class="text-brand-600 font-semibold">Guesvia</span>
            <span class="text-ink-faint mx-[15px]" aria-hidden="true">|</span>
            <span class="text-ink-faint"
                >Real English for a Warmer Welcome</span
            >
        </p>

        <p
            class="text-ink-faint flex items-end gap-3 text-[12.5px] leading-4 whitespace-nowrap"
        >
            <img
                v-if="props.palm"
                src="/decor/palm-island.png"
                alt=""
                aria-hidden="true"
                width="540"
                height="252"
                draggable="false"
                class="pointer-events-none -mb-1.5 h-auto w-[140px] opacity-65 select-none"
            />
            <span>Hotel People. Brighter Futures.</span>
        </p>
    </footer>
</template>
