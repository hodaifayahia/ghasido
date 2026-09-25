<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ClipboardList, MessageCircle, Volume2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import TaskCard from '@/components/learning/TaskCard.vue';
import WaveformGlyph from '@/components/learning/WaveformGlyph.vue';
import ActivityFrame from '@/components/learning/practice/ActivityFrame.vue';
import SpeedRow from '@/components/learning/practice/SpeedRow.vue';
import { useActivityRunner } from '@/composables/useActivityRunner';
import { useAudio } from '@/composables/useAudio';
import { cn } from '@/lib/utils';
import type { ActivityResult, ActivityViewOf, LessonSummary } from '@/types';

/*
 * Best Response (PRAC-01, PRAC-04; photo_10): hear the guest, choose the
 * most appropriate reply. Selecting advances; Check posts the answers and
 * the rows show correct / not quite (ACC-02).
 */
type Props = {
    lesson: LessonSummary;
    activity: ActivityViewOf<'best_response'>;
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
const { play, isPlaying } = useAudio();

const item = computed(() => props.activity.items[runner.current.value] ?? null);
const guestClip = computed(() => {
    const pair = item.value?.guest_audio_text_audio;

    return pair ? (speed.value === 'slow' ? pair.slow : pair.normal) : null;
});

function letter(index: number): string {
    return String.fromCharCode(65 + index);
}

function rowClass(itemId: string, optionId: string): string {
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
        :icon="MessageCircle"
        tone="sunset"
        :number="number"
        :label="activity.label"
        subtitle="Listen to the guest and choose the most appropriate response."
        :department="lesson.department.name"
        :lesson-number="lesson.positionInCourse"
        :lesson-count="lesson.courseLessonCount"
        :side-photo="activity.sideImage ?? lesson.cover"
        tip="Listen carefully. Think about a polite and helpful response."
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
        <template v-if="item && item.situation" #side>
            <TaskCard
                title="Situation"
                :text="item.situation"
                :icon="ClipboardList"
            />
        </template>

        <div v-if="item" class="flex flex-col gap-5">
            <div
                class="bg-brand-50 flex items-center gap-3 rounded-xl px-5 py-4"
            >
                <button
                    type="button"
                    aria-label="Play the guest"
                    :class="
                        cn(
                            'bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/30 grid size-12 shrink-0 place-items-center rounded-full text-white transition focus-visible:ring-3 focus-visible:outline-none active:scale-95',
                            isPlaying(guestClip) &&
                                'animate-pulse-ring motion-reduce:animate-none',
                        )
                    "
                    @click="play(guestClip, speed === 'slow' ? 0.75 : 1)"
                >
                    <Volume2
                        class="size-6 [&>path:first-child]:fill-current"
                        aria-hidden="true"
                    />
                </button>
                <p class="text-ink text-lg font-semibold">
                    Listen to the guest. What is the best response?
                </p>
            </div>

            <SpeedRow v-model:speed="speed" :meaning-enabled="false" />

            <div class="flex flex-col gap-3">
                <button
                    v-for="(option, index) in item.options"
                    :key="option.id"
                    type="button"
                    :disabled="runner.locked.value"
                    :aria-pressed="runner.selected(item.id) === option.id"
                    :class="
                        cn(
                            'flex w-full items-center gap-3 rounded-xl border-2 p-3 text-start transition disabled:cursor-default',
                            rowClass(item.id, option.id),
                        )
                    "
                    @click="runner.choose(item.id, option.id)"
                >
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

                    <span
                        class="bg-brand-50 text-brand-700 font-heading grid size-9 shrink-0 place-items-center rounded-full font-bold"
                    >
                        {{ letter(index) }}
                    </span>

                    <img
                        v-if="option.image"
                        :src="option.image.url"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="h-16 w-20 shrink-0 rounded-lg object-cover sm:h-[74px] sm:w-28"
                    />

                    <span
                        class="text-brand-600 hidden shrink-0 items-center gap-2 sm:flex"
                        aria-hidden="true"
                    >
                        <Volume2
                            class="size-5 [&>path:first-child]:fill-current"
                        />
                        <WaveformGlyph class="text-brand-400 h-5 w-16" />
                    </span>

                    <span class="text-ink min-w-0 flex-1 text-base font-medium">
                        {{ option.text }}
                    </span>

                    <span
                        v-if="
                            runner.optionState(item.id, option.id) === 'correct'
                        "
                        class="text-success-text inline-flex shrink-0 items-center gap-1 text-sm font-semibold"
                    >
                        <Check class="size-4" aria-hidden="true" /> Correct
                    </span>
                    <span
                        v-else-if="
                            runner.optionState(item.id, option.id) ===
                            'incorrect'
                        "
                        class="text-danger inline-flex shrink-0 items-center gap-1 text-sm font-semibold"
                    >
                        <X class="size-4" aria-hidden="true" /> Not quite
                    </span>
                </button>
            </div>
        </div>
    </ActivityFrame>
</template>
