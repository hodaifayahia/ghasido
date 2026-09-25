<script setup lang="ts">
import { ArrowLeft, ArrowRight, Check, CircleHelp } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';

type Callout = {
    number: string;
    label: string;
    targetX: number;
    targetY: number;
    labelX: number;
    labelY: number;
};

type GuideStep = {
    number: string;
    kicker: string;
    title: string;
    description: string;
    image: string;
    alt: string;
    caption: string;
    callouts: Callout[];
    notes: { title: string; text: string }[];
};

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{ hasLesson: boolean }>();

const emit = defineEmits<{ startTour: [] }>();

const currentIndex = ref(0);

const steps: GuideStep[] = [
    {
        number: '1',
        kicker: 'Build the structure first',
        title: 'Course > Unit > Lesson',
        description:
            'A Course is the large category, a Unit is a chapter, and a Lesson is the topic employees complete. Start by choosing Course in the Add dialog, then save it.',
        image: '/tutorial/lesson-builder/09-add-course.png',
        alt: 'Guesvia Add Course dialog with arrows pointing to the course fields',
        caption:
            'This is the real Add Course screen. The same Add menu is used for Units and Lessons.',
        callouts: [
            {
                number: '1',
                label: 'Choose Course',
                targetX: 50,
                targetY: 38,
                labelX: 16,
                labelY: 30,
            },
            {
                number: '2',
                label: 'Department',
                targetX: 39,
                targetY: 57,
                labelX: 15,
                labelY: 58,
            },
            {
                number: '3',
                label: 'Hotel scope',
                targetX: 66,
                targetY: 57,
                labelX: 84,
                labelY: 58,
            },
            {
                number: '4',
                label: 'Course title',
                targetX: 50,
                targetY: 73,
                labelX: 17,
                labelY: 77,
            },
        ],
        notes: [
            {
                title: 'Course example',
                text: 'Front Desk English',
            },
            {
                title: 'Unit example',
                text: 'Welcoming Guests',
            },
            {
                title: 'Lesson example',
                text: 'Handling a Room Request',
            },
        ],
    },
    {
        number: '2',
        kicker: 'Create the lesson',
        title: 'Choose the Course, Unit and title',
        description:
            'Select the Course and Unit that should contain the lesson, enter the lesson title, and leave the default nine employee steps checked. Then click Create Lesson.',
        image: '/tutorial/lesson-builder/02-create-lesson.png',
        alt: 'Guesvia Create Lesson screen with arrows pointing to course, unit, title and default steps',
        caption:
            'This page creates a draft and then takes you directly to the lesson editor.',
        callouts: [
            {
                number: '1',
                label: 'Course',
                targetX: 27,
                targetY: 45,
                labelX: 12,
                labelY: 39,
            },
            {
                number: '2',
                label: 'Unit',
                targetX: 27,
                targetY: 55,
                labelX: 12,
                labelY: 61,
            },
            {
                number: '3',
                label: 'Lesson title',
                targetX: 45,
                targetY: 64,
                labelX: 77,
                labelY: 63,
            },
            {
                number: '4',
                label: 'Keep nine steps',
                targetX: 31,
                targetY: 72,
                labelX: 73,
                labelY: 75,
            },
        ],
        notes: [
            {
                title: 'The draft is safe',
                text: 'You can edit everything before publishing.',
            },
            {
                title: 'Nine starter steps',
                text: 'Situation, Vocabulary, Expressions, Listen & Repeat, Dialogue, Video, Practice, AI Role-play and Complete.',
            },
        ],
    },
    {
        number: '3',
        kicker: 'Open a lesson',
        title: 'Use Lesson Directory > Edit',
        description:
            'The Lesson Directory shows every lesson and its status. Click Edit on the lesson you want to fill. Draft lessons are not visible to employees until you publish them.',
        image: '/tutorial/lesson-builder/01-lesson-directory.png',
        alt: 'Guesvia Lesson Directory with arrows pointing to Edit and the lesson guide button',
        caption:
            'You can reopen this visual guide at any time with How to create a lesson.',
        callouts: [
            {
                number: '1',
                label: 'Open your lesson',
                targetX: 89,
                targetY: 46,
                labelX: 69,
                labelY: 31,
            },
            {
                number: '2',
                label: 'Draft / Published',
                targetX: 75,
                targetY: 38,
                labelX: 88,
                labelY: 62,
            },
            {
                number: '3',
                label: 'Open this guide',
                targetX: 90,
                targetY: 28,
                labelX: 65,
                labelY: 15,
            },
        ],
        notes: [
            {
                title: 'Status meaning',
                text: 'Draft = still hidden. Published = available to the correct employees.',
            },
            {
                title: 'Edit is safe',
                text: 'You can return to the builder whenever you need to change content.',
            },
        ],
    },
    {
        number: '4',
        kicker: 'Fill the lesson content',
        title: 'Cover, introduction and blocks',
        description:
            'At the top of Lesson Content, set the title, choose the cover image, and write a short introduction. On the right, click a content block to add it to the employee journey.',
        image: '/tutorial/lesson-builder/03-lesson-editor.png',
        alt: 'Guesvia lesson editor with the lesson title, cover image, introduction and content block palette visible',
        caption:
            'This is the real editor: the middle is your lesson, the right side adds employee steps.',
        callouts: [
            {
                number: '1',
                label: 'Lesson title',
                targetX: 56,
                targetY: 45,
                labelX: 29,
                labelY: 35,
            },
            {
                number: '2',
                label: 'Cover image',
                targetX: 56,
                targetY: 59,
                labelX: 28,
                labelY: 72,
            },
            {
                number: '3',
                label: 'Introduction',
                targetX: 57,
                targetY: 83,
                labelX: 76,
                labelY: 82,
            },
            {
                number: '4',
                label: 'Add blocks here',
                targetX: 85,
                targetY: 58,
                labelX: 87,
                labelY: 28,
            },
        ],
        notes: [
            {
                title: 'Keep the introduction short',
                text: 'One or two simple sentences are enough.',
            },
            {
                title: 'English first',
                text: 'English is always visible. Arabic belongs behind Show Meaning.',
            },
        ],
    },
    {
        number: '5',
        kicker: 'Organise employee steps',
        title: 'Objectives and Lesson Blocks',
        description:
            'Add learning objectives, then use each Lesson Block row as one employee step. Click Edit to fill it, the arrows to reorder it, the eye to hide it, duplicate to copy it, and the trash icon to remove it.',
        image: '/tutorial/lesson-builder/08-lesson-objectives.png',
        alt: 'Guesvia lesson objectives and lesson blocks with arrows pointing to objectives and block edit controls',
        caption: 'Every visible block becomes one step in the employee lesson.',
        callouts: [
            {
                number: '1',
                label: 'Add Objective',
                targetX: 71,
                targetY: 16,
                labelX: 87,
                labelY: 13,
            },
            {
                number: '2',
                label: 'One block = one step',
                targetX: 61,
                targetY: 45,
                labelX: 27,
                labelY: 30,
            },
            {
                number: '3',
                label: 'Edit Vocabulary',
                targetX: 74,
                targetY: 59,
                labelX: 87,
                labelY: 68,
            },
            {
                number: '4',
                label: 'Reorder / hide / copy / delete',
                targetX: 68,
                targetY: 46,
                labelX: 22,
                labelY: 69,
            },
        ],
        notes: [
            {
                title: 'Recommended order',
                text: 'Situation > Vocabulary > Expressions > Listen & Repeat > Dialogue > Video > Practice > AI Role-play > Complete.',
            },
            {
                title: 'You can customise it',
                text: 'Remove steps you do not need and drag the remaining steps into the order you want.',
            },
            {
                title: 'Practice and AI Role-play',
                text: 'Practice uses activities that already exist. If it says No activity yet, the current builder does not have Add Activity there yet. Create AI scenarios under AI Scenarios before attaching them to AI Role-play.',
            },
        ],
    },
    {
        number: '6',
        kicker: 'Add vocabulary',
        title: 'Edit Vocabulary, then Add word',
        description:
            'Open the Vocabulary block and click Add word. A word can include an image, pronunciation, Arabic meaning, simple explanation, hotel example and its own Normal and Slow audio.',
        image: '/tutorial/lesson-builder/04-vocabulary-block.png',
        alt: 'Guesvia Vocabulary block editor with Add word, Show Meaning and Normal and Slow audio visible',
        caption:
            'The vocabulary editor keeps all word content together in one block.',
        callouts: [
            {
                number: '1',
                label: 'Add word',
                targetX: 87,
                targetY: 66,
                labelX: 85,
                labelY: 49,
            },
            {
                number: '2',
                label: 'Show Meaning',
                targetX: 39,
                targetY: 71,
                labelX: 20,
                labelY: 54,
            },
            {
                number: '3',
                label: 'Normal + Slow audio',
                targetX: 58,
                targetY: 92,
                labelX: 80,
                labelY: 89,
            },
        ],
        notes: [
            {
                title: 'What employees see',
                text: 'The English word is visible. Arabic and the explanation appear only when Show Meaning is tapped.',
            },
            {
                title: 'Audio',
                text: 'If a clip is missing, scroll below the word and click Generate TTS audio.',
            },
        ],
    },
    {
        number: '7',
        kicker: 'Fill one word completely',
        title: 'English, meaning and hotel example',
        description:
            'Type the English word first. Then add the Arabic meaning, a very simple explanation, and an example employees might hear at the hotel. Save the word when finished.',
        image: '/tutorial/lesson-builder/05-add-word.png',
        alt: 'Guesvia Add word form with arrows pointing to English, Arabic meaning, explanation, hotel example and Save',
        caption:
            'This is the exact Add word form. The same form is used for Useful Expressions.',
        callouts: [
            {
                number: '1',
                label: 'English',
                targetX: 42,
                targetY: 28,
                labelX: 17,
                labelY: 23,
            },
            {
                number: '2',
                label: 'Arabic meaning',
                targetX: 36,
                targetY: 68,
                labelX: 15,
                labelY: 58,
            },
            {
                number: '3',
                label: 'Simple explanation',
                targetX: 64,
                targetY: 68,
                labelX: 83,
                labelY: 58,
            },
            {
                number: '4',
                label: 'Hotel example',
                targetX: 36,
                targetY: 82,
                labelX: 14,
                labelY: 86,
            },
            {
                number: '5',
                label: 'Save',
                targetX: 75,
                targetY: 91,
                labelX: 87,
                labelY: 83,
            },
        ],
        notes: [
            {
                title: 'Example word',
                text: 'towel — منشفة — a cloth used to dry your hands or body.',
            },
            {
                title: 'Example sentence',
                text: 'Of course. I will send an extra towel to your room.',
            },
        ],
    },
    {
        number: '8',
        kicker: 'Add a dialogue and audio',
        title: 'Staff and Guest, line by line',
        description:
            'Open Dialogue, choose a scene image, and click Add line. Choose Staff or Guest, write one English sentence, and add Arabic for Show Meaning. After the sentences exist, generate the Normal and Slow audio clips lower in the same editor.',
        image: '/tutorial/lesson-builder/06-dialogue-audio.png',
        alt: 'Guesvia Dialogue block editor with scene image, Add line and conversation lines visible',
        caption:
            'A dialogue is a real conversation: one short sentence per line and a speaker for every line.',
        callouts: [
            {
                number: '1',
                label: 'Scene image',
                targetX: 33,
                targetY: 47,
                labelX: 16,
                labelY: 39,
            },
            {
                number: '2',
                label: 'Add line',
                targetX: 84,
                targetY: 89,
                labelX: 83,
                labelY: 73,
            },
            {
                number: '3',
                label: 'Staff or Guest',
                targetX: 21,
                targetY: 96,
                labelX: 15,
                labelY: 83,
            },
        ],
        notes: [
            {
                title: 'Guest example',
                text: 'Could I have an extra towel, please?',
            },
            {
                title: 'Staff example',
                text: 'Of course. I will send one right away.',
            },
            {
                title: 'Audio step',
                text: 'Scroll down inside the Dialogue editor and click Generate TTS audio for the missing Normal and Slow clips.',
            },
        ],
    },
    {
        number: '9',
        kicker: 'Check before employees see it',
        title: 'Preview, save a draft, then publish',
        description:
            'Open Preview and check Phone (390px) and Desktop. Use Save Draft while you are still working. Publish Lesson only after every block is complete and the employee view looks correct.',
        image: '/tutorial/lesson-builder/07-lesson-preview.png',
        alt: 'Guesvia lesson Preview tab showing the phone employee experience and Desktop switch',
        caption:
            'Preview is safe: it shows the employee experience without recording employee progress.',
        callouts: [
            {
                number: '1',
                label: 'Phone (390px)',
                targetX: 43,
                targetY: 41,
                labelX: 24,
                labelY: 28,
            },
            {
                number: '2',
                label: 'Desktop',
                targetX: 52,
                targetY: 41,
                labelX: 67,
                labelY: 29,
            },
            {
                number: '3',
                label: 'Save Draft / Publish',
                targetX: 88,
                targetY: 35,
                labelX: 84,
                labelY: 18,
            },
        ],
        notes: [
            {
                title: 'Save Draft',
                text: 'Use this while content is unfinished. Employees do not see the lesson yet.',
            },
            {
                title: 'Publish Lesson',
                text: 'Use this only when the lesson is ready for the selected department and hotel scope.',
            },
        ],
    },
] as const;

const currentStep = computed(() => steps[currentIndex.value]);
const progress = computed(() => `${currentIndex.value + 1} / ${steps.length}`);

function previous(): void {
    currentIndex.value = Math.max(0, currentIndex.value - 1);
}

function next(): void {
    currentIndex.value = Math.min(steps.length - 1, currentIndex.value + 1);
}

function selectStep(index: number): void {
    currentIndex.value = index;
}

watch(open, (value) => {
    if (value) {
        currentIndex.value = 0;
    }
});
</script>

<template>
    <LessonsModal
        v-model:open="open"
        title="How to create a lesson"
        description="A visual walkthrough of the real Guesvia lesson builder, from Course to Publish."
        size="xl"
    >
        <div class="mt-2 grid gap-3">
            <div
                class="border-brand-200 bg-brand-50/70 text-brand-900 rounded-md border px-4 py-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-[12px] font-semibold">
                            Follow the real screens
                        </p>
                        <p class="text-ink-slate mt-1 text-[11.5px]">
                            Course > Unit > Lesson > Blocks > Preview > Publish
                        </p>
                    </div>
                    <span
                        class="bg-surface border-brand-200 text-brand-700 rounded-pill inline-flex items-center gap-1.5 border px-2.5 py-1 text-[11px] font-semibold"
                    >
                        <CircleHelp class="size-3.5" aria-hidden="true" />
                        {{ progress }}
                    </span>
                </div>
            </div>

            <div
                class="border-line bg-surface overflow-hidden rounded-lg border"
            >
                <div
                    class="bg-brand-900 flex items-center justify-between gap-3 px-3 py-2.5 text-white sm:px-4"
                >
                    <div class="min-w-0">
                        <p class="text-brand-100 text-[10.5px] font-semibold">
                            {{ currentStep.kicker }}
                        </p>
                        <h3 class="mt-0.5 truncate text-[14px] font-semibold">
                            {{ currentStep.title }}
                        </h3>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="currentIndex === 0"
                            class="border-brand-200/40 text-brand-100 hover:bg-brand-800 h-8 w-8 rounded-md p-0 shadow-none"
                            aria-label="Previous visual guide step"
                            @click="previous"
                        >
                            <ArrowLeft class="size-3.5" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            class="bg-brand-400 text-brand-900 hover:bg-brand-300 h-8 gap-1 rounded-md px-2.5 text-[11px] font-semibold shadow-none"
                            :disabled="currentIndex === steps.length - 1"
                            @click="next"
                        >
                            Next
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Button>
                    </div>
                </div>

                <div class="bg-app-alt/50 p-2 sm:p-3">
                    <div
                        class="shadow-card relative mx-auto aspect-[1280/853] w-full overflow-hidden rounded-md border border-white"
                    >
                        <img
                            :src="currentStep.image"
                            :alt="currentStep.alt"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <svg
                            class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible"
                            viewBox="0 0 100 100"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <defs>
                                <marker
                                    id="lesson-guide-arrowhead"
                                    markerWidth="3.5"
                                    markerHeight="3.5"
                                    refX="2.8"
                                    refY="1.75"
                                    orient="auto"
                                >
                                    <path
                                        d="M0,0 L0,3.5 L3.5,1.75 z"
                                        fill="currentColor"
                                    />
                                </marker>
                            </defs>
                            <line
                                v-for="callout in currentStep.callouts"
                                :key="`${callout.number}-line`"
                                :x1="callout.labelX"
                                :y1="callout.labelY"
                                :x2="callout.targetX"
                                :y2="callout.targetY"
                                class="text-danger"
                                stroke="currentColor"
                                stroke-width="0.55"
                                stroke-linecap="round"
                                marker-end="url(#lesson-guide-arrowhead)"
                            />
                        </svg>

                        <div
                            v-for="callout in currentStep.callouts"
                            :key="callout.number"
                            class="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-1/2"
                            :style="{
                                left: `${callout.labelX}%`,
                                top: `${callout.labelY}%`,
                            }"
                        >
                            <span
                                class="border-danger bg-surface text-danger shadow-pop inline-flex max-w-[145px] items-center gap-1 rounded-md border px-1.5 py-1 text-[9px] leading-tight font-bold sm:max-w-[175px] sm:text-[10px]"
                            >
                                <span
                                    class="bg-danger text-surface grid size-4 shrink-0 place-items-center rounded-full text-[9px]"
                                >
                                    {{ callout.number }}
                                </span>
                                {{ callout.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-2 px-3 py-3 sm:px-4">
                    <p class="text-ink-slate text-[12px] leading-5">
                        {{ currentStep.description }}
                    </p>
                    <p
                        class="text-brand-700 bg-brand-50/70 rounded-md px-3 py-2 text-[11px] leading-5"
                    >
                        {{ currentStep.caption }}
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="note in currentStep.notes"
                            :key="note.title"
                            class="border-line bg-app-alt rounded-md border px-3 py-2.5"
                        >
                            <p
                                class="text-brand-900 text-[11.5px] font-semibold"
                            >
                                {{ note.title }}
                            </p>
                            <p
                                class="text-ink-slate mt-1 text-[11px] leading-4.5"
                            >
                                {{ note.text }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-1.5">
                <button
                    v-for="(step, index) in steps"
                    :key="step.number"
                    type="button"
                    :aria-label="`Open visual guide step ${step.number}`"
                    :aria-current="currentIndex === index ? 'step' : undefined"
                    :class="[
                        'grid size-6 place-items-center rounded-full text-[10px] font-semibold transition-colors',
                        currentIndex === index
                            ? 'bg-brand-600 text-white'
                            : 'bg-brand-100 text-brand-700 hover:bg-brand-200',
                    ]"
                    @click="selectStep(index)"
                >
                    {{ step.number }}
                </button>
            </div>

            <div
                class="border-line flex items-start gap-2 rounded-md border p-3"
            >
                <Check
                    class="text-success mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p class="text-ink-slate text-[12px] leading-5">
                    Save as Draft while you work. Publish only after checking
                    Preview on Phone and Desktop. Practice activities are added
                    from the Quiz / Practice block when activities are
                    available.
                </p>
            </div>

            <p
                v-if="!props.hasLesson"
                class="text-ink-slate bg-app-alt rounded-md px-3 py-2 text-[11.5px]"
            >
                To start the live arrow tour, open a lesson first. This visual
                guide is available from the Lesson Directory even before you
                have created your first lesson.
            </p>

            <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <Button
                    type="button"
                    variant="outline"
                    data-test="close-lesson-creation-tutorial"
                    @click="open = false"
                >
                    Close
                </Button>
                <Button
                    type="button"
                    :disabled="!props.hasLesson"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-10 rounded-md px-4 text-[12.5px] font-semibold text-white"
                    data-test="start-lesson-guided-tour"
                    @click="
                        emit('startTour');
                        open = false;
                    "
                >
                    Start live tour with arrows
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
