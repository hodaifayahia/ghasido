<script setup lang="ts">
import { computed, ref } from 'vue';
import ExpressionFeaturedCard from '@/components/learning/vocab/ExpressionFeaturedCard.vue';
import ExpressionsList from '@/components/learning/vocab/ExpressionsList.vue';
import { useI18n } from '@/composables/useI18n';
import type { LessonSummary, StepBlock } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * Step 3, "Useful Expressions" (LESSON-06, CTRL-01..03, PHRASE-02; photo_3):
 * a featured sentence card on the left, the other expressions on the right.
 * Tapping one swaps the feature in client-side and keeps the URL.
 */
type LexiconBlock = Extract<StepBlock, { type: 'vocabulary' | 'expressions' }>;

type Props = {
    lesson: LessonSummary;
    block: LexiconBlock;
};

const props = defineProps<Props>();

const { t } = useI18n();

const items = computed(() => props.block.lexicon);

const featuredId = ref<number | null>(
    items.value.find((item) => item.featured)?.id ?? items.value[0]?.id ?? null,
);

const featured = computed(
    () =>
        items.value.find((item) => item.id === featuredId.value) ??
        items.value[0] ??
        null,
);

const related = computed(() =>
    items.value.filter((item) => item.id !== featured.value?.id),
);

const subtitle = computed(() => props.block.settings.subtitle ?? null);
const sideTitle = computed(
    () => props.block.settings.side_title ?? t('More Useful Expressions'),
);
const tip = computed(() => props.block.settings.tip ?? null);
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <MeaningText
            as="p"
            :text="subtitle"
            v-if="subtitle"
            class="text-ink-slate text-lg leading-7"
        />

        <div class="grid gap-6 md:grid-cols-[minmax(0,58fr)_minmax(0,42fr)]">
            <ExpressionFeaturedCard
                v-if="featured"
                :key="featured.id"
                :item="featured"
                :lesson-id="lesson.id"
            />

            <ExpressionsList
                :items="related"
                :title="sideTitle"
                :tip="tip"
                @select="featuredId = $event"
            />
        </div>
    </div>
</template>
