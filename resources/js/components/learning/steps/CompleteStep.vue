<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BedDouble,
    Bot,
    Check,
    ClipboardCheck,
    Headphones,
    House,
    Info,
    Lightbulb,
    ListChecks,
    MessageSquare,
    Quote,
    TrendingUp,
    Trophy,
    Video as VideoIcon,
} from '@lucide/vue';
import { computed, onMounted } from 'vue';
import type { Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import ProgressRing from '@/components/learning/ProgressRing.vue';
import type { BlockType, LessonSummary, StepBlockOf } from '@/types';

/*
 * Step 9, "Lesson Completed!" (LESSON-05; photo_19): a trophy header, the
 * left photo with its quote, the ticked Lesson Summary, the progress ring,
 * an encouragement and What's Next. Reaching this step finishes the lesson —
 * completed on mount if it is not already (idempotent), then the summary is
 * refreshed so the ring counts this lesson.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlockOf<'complete'>;
    number?: number;
    completeUrl: string;
    alreadyDone?: boolean;
    preview?: boolean;
};

const props = defineProps<Props>();

const summary = computed(() => props.block.summary);
const settings = computed(() => props.block.settings);

const image = computed(() => settings.value.image ?? props.lesson.cover);
const quote = computed(() => settings.value.quote ?? null);
const encouragement = computed(() => settings.value.encouragement ?? null);
const closingQuote = computed(() => settings.value.closing_quote ?? null);
const subtitle = computed(
    () =>
        settings.value.subtitle ?? 'Great job! You have finished this lesson.',
);

const heading = computed(
    () => `${props.number ? `${props.number}. ` : ''}Lesson Completed!`,
);

const percent = computed(() => {
    const total = summary.value?.lessonsTotal ?? 0;
    const done = summary.value?.lessonsCompleted ?? 0;

    return total > 0 ? Math.round((done / total) * 100) : 0;
});

const nextUrl = computed(() =>
    props.preview
        ? null
        : (summary.value?.nextLesson?.url ?? summary.value?.homeUrl ?? ''),
);

const rowIcon: Record<BlockType, Component> = {
    situation: BedDouble,
    vocabulary: MessageSquare,
    expressions: Quote,
    listen_repeat: Headphones,
    dialogue: MessageSquare,
    video: VideoIcon,
    practice: ListChecks,
    ai_roleplay: Bot,
    complete: Trophy,
    text: ListChecks,
    note: ListChecks,
    image: ListChecks,
    audio: Headphones,
    email_activity: ListChecks,
    phone_activity: ListChecks,
};

function xsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

onMounted(async () => {
    if (props.preview || props.alreadyDone) {
        return;
    }

    try {
        await fetch(props.completeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
        });
        router.reload({ only: ['block', 'steps'] });
    } catch {
        // The footer/next visit records it; nothing is lost (PROG-03).
    }
});
</script>

<template>
    <div class="mt-3 flex flex-col gap-6">
        <div class="flex items-center gap-4">
            <span
                class="bg-brand-800 grid size-16 shrink-0 place-items-center rounded-full text-white md:size-[76px]"
            >
                <Trophy class="size-8 md:size-9" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h1
                    class="font-heading text-ink-royal md:text-h1 text-2xl font-bold"
                >
                    {{ heading }}
                </h1>
                <p class="text-ink-slate mt-0.5 text-base">{{ subtitle }}</p>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,300px)_minmax(0,1fr)_minmax(0,320px)]"
        >
            <figure
                class="bg-ink shadow-card relative min-h-64 overflow-hidden rounded-lg lg:min-h-[450px]"
            >
                <img
                    v-if="image"
                    :src="image.url"
                    :alt="image.alt ?? ''"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover opacity-90"
                />
                <figcaption
                    v-if="quote"
                    class="absolute inset-x-0 bottom-0 p-5"
                >
                    <p
                        class="font-heading text-xl font-semibold text-white italic"
                    >
                        {{ quote }}
                    </p>
                    <span class="bg-gold mt-2 block h-1 w-16 rounded-full" />
                </figcaption>
            </figure>

            <div
                class="border-line bg-surface shadow-card rounded-lg border p-5"
            >
                <div class="mb-3 flex items-center gap-2">
                    <ClipboardCheck
                        class="text-brand-600 size-6"
                        aria-hidden="true"
                    />
                    <h2 class="text-ink font-heading text-lg font-semibold">
                        Lesson Summary
                    </h2>
                </div>

                <ul class="flex list-none flex-col">
                    <li
                        v-for="(row, index) in summary?.rows ?? []"
                        :key="index"
                        class="border-line flex items-center gap-3 border-b py-3 last:border-b-0"
                    >
                        <span
                            class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
                        >
                            <component
                                :is="
                                    rowIcon[row.type as BlockType] ?? ListChecks
                                "
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="text-ink block font-semibold">
                                {{ row.title }}
                            </span>
                            <span class="text-ink-slate block text-sm">
                                {{ row.subtitle }}
                            </span>
                        </span>
                        <span
                            v-if="row.done"
                            class="bg-success grid size-6 shrink-0 place-items-center rounded-full text-white"
                        >
                            <Check class="size-4" aria-hidden="true" />
                        </span>
                    </li>
                </ul>
            </div>

            <div class="flex min-w-0 flex-col gap-4">
                <div
                    class="border-line bg-surface shadow-card rounded-lg border p-5"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <TrendingUp
                            class="text-brand-600 size-5"
                            aria-hidden="true"
                        />
                        <h2
                            class="text-ink font-heading text-base font-semibold"
                        >
                            Your Progress
                        </h2>
                        <Info
                            class="text-ink-faint size-4"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative grid shrink-0 place-items-center">
                            <ProgressRing
                                :value="percent"
                                :size="96"
                                :stroke="10"
                            />
                            <span
                                class="text-ink-royal font-heading absolute text-xl font-bold"
                            >
                                {{ percent }}%
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-ink font-semibold">
                                {{ summary?.lessonsCompleted ?? 0 }} of
                                {{ summary?.lessonsTotal ?? 0 }} lessons
                                completed
                            </p>
                            <p class="text-ink-slate mt-1 text-sm">
                                Keep going! You're on the right track.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="encouragement"
                    class="bg-success-tint flex items-start gap-3 rounded-lg p-5"
                >
                    <Lightbulb
                        class="text-gold size-6 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="text-ink leading-7">{{ encouragement }}</p>
                </div>

                <div
                    v-if="!preview"
                    class="border-line bg-surface shadow-card rounded-lg border p-5"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <ArrowRight
                            class="text-brand-600 size-5"
                            aria-hidden="true"
                        />
                        <h2
                            class="text-ink font-heading text-base font-semibold"
                        >
                            What's Next?
                        </h2>
                    </div>
                    <Link
                        :href="nextUrl ?? ''"
                        title="الانتقال إلى الدرس التالي"
                        class="bg-brand-600 hover:bg-brand-700 shadow-btn focus-visible:ring-brand-600/30 font-heading flex min-h-11 w-full items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
                    >
                        Continue to Next Lesson
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <Link
                        :href="summary?.lessonsUrl ?? ''"
                        title="العودة إلى قائمة الدروس"
                        class="border-line text-ink hover:bg-app-alt focus-visible:ring-brand-600/30 mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-md border px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    >
                        <House class="size-4" aria-hidden="true" />
                        Back to My Lessons
                    </Link>
                </div>
                <div
                    v-else
                    class="border-line bg-brand-50/40 text-ink-slate rounded-lg border border-dashed p-5 text-center text-sm"
                >
                    Preview complete. Return to the lesson editor to continue
                    managing this lesson.
                </div>
            </div>
        </div>

        <div
            v-if="closingQuote"
            class="bg-brand-50 flex items-center gap-3 rounded-lg px-5 py-4"
        >
            <Quote class="text-brand-300 size-6 shrink-0" aria-hidden="true" />
            <p class="text-ink italic">{{ closingQuote }} — Guesvia</p>
        </div>
    </div>
</template>
