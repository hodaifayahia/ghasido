<script setup lang="ts">
import { computed, ref } from 'vue';
import RelatedWordsList from '@/components/learning/vocab/RelatedWordsList.vue';
import VocabExampleCard from '@/components/learning/vocab/VocabExampleCard.vue';
import VocabFeaturedCard from '@/components/learning/vocab/VocabFeaturedCard.vue';
import type { LessonSummary, StepBlock } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * Step 2, "Vocabulary" (LESSON-06, CTRL-01..03, PHRASE-02; photo_2): a
 * featured word card with its example on the left, the related words on the
 * right. Tapping a related word swaps the feature in client-side and keeps
 * the URL. `vocabulary` and `expressions` share one block shape.
 */
type LexiconBlock = Extract<StepBlock, { type: 'vocabulary' | 'expressions' }>;

type Props = {
    lesson: LessonSummary;
    block: LexiconBlock;
};

const props = defineProps<Props>();

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
    () => props.block.settings.side_title ?? 'Related Words',
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

        <div class="grid gap-6 md:grid-cols-[minmax(0,68fr)_minmax(0,32fr)]">
            <div class="flex min-w-0 flex-col gap-4">
                <VocabFeaturedCard
                    v-if="featured"
                    :key="featured.id"
                    :item="featured"
                    :lesson-id="lesson.id"
                />
                <VocabExampleCard
                    v-if="featured && featured.example"
                    :key="`ex-${featured.id}`"
                    :text="featured.example"
                    :word="featured.text"
                    :audio="featured.exampleAudio"
                    :arabic="featured.meaning?.exampleArabic ?? null"
                />
            </div>

            <RelatedWordsList
                :items="related"
                :title="sideTitle"
                :tip="tip"
                @select="featuredId = $event"
            />
        </div>
    </div>
</template>
