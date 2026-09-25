<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Circle,
    CircleAlert,
    CircleCheck,
    ExternalLink,
    LoaderCircle,
    RotateCcw,
    Smile,
    Sparkles,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { getJson } from '@/components/lessons/lessonsHttp';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { useContentGeneration } from '@/components/lessons/useContentGeneration';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { courses as coursesRoute, store } from '@/routes/lesson-generations';
import type {
    ContentGenerationCourseOption,
    ContentGenerationLevel,
    ContentGenerationState,
    LessonFilterOption,
} from '@/types';

/**
 * "Generate with AI" (GEN-01, GEN-03, PERF-04; spec 0004): one prompt, a
 * department, a level, one lesson or a whole course, optional images and
 * audio. After submitting, the same dialog shows the progress the server
 * reports, step by step, until the draft is ready to open (or failed, with a
 * Retry). Everything it creates is a draft (GEN-03).
 */
type Props = {
    departments: LessonFilterOption[];
    hotels: LessonFilterOption[];
    defaultDepartment: string;
    defaultHotel?: string;
};

const props = withDefaults(defineProps<Props>(), { defaultHotel: 'shared' });
const open = defineModel<boolean>('open', { required: true });

const NEW_COURSE = 'new';
const SHARED = 'shared';

const levels: Array<{ value: ContentGenerationLevel; label: string }> = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'elementary', label: 'Elementary' },
    { value: 'intermediate', label: 'Intermediate' },
];

const lessonCounts = ['2', '3', '4', '5', '6', '7', '8'];

const modeOptions: Array<{ value: 'lesson' | 'course'; label: string }> = [
    { value: 'lesson', label: 'A single lesson' },
    { value: 'course', label: 'A whole course' },
];

const prompt = ref('');
const department = ref(props.defaultDepartment);
const hotel = ref(props.defaultHotel);
const level = ref<ContentGenerationLevel>('beginner');
const mode = ref<'lesson' | 'course'>('lesson');
const lessonCount = ref('4');
const course = ref(NEW_COURSE);
const images = ref(true);
const audio = ref(true);
const courseOptions = ref<ContentGenerationCourseOption[]>([]);

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

const hotelOptions = computed(() =>
    props.hotels.filter((option) => option.value !== ''),
);

const canSubmit = computed(
    () =>
        prompt.value.trim().length >= 5 &&
        department.value !== '' &&
        !submitting.value,
);

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen && generation.value === null) {
            department.value = props.defaultDepartment;
            hotel.value = props.defaultHotel || SHARED;
            void loadCourses();
        }
    },
);

watch([department, hotel], () => {
    course.value = NEW_COURSE;
    void loadCourses();
});

async function loadCourses(): Promise<void> {
    if (department.value === '') {
        courseOptions.value = [];

        return;
    }

    const query: Record<string, string> = { department: department.value };
    if (hotel.value !== SHARED && hotel.value !== '') {
        query.hotel = hotel.value;
    }

    try {
        const response = await getJson<{
            courses: ContentGenerationCourseOption[];
        }>(coursesRoute.url({ query }));
        courseOptions.value = response.courses;
    } catch {
        courseOptions.value = [];
    }
}

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    const body = new FormData();
    body.append('prompt', prompt.value.trim());
    body.append('department_id', department.value);
    if (hotel.value !== SHARED && hotel.value !== '') {
        body.append('hotel_id', hotel.value);
    }
    body.append('level', level.value);
    body.append('mode', mode.value);
    body.append(
        'lesson_count',
        mode.value === 'course' ? lessonCount.value : '1',
    );
    if (course.value !== NEW_COURSE) {
        body.append('course_id', course.value);
    }
    body.append('images', images.value ? '1' : '0');
    body.append('audio', audio.value ? '1' : '0');

    await start(store.url(), body);
}

watch(finished, (isDone) => {
    if (isDone) {
        router.reload({
            only: [
                'lessonDirectory',
                'directoryStats',
                'directoryPagination',
                'courses',
                'filters',
            ],
        });
    }
});

function startOver(): void {
    reset();
    prompt.value = '';
}

type StepState = 'pending' | 'active' | 'done' | 'failed';

const ORDER: ContentGenerationState[] = [
    'queued',
    'outline',
    'writing',
    'images',
    'audio',
    'done',
];

function stepState(step: ContentGenerationState): StepState {
    const current = generation.value;

    if (current === null) {
        return 'pending';
    }

    if (current.state === 'done') {
        return 'done';
    }

    const reached = ORDER.indexOf(current.state);
    const index = ORDER.indexOf(step);

    if (current.state === 'failed') {
        const lessonsLeft = current.lessons.done < current.lessons.total;

        // The outline exists once the lesson total is known: only the
        // lessons still missing failed.
        if (step === 'outline') {
            return current.lessons.total > 0 ? 'done' : 'failed';
        }

        if (step === 'writing') {
            return lessonsLeft ? 'failed' : 'done';
        }

        return 'pending';
    }

    if (index < reached) {
        return 'done';
    }

    return index === reached ||
        (current.state === 'queued' && step === 'outline')
        ? 'active'
        : 'pending';
}

const steps = computed(() => {
    const current = generation.value;

    if (current === null) {
        return [];
    }

    const list: Array<{
        key: ContentGenerationState;
        label: string;
        state: StepState;
    }> = [];

    if (current.type === 'course') {
        const outline = stepState('outline');
        list.push({
            key: 'outline',
            label: withEllipsis('Planning the course', outline),
            state: outline,
        });
    }

    const writing =
        current.type === 'lesson' && current.state === 'queued'
            ? 'active'
            : stepState('writing');
    list.push({
        key: 'writing',
        label:
            current.lessons.total > 1
                ? `Writing lessons ${current.lessons.done}/${current.lessons.total}`
                : withEllipsis('Writing the lesson', writing),
        state: writing,
    });

    if (current.images.total > 0 || images.value) {
        list.push({
            key: 'images',
            label:
                current.images.total > 0
                    ? `Creating images ${current.images.done}/${current.images.total}` +
                      (current.images.failed > 0
                          ? ` (${current.images.failed} failed)`
                          : '')
                    : 'Creating images',
            state: stepState('images'),
        });
    }

    if (current.audio.total > 0 || audio.value) {
        list.push({
            key: 'audio',
            label:
                current.audio.total > 0
                    ? `Generating audio ${current.audio.done}/${current.audio.total}`
                    : 'Generating audio',
            state: stepState('audio'),
        });
    }

    return list;
});

const backgroundTitle = computed(() =>
    failed.value
        ? 'Generation needs attention'
        : finished.value
          ? 'Your lesson is ready'
          : 'Your lesson is generating',
);

const backgroundDetail = computed(() => {
    if (failed.value) {
        return 'Tap to review the error';
    }

    if (finished.value) {
        return 'Tap to review your draft';
    }

    return (
        steps.value
            .find((step) => step.state === 'active')
            ?.label.replace(/[.…]+$/u, '') ?? 'Getting everything ready'
    );
});

/** "…" only while a step is running; a finished step reads as a fact. */
function withEllipsis(label: string, state: StepState): string {
    return state === 'active' ? `${label}…` : label;
}

const stepIcon = {
    pending: Circle,
    active: LoaderCircle,
    done: CircleCheck,
    failed: CircleAlert,
};

const stepTone: Record<StepState, string> = {
    pending: 'text-ink-faint',
    active: 'text-brand-600',
    done: 'text-success',
    failed: 'text-danger-text',
};

const fieldLabel = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const selectTrigger =
    'border-line text-ink bg-surface h-11 sm:h-10 w-full rounded-sm text-[13px] shadow-none';
</script>

<template>
    <LessonsModal
        v-model:open="open"
        title="Generate with AI"
        description="Describe the lesson you need. The AI writes a complete draft: situation, vocabulary, expressions, dialogue, practice, pictures and audio. You review and publish it."
        size="lg"
    >
        <form
            v-if="generation === null"
            class="mt-2 grid gap-4"
            data-test="ai-generate-form"
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5">
                <label for="ai-generate-prompt" :class="fieldLabel">
                    What should learners practise?
                    <span class="text-danger-text">*</span>
                </label>
                <textarea
                    id="ai-generate-prompt"
                    v-model="prompt"
                    rows="5"
                    maxlength="4000"
                    required
                    placeholder="e.g. A guest arrives late at night and their room is not ready. Apologise, offer a solution and check them in politely."
                    class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-28 w-full resize-y rounded-sm border px-3 py-2 text-[13.5px] leading-6 focus-visible:ring-3 focus-visible:outline-none"
                    data-test="ai-generate-prompt"
                />
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <label for="ai-generate-department" :class="fieldLabel">
                        Department <span class="text-danger-text">*</span>
                    </label>
                    <Select v-model="department">
                        <SelectTrigger
                            id="ai-generate-department"
                            :class="selectTrigger"
                            data-test="ai-generate-department"
                        >
                            <SelectValue placeholder="Choose a department" />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in departments"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <label for="ai-generate-hotel" :class="fieldLabel">
                        Visible to
                    </label>
                    <Select v-model="hotel">
                        <SelectTrigger
                            id="ai-generate-hotel"
                            :class="selectTrigger"
                            data-test="ai-generate-hotel"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in hotelOptions"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <label for="ai-generate-level" :class="fieldLabel">
                        Level
                    </label>
                    <Select v-model="level">
                        <SelectTrigger
                            id="ai-generate-level"
                            :class="selectTrigger"
                            data-test="ai-generate-level"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in levels"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <fieldset class="grid gap-1.5">
                <legend :class="cn(fieldLabel, 'mb-1.5')">Create</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="option in modeOptions"
                        :key="option.value"
                        type="button"
                        :aria-pressed="mode === option.value"
                        :data-test="`ai-generate-mode-${option.value}`"
                        :class="
                            cn(
                                'min-h-11 rounded-md border px-3 text-start text-[13px] font-semibold transition-colors duration-150',
                                'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
                                mode === option.value
                                    ? 'border-brand-600 bg-brand-50 text-brand-700'
                                    : 'border-line bg-surface text-ink-slate hover:bg-brand-50/60',
                            )
                        "
                        @click="mode = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-if="mode === 'course'" class="grid gap-1.5">
                    <label for="ai-generate-count" :class="fieldLabel">
                        Number of lessons
                    </label>
                    <Select v-model="lessonCount">
                        <SelectTrigger
                            id="ai-generate-count"
                            :class="selectTrigger"
                            data-test="ai-generate-count"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="count in lessonCounts"
                                :key="count"
                                :value="count"
                                class="text-[13px]"
                            >
                                {{ count }} lessons
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <label for="ai-generate-course" :class="fieldLabel">
                        Add to course
                    </label>
                    <Select v-model="course">
                        <SelectTrigger
                            id="ai-generate-course"
                            :class="selectTrigger"
                            data-test="ai-generate-course"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem :value="NEW_COURSE" class="text-[13px]">
                                A new draft course
                            </SelectItem>
                            <SelectItem
                                v-for="option in courseOptions"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                                <span
                                    v-if="option.status === 'draft'"
                                    class="text-ink-faint"
                                >
                                    (draft)
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <label
                    class="text-ink flex min-h-11 items-center gap-2 text-[13px] font-medium"
                >
                    <Checkbox
                        :model-value="images"
                        data-test="ai-generate-images"
                        @update:model-value="images = $event === true"
                    />
                    Generate images
                </label>
                <label
                    class="text-ink flex min-h-11 items-center gap-2 text-[13px] font-medium"
                >
                    <Checkbox
                        :model-value="audio"
                        data-test="ai-generate-audio"
                        @update:model-value="audio = $event === true"
                    />
                    Generate audio
                </label>
            </div>

            <p
                v-if="error"
                class="bg-danger-tint text-danger-text flex items-start gap-2 rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ error }}
            </p>

            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none sm:h-10"
                    data-test="cancel-ai-generate"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    :disabled="!canSubmit"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] sm:h-10"
                    data-test="submit-ai-generate-button"
                >
                    <LoaderCircle
                        v-if="submitting"
                        class="size-4 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    <Sparkles v-else class="size-4" aria-hidden="true" />
                    Generate
                </Button>
            </div>
        </form>

        <div
            v-else
            class="mt-2 grid gap-4"
            aria-live="polite"
            data-test="ai-generate-progress"
        >
            <p
                class="text-ink-slate bg-brand-50/60 rounded-md px-3 py-2 text-[12.5px]"
            >
                “{{ generation.prompt }}”
            </p>

            <ol class="grid gap-2.5">
                <li
                    v-for="step in steps"
                    :key="step.key"
                    class="flex items-center gap-2.5 text-[13px]"
                    :data-test="`ai-generate-step-${step.key}`"
                >
                    <component
                        :is="stepIcon[step.state]"
                        :class="
                            cn(
                                'size-4.5 shrink-0',
                                stepTone[step.state],
                                step.state === 'active' &&
                                    'animate-spin motion-reduce:animate-none',
                            )
                        "
                        aria-hidden="true"
                    />
                    <span
                        :class="
                            step.state === 'pending'
                                ? 'text-ink-faint'
                                : 'text-ink font-medium'
                        "
                    >
                        {{ step.label }}
                    </span>
                    <span class="sr-only">({{ step.state }})</span>
                </li>
            </ol>

            <p
                v-if="finished"
                class="bg-success-tint text-success-text flex items-center gap-2 rounded-md px-3 py-2 text-[12.5px] font-semibold"
            >
                <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
                Done. {{ generation.lessonIds.length }}
                {{
                    generation.lessonIds.length === 1
                        ? 'lesson was'
                        : 'lessons were'
                }}
                created as a draft. Review, then publish.
            </p>

            <p
                v-if="failed"
                class="bg-danger-tint text-danger-text flex items-start gap-2 rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ generation.failedReason ?? 'The generation failed.' }}
            </p>

            <p v-if="error" class="text-danger-text text-[12px]" role="status">
                {{ error }}
            </p>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    v-if="finished || failed"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none sm:h-10"
                    data-test="ai-generate-again"
                    @click="startOver"
                >
                    New prompt
                </Button>
                <Button
                    v-if="failed"
                    type="button"
                    variant="outline"
                    :disabled="submitting"
                    class="border-danger text-danger-text hover:bg-danger-tint h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none sm:h-10"
                    data-test="ai-generate-retry-button"
                    @click="retryGeneration"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                    Retry
                </Button>
                <Button
                    v-if="!finished && !failed"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none sm:h-10"
                    @click="open = false"
                >
                    Keep working — it continues in the background
                </Button>
                <Button
                    v-if="generation.openUrl"
                    as-child
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white sm:h-10"
                >
                    <Link
                        :href="generation.openUrl"
                        data-test="ai-generate-open-lesson"
                        @click="open = false"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" />
                        Open lesson
                    </Link>
                </Button>
            </div>
        </div>
    </LessonsModal>

    <Teleport to="body">
        <button
            v-if="generation !== null && !open"
            type="button"
            class="border-brand-200 bg-surface text-brand-900 shadow-pop ease-brand hover:shadow-pop focus-visible:ring-brand-600/40 fixed right-4 bottom-20 z-40 inline-flex max-w-[calc(100vw_-_2rem)] items-center gap-3 rounded-full border px-3 py-2.5 text-start transition duration-200 hover:-translate-y-0.5 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none motion-reduce:hover:translate-y-0 md:right-6 md:bottom-6"
            aria-label="Open lesson generation progress"
            title="Tap to reopen lesson generation"
            data-test="ai-generate-background-status"
            @click="open = true"
        >
            <span
                class="bg-brand-600 shadow-btn relative grid size-14 shrink-0 place-items-center rounded-full text-white"
            >
                <span
                    class="border-brand-300 motion-safe:animate-pulse-ring absolute inset-0 rounded-full border"
                    aria-hidden="true"
                />
                <Smile
                    class="ai-generation-smile relative size-9"
                    aria-hidden="true"
                />
                <Sparkles
                    class="text-warning absolute -top-0.5 -right-0.5 size-4 motion-safe:animate-pulse motion-reduce:animate-none"
                    aria-hidden="true"
                />
            </span>
            <span class="grid min-w-0 gap-0.5">
                <span class="truncate text-[12.5px] font-semibold">
                    {{ backgroundTitle }}
                </span>
                <span class="text-ink-slate truncate text-[11.5px]">
                    {{ backgroundDetail }}
                </span>
            </span>
        </button>
    </Teleport>
</template>

<style scoped>
.ai-generation-smile {
    animation: ai-generation-smile 2.4s var(--ease-brand) infinite;
}

@keyframes ai-generation-smile {
    0%,
    100% {
        transform: translateY(0) rotate(-4deg);
    }
    50% {
        transform: translateY(-3px) rotate(4deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ai-generation-smile {
        animation: none;
    }
}
</style>
