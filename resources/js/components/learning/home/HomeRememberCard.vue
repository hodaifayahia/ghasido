<script setup lang="ts">
import { Heart } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * "Remember!" (spec 0003 G.4), measured on desginphotos/employ/photo_20 at
 * 1280×853: 373×106 at x 720 / y 682 in the danger tint; a 40px crimson
 * heart chip 17px in / 13px down; the 16px bold title at x 795 beside it
 * and the 14px text on 20px lines under it, 18px above the bottom.
 */
type Props = {
    title: string;
    text: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <aside
        :class="
            cn(
                'bg-danger-tint flex gap-[18px] rounded-lg p-[17px] pt-[13px] pb-[18px]',
                props.class,
            )
        "
    >
        <span
            class="bg-crimson grid size-10 shrink-0 place-items-center rounded-full text-white"
        >
            <Heart class="size-5 fill-current" aria-hidden="true" />
        </span>
        <div class="min-w-0">
            <h2
                class="font-heading text-crimson-text text-base leading-6 font-bold"
            >
                {{ title }}
            </h2>
            <p class="text-ink-slate mt-2.5 text-sm leading-5">{{ text }}</p>
        </div>
    </aside>
</template>
