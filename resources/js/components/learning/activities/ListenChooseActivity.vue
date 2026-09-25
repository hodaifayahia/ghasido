<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Headphones, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityFrame from '@/components/learning/practice/ActivityFrame.vue';
import PromptAudioBar from '@/components/learning/practice/PromptAudioBar.vue';
import SpeedRow from '@/components/learning/practice/SpeedRow.vue';
import { useActivityRunner } from '@/composables/useActivityRunner';
import { cn } from '@/lib/utils';
import type { ActivityResult, ActivityViewOf, LessonSummary } from '@/types';

/*
 * Listen & Choose (PRAC-01, PRAC-04; photo_8): play the prompt, pick the
 * matching picture. Selecting advances; Check posts every answer as one
 * attempt and the picked / correct cards then show a check or an X (ACC-02).
 */
type Props = {
    lesson: LessonSummary;
    activity: ActivityViewOf<'listen_choose'>;
    result: ActivityResult | null;
    answerUrl: string;
    backUrl: string;
    number?: number;
};

const props = defineProps<Props>();

const runner = useActivityRunner(
    () => props.activity,
    () => props.answerUrl,
    () => props.result,
);

const speed = ref<'normal' | 'slow'>('normal');

const item = computed(() => props.activity.items[runner.current.value] ?? null);
const clip = computed(() => {
    const pair = item.value?.audio_text_audio;

    if (!pair) {
        return null;
    }

    return speed.value === 'slow' ? pair.slow : pair.normal;
});

function letter(index: number): string {
    return String.fromCharCode(65 + index);
}

function cardClass(itemId: string, optionId: string): string {
    switch (runner.optionState(itemId, optionId)) {
        case 'correct':
            return 'border-success bg-success-tint';
        case 'incorrect':
            return 'border-danger bg-danger-tint animate-shake motion-reduce:animate-none';
        case 'selected':
            return 'border-brand-600 bg-brand-50';
        default:
            return 'border-line hover:border-brand-300';
    }
}

function onCheck(): void {
    if (props.result !== null) {
        router.visit(props.backUrl);

        return;
    }

    runner.submit();
}
</script>

<template>
    <ActivityFrame
        :back-url="backUrl"
        :icon="Headphones"
        tone="brand"
        :number="number"
        :label="activity.label"
        subtitle="Listen to the word or sentence and choose the correct picture."
        :department="lesson.department.name"
        :lesson-number="lesson.positionInCourse"
        :lesson-count="lesson.courseLessonCount"
        :side-photo="activity.sideImage ?? lesson.cover"
        tip="Listen carefully and look at the pictures. You can play the audio as many times as you need."
        :total="runner.total.value"
        :current="runner.current.value"
        :answered="runner.answeredIndexes.value"
        :can-check="runner.canCheck.value"
        :has-result="result !== null"
        :check-label="result !== null ? 'Back to Practice' : 'Check'"
        @check="onCheck"
        @prev="runner.prev()"
        @select="runner.goto($event)"
    >
        <div v-if="item" class="flex flex-col gap-5">
            <PromptAudioBar
                :src="clip"
                :text="item.audio_text"
                :index="runner.current.value + 1"
                :total="runner.total.value"
                :rate="speed === 'slow' ? 0.75 : 1"
            />

            <SpeedRow v-model:speed="speed" :meaning-enabled="false" />

            <div class="grid gap-4 sm:grid-cols-3">
                <button
                    v-for="(option, index) in item.options"
                    :key="option.id"
                    type="button"
                    :disabled="runner.locked.value"
                    :aria-pressed="runner.selected(item.id) === option.id"
                    :class="
                        cn(
                            'bg-surface relative flex flex-col rounded-xl border-2 p-3 text-center transition disabled:cursor-default',
                            cardClass(item.id, option.id),
                        )
                    "
                    @click="runner.choose(item.id, option.id)"
                >
                    <span
                        class="bg-surface/90 text-ink shadow-card absolute start-3 top-3 z-10 grid size-8 place-items-center rounded-full text-sm font-bold"
                    >
                        {{ letter(index) }}
                    </span>
                    <img
                        v-if="option.image"
                        :src="option.image.url"
                        :alt="option.label"
                        loading="lazy"
                        decoding="async"
                        class="h-40 w-full rounded-lg object-cover"
                    />
                    <span class="text-ink mt-3 text-lg font-semibold">
                        {{ option.label }}
                    </span>

                    <span
                        v-if="
                            runner.optionState(item.id, option.id) === 'correct'
                        "
                        class="text-success-text mt-1 inline-flex items-center justify-center gap-1 text-sm font-semibold"
                    >
                        <Check class="size-4" aria-hidden="true" /> Correct
                    </span>
                    <span
                        v-else-if="
                            runner.optionState(item.id, option.id) ===
                            'incorrect'
                        "
                        class="text-danger mt-1 inline-flex items-center justify-center gap-1 text-sm font-semibold"
                    >
                        <X class="size-4" aria-hidden="true" /> Not quite
                    </span>
                </button>
            </div>
        </div>
    </ActivityFrame>
</template>
