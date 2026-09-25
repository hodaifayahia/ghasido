<script setup lang="ts">
import { CircleCheck, Lightbulb } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * "Good to know" (spec 0003 G.4), measured on desginphotos/employ/photo_20
 * at 1280×853: 537×209 at x 720 / y 461; a 38px brand-50 header band with
 * a 26px gold bulb 25px in and the 16px semibold title at x 789; the body
 * in the header tint with five 29px rows (20px solid green check 32px in,
 * 14px text at x 797), starting 10px under the band.
 */
type Props = {
    title: string;
    items: string[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-tint-header shadow-card overflow-hidden rounded-lg border',
                props.class,
            )
        "
        :aria-label="title"
    >
        <header class="bg-brand-50 flex h-[38px] items-center ps-[25px]">
            <Lightbulb
                class="text-gold size-[26px] shrink-0 stroke-[2]"
                aria-hidden="true"
            />
            <h2
                class="font-heading text-ink-cobalt ms-[18px] -mt-1 text-[17px] leading-6 font-semibold"
            >
                {{ title }}
            </h2>
        </header>
        <ul class="flex list-none flex-col ps-8 pe-4 pt-[5px] pb-4">
            <li
                v-for="item in items"
                :key="item"
                class="flex min-h-[29px] items-center gap-[25px]"
            >
                <CircleCheck
                    class="text-success-text [&>path]:stroke-surface size-5 shrink-0 fill-current"
                    aria-hidden="true"
                />
                <span class="text-ink-slate text-[13px] leading-5">{{
                    item
                }}</span>
            </li>
        </ul>
    </section>
</template>
