<script setup lang="ts">
import { Star } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * A read-only 1-5 star rating, with its value spelled out for screen
 * readers (ACC-02: never colour alone). Used for learners' replies to a
 * message (client request 2026-11-07).
 */
type Props = {
    rating: number;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <span :class="cn('inline-flex items-center gap-0.5', props.class)">
        <span class="sr-only">{{
            $t('Rated :rating of 5', { rating: rating })
        }}</span>
        <Star
            v-for="n in 5"
            :key="n"
            :class="
                cn(
                    'size-3.5 shrink-0',
                    n <= Math.round(rating)
                        ? 'fill-gold text-gold'
                        : 'text-line-strong',
                )
            "
            aria-hidden="true"
        />
    </span>
</template>
