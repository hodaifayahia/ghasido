<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    LoaderCircle,
    RotateCcw,
    Sparkles,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { useContentGeneration } from '@/components/lessons/useContentGeneration';
import { Button } from '@/components/ui/button';
import { aiFill } from '@/routes/lessons';

/*
 * "Fill this lesson with AI" (client request 2026-11-07). The admin has
 * already created the lesson where it belongs, with its title; the AI
 * writes THIS lesson from their brief (no new lesson elsewhere), keeping
 * its title, unit and course. The steps land as a draft to review
 * (GEN-03); progress is polled from the server row (PERF-04).
 */
type Props = {
    lessonId: number;
    lessonTitle: string;
    /** "Unit 1 · Letters & Spelling": where the lesson sits. */
    context?: string | null;
    /** The lesson already has steps: they will be replaced. */
    hasSteps: boolean;
};

const props = withDefaults(defineProps<Props>(), { context: null });
const open = defineModel<boolean>('open', { required: true });

const prompt = ref('');
const images = ref(true);
const audio = ref(true);
const MAX = 20000;

const {
    generation,
    error,
    submitting,
    finished,
    failed,
    start,
    retryGeneration,
    reset,
} = useContentGeneration();

watch(open, (value) => {
    if (value && generation.value === null) {
        prompt.value = '';
        error.value = null;
    }
});

// When the lesson is written, show its new steps without a full reload.
watch(finished, (done) => {
    if (done) {
        router.reload({ only: ['lessonBlocks', 'editor', 'courses'] });
    }
});

const working = computed(
    () => generation.value !== null && !finished.value && !failed.value,
);

const stageLabel = computed((): string => {
    const state = generation.value?.state;

    if (state === 'images') {
        return 'Adding pictures…';
    }

    if (state === 'audio') {
        return 'Recording the audio…';
    }

    return 'Writing the lesson…';
});

function submit(): void {
    if (prompt.value.trim().length < 5 || submitting.value) {
        return;
    }

    const body = new FormData();
    body.append('prompt', prompt.value.trim());
    body.append('images', images.value ? '1' : '0');
    body.append('audio', audio.value ? '1' : '0');

    void start(aiFill.url(props.lessonId), body);
}

function startOver(): void {
    reset();
    prompt.value = '';
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t('Fill this lesson with AI')"
        :description="
            $t(
                'The AI writes the steps of this lesson only, on its title, from your instructions. Nothing is published until you check it.',
            )
        "
        size="lg"
    >
        <div class="mt-4 grid gap-4" data-test="lesson-ai-fill">
            <div
                class="border-line bg-app grid gap-0.5 rounded-md border px-3 py-2.5"
            >
                <p class="text-ink-slate text-[11.5px] font-semibold uppercase">
                    {{ $t('Lesson') }}
                </p>
                <p class="text-ink-night text-[14px] font-semibold">
                    {{ lessonTitle }}
                </p>
                <p v-if="context" class="text-ink-slate text-[12px]">
                    {{ context }}
                </p>
            </div>

            <form
                v-if="generation === null"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5">
                    <label
                        for="lesson-ai-fill-prompt"
                        class="text-brand-900 text-[12.5px] font-semibold"
                    >
                        {{ $t('Your instructions for this lesson') }}
                        <span class="text-danger-text">*</span>
                    </label>
                    <textarea
                        id="lesson-ai-fill-prompt"
                        v-model="prompt"
                        rows="9"
                        :maxlength="MAX"
                        required
                        :placeholder="
                            $t(
                                'e.g. Beginner level. The receptionist asks the guest to spell their surname. Words: alphabet, spell, surname, double letter. Phrases: “Could you spell that, please?”, “Is that with one L or two?”. Dialogue of 6 lines. Practice: 2 multiple-choice questions.',
                            )
                        "
                        class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-40 w-full resize-y rounded-sm border px-3 py-2 text-[13.5px] leading-6 focus-visible:ring-3 focus-visible:outline-none"
                        data-test="lesson-ai-fill-prompt"
                    />
                    <p
                        class="text-ink-faint text-end text-[11.5px] tabular-nums"
                    >
                        {{
                            $t(
                                'You can paste a whole lesson plan here. :count / :max characters',
                                { count: prompt.length, max: MAX },
                            )
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-x-5 gap-y-2 text-[12.5px]">
                    <label class="text-ink flex min-h-11 items-center gap-2">
                        <input
                            v-model="images"
                            type="checkbox"
                            class="accent-brand-600 size-4"
                        />
                        {{ $t('Add pictures') }}
                    </label>
                    <label class="text-ink flex min-h-11 items-center gap-2">
                        <input
                            v-model="audio"
                            type="checkbox"
                            class="accent-brand-600 size-4"
                        />
                        {{ $t('Record the audio') }}
                    </label>
                </div>

                <p
                    v-if="hasSteps"
                    class="bg-warning-tint text-warning-text flex items-start gap-2 rounded-md px-3 py-2 text-[12.5px]"
                    role="note"
                >
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{
                        $t(
                            'This lesson already has steps. The AI replaces them with the new ones; the title stays.',
                        )
                    }}
                </p>

                <p
                    v-if="error"
                    class="text-danger-text text-[12.5px]"
                    role="alert"
                >
                    {{ error }}
                </p>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line h-11 rounded-md px-4 md:h-10"
                        @click="open = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-1.5 rounded-md px-5 font-semibold text-white active:scale-[.97] md:h-10"
                        :disabled="submitting || prompt.trim().length < 5"
                        data-test="lesson-ai-fill-submit"
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                        {{ $t('Write this lesson') }}
                    </Button>
                </div>
            </form>

            <div v-else class="grid gap-3" aria-live="polite">
                <p
                    v-if="working"
                    class="text-brand-800 flex items-center gap-2 text-[14px] font-semibold"
                >
                    <LoaderCircle
                        class="size-5 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    {{ $t(stageLabel) }}
                </p>
                <p
                    v-if="working"
                    class="text-ink-slate text-[12.5px] leading-5"
                >
                    {{
                        $t(
                            'This takes about a minute. You can close this window; the lesson keeps being written.',
                        )
                    }}
                </p>

                <p
                    v-if="finished"
                    class="text-success-text flex items-center gap-2 text-[14px] font-semibold"
                >
                    <CircleCheck class="size-5" aria-hidden="true" />
                    {{
                        $t(
                            'The lesson is written. Check each step, then publish it.',
                        )
                    }}
                </p>

                <div
                    v-if="failed"
                    class="bg-danger-tint text-danger-text grid gap-1 rounded-md px-3 py-2.5 text-[12.5px]"
                    role="alert"
                >
                    <p class="flex items-center gap-2 font-semibold">
                        <CircleAlert class="size-4" aria-hidden="true" />
                        {{ $t('The AI could not write the lesson.') }}
                    </p>
                    <p>
                        {{
                            generation.failedReason ??
                            $t('The generation failed.')
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        v-if="failed"
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 h-11 gap-1.5 rounded-md px-4 md:h-10"
                        :disabled="submitting"
                        @click="retryGeneration"
                    >
                        <RotateCcw class="size-4" aria-hidden="true" />
                        {{ $t('Try again') }}
                    </Button>
                    <Button
                        v-if="finished || failed"
                        type="button"
                        variant="outline"
                        class="border-line h-11 rounded-md px-4 md:h-10"
                        @click="startOver"
                    >
                        {{ $t('New instructions') }}
                    </Button>
                    <Button
                        type="button"
                        class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 rounded-md px-5 font-semibold text-white md:h-10"
                        @click="open = false"
                    >
                        {{ finished ? $t('See the lesson') : $t('Close') }}
                    </Button>
                </div>
            </div>
        </div>
    </LessonsModal>
</template>
