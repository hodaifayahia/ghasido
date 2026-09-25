<script setup lang="ts">
import { computed } from 'vue';
import PracticeActivityCard from '@/components/learning/practice/PracticeActivityCard.vue';
import PracticeProgressPill from '@/components/learning/practice/PracticeProgressPill.vue';
import type { LessonSummary, StepBlockOf } from '@/types';

/*
 * Step 7, "Practice" (PRAC-01..03; photo_7): the heading with a progress
 * pill, then a grid of activity cards, one per placement in the block. Each
 * card links to its activity page; the grid wraps when a lesson has more
 * than the six the mockup draws.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlockOf<'practice'>;
    number?: number;
};

const props = defineProps<Props>();

const cards = computed(() => props.block.activities);
const completed = computed(
    () => cards.value.filter((card) => card.done).length,
);
const subtitle = computed(
    () =>
        props.block.settings.subtitle ??
        'Choose a practice activity to improve your skills.',
);
const motto = computed(() => props.block.settings.motto ?? null);
const heading = computed(
    () => `${props.number ? `${props.number}. ` : ''}Practice`,
);
</script>

<template>
    <div class="mt-3 flex flex-col gap-5">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
        >
            <div class="min-w-0">
                <h1 class="font-heading text-ink-royal text-h1 font-bold">
                    {{ heading }}
                </h1>
                <p class="text-ink-slate mt-1 text-base">{{ subtitle }}</p>
            </div>
            <PracticeProgressPill
                :completed="completed"
                :total="cards.length"
                :motto="motto"
                class="md:w-80 md:shrink-0"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <PracticeActivityCard
                v-for="(card, index) in cards"
                :key="card.id"
                :card="card"
                :index="index"
            />
        </div>
    </div>
</template>
