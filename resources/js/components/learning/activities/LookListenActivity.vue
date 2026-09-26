<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Image as ImageIcon, Volume2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityFrame from '@/components/learning/practice/ActivityFrame.vue';
import SpeedRow from '@/components/learning/practice/SpeedRow.vue';
import WaveformGlyph from '@/components/learning/WaveformGlyph.vue';
import { useActivityRunner } from '@/composables/useActivityRunner';
import { useAudio } from '@/composables/useAudio';
import { cn } from '@/lib/utils';
import type { ActivityResult, ActivityViewOf, LessonSummary } from '@/types';

/*
 * Look & Listen (PRAC-01, PRAC-04; photo_9): the picture is the item image
 * on the left; pick the audio option that matches it. Selecting advances;
 * Check posts the answers and the rows show correct / not quite (ACC-02).
 */
type Props = {
    lesson: LessonSummary;
    activity: ActivityViewOf<'look_listen'>;
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
const { play } = useAudio();

const item = computed(() => props.activity.items[runner.current.value] ?? null);

function playOption(option: {
    audio_text_audio?: { normal: string | null; slow: string | null };
}): void {
    const pair = option.audio_text_audio;
    const src = pair
        ? speed.value === 'slow'
            ? pair.slow
            : pair.normal
        : null;
    play(src, speed.value === 'slow' ? 0.75 : 1);
}

function letter(index: number): string {
    return String.fromCharCode(65 + index);
}

function cardClass(itemId: string, optionId: string): string {
    switch (runner.optionState(itemId, optionId)) {
        case 'correct':
            return 'border-success bg-success-tint';
        case 'incorrect':
            return 'border-danger bg-danger-tint';
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
        :icon="ImageIcon"
        tone="success"
        :number="number"
        :label="activity.label"
        :subtitle="$t('Look at the picture and choose the correct audio.')"
        :department="lesson.department.name"
        :lesson-number="lesson.positionInCourse"
        :lesson-count="lesson.courseLessonCount"
        :side-photo="item?.image ?? lesson.cover"
        :tip="
            $t(
                'Look carefully at the picture and listen to all the audios before you choose.',
            )
        "
        :total="runner.total.value"
        :current="runner.current.value"
        :answered="runner.answeredIndexes.value"
        :can-check="runner.canCheck.value"
        :has-result="result !== null"
        :check-label="result !== null ? $t('Back to Practice') : $t('Check')"
        @check="onCheck"
        @prev="runner.prev()"
        @select="runner.goto($event)"
    >
        <div v-if="item" class="flex flex-col gap-5">
            <div
                class="bg-brand-50 flex items-center gap-3 rounded-xl px-5 py-4"
            >
                <span
                    class="bg-brand-600 grid size-12 shrink-0 place-items-center rounded-full text-white"
                >
                    <Volume2
                        class="size-6 [&>path:first-child]:fill-current"
                        aria-hidden="true"
                    />
                </span>
                <p class="text-ink text-lg font-semibold">
                    {{ $t('Which audio matches the picture?') }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="(option, index) in item.options"
                    :key="option.id"
                    role="radio"
                    :aria-checked="runner.selected(item.id) === option.id"
                    :aria-label="
                        $t('Option :letter', { letter: letter(index) })
                    "
                    :tabindex="runner.locked.value ? -1 : 0"
                    :class="
                        cn(
                            'flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 p-4 transition',
                            cardClass(item.id, option.id),
                        )
                    "
                    @click="runner.choose(item.id, option.id)"
                    @keydown.enter.prevent="runner.choose(item.id, option.id)"
                    @keydown.space.prevent="runner.choose(item.id, option.id)"
                >
                    <div class="flex items-center gap-3">
                        <span
                            :class="
                                cn(
                                    'grid size-6 shrink-0 place-items-center rounded-full border-2',
                                    runner.selected(item.id) === option.id
                                        ? 'border-brand-600'
                                        : 'border-line-strong',
                                )
                            "
                        >
                            <span
                                v-if="runner.selected(item.id) === option.id"
                                class="bg-brand-600 size-3 rounded-full"
                            />
                        </span>
                        <div
                            class="bg-surface border-line flex items-center gap-2 rounded-lg border px-3 py-2"
                        >
                            <button
                                type="button"
                                :aria-label="
                                    $t('Play option :letter', {
                                        letter: letter(index),
                                    })
                                "
                                class="text-brand-600 focus-visible:ring-brand-600/40 grid size-8 place-items-center rounded-full focus-visible:ring-3 focus-visible:outline-none"
                                @click.stop="playOption(option)"
                            >
                                <Volume2
                                    class="size-5 [&>path:first-child]:fill-current"
                                    aria-hidden="true"
                                />
                            </button>
                            <WaveformGlyph class="text-brand-400 h-6 w-20" />
                        </div>
                    </div>

                    <span class="text-ink text-lg font-semibold">
                        {{ letter(index) }}
                    </span>

                    <span
                        v-if="
                            runner.optionState(item.id, option.id) === 'correct'
                        "
                        class="text-success-text inline-flex items-center gap-1 text-sm font-semibold"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        {{ $t('Correct') }}
                    </span>
                    <span
                        v-else-if="
                            runner.optionState(item.id, option.id) ===
                            'incorrect'
                        "
                        class="text-danger inline-flex items-center gap-1 text-sm font-semibold"
                    >
                        <X class="size-4" aria-hidden="true" />
                        {{ $t('Not quite') }}
                    </span>
                </div>
            </div>

            <SpeedRow v-model:speed="speed" :meaning-enabled="false" />
        </div>
    </ActivityFrame>
</template>
