<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Check, Lock } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { home } from '@/routes/learn';
import type { CourseOutline, CourseTone } from '@/types';
import MeaningRow from '@/components/learning/meaning/MeaningRow.vue';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * One course on My Lessons (JOURNEY-01, JOURNEY-03, LESSON-04, PROG-02):
 * its units and lessons as rows. A locked lesson (Pre-test not yet
 * submitted) is not a link at all — the server refuses it too — and says
 * why with an icon and words (ACC-02). No mockup covers this page; it is
 * built from the design system and the learn-support lane refines it.
 */
type Props = {
    course: CourseOutline;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const chip: Record<CourseTone, string> = {
    brand: 'bg-brand-50 text-brand-600',
    aqua: 'bg-aqua-tint text-aqua',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    gold: 'bg-gold-tint text-gold',
    danger: 'bg-danger-tint text-danger',
    ai: 'bg-ai-tint text-ai',
    azure: 'bg-azure-tint text-azure',
    sunset: 'bg-warning-tint text-sunset',
    blossom: 'bg-danger-tint text-crimson',
};

const rowClass =
    'flex min-h-14 items-center gap-3 rounded-lg border px-4 py-2 text-start';
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col rounded-lg border p-5',
                props.class,
            )
        "
        :aria-labelledby="`course-${course.id}`"
    >
        <header class="flex items-center gap-3">
            <span
                :class="
                    cn(
                        'grid size-11 shrink-0 place-items-center rounded-xl',
                        chip[course.tone],
                    )
                "
            >
                <BookOpen class="size-[22px]" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <MeaningText
                    :id="`course-${course.id}`"
                    as="h2"
                    :text="course.title"
                    class="font-heading text-ink-night text-xl leading-7 font-semibold"
                />
                <MeaningText
                    v-if="course.description"
                    :text="course.description"
                    class="text-ink-slate text-sm"
                    wrapper-class="mt-1"
                />
            </div>
        </header>

        <div
            v-for="unit in course.units"
            :key="unit.id"
            class="mt-5 first-of-type:mt-4"
        >
            <MeaningText
                as="h3"
                :text="unit.title"
                class="text-ink-slate text-xs font-semibold tracking-[0.1em] uppercase"
            />
            <ul class="mt-2 flex list-none flex-col gap-2">
                <li v-for="lesson in unit.lessons" :key="lesson.id">
                    <MeaningRow :text="lesson.title">
                        <Link
                            v-if="!lesson.locked"
                            :href="lesson.url"
                            :class="
                                cn(
                                    rowClass,
                                    'border-line hover:border-brand-300 focus-visible:ring-brand-600/40 focus-visible:ring-3 focus-visible:outline-none',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold',
                                        lesson.completed
                                            ? 'bg-success-tint text-success-text'
                                            : 'bg-brand-50 text-brand-700',
                                    )
                                "
                            >
                                <Check
                                    v-if="lesson.completed"
                                    class="size-4 stroke-[3]"
                                    aria-hidden="true"
                                />
                                <template v-else>{{
                                    lesson.position
                                }}</template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="text-ink block text-base font-semibold"
                                >
                                    {{ lesson.title }}
                                </span>
                                <span class="text-ink-slate block text-sm">
                                    {{
                                        $tc(
                                            ':count step|:count steps',
                                            lesson.stepCount,
                                        )
                                    }}<template
                                        v-if="lesson.estimatedMinutes"
                                        >{{
                                            ' · ' +
                                            $t('about :minutes min', {
                                                minutes:
                                                    lesson.estimatedMinutes,
                                            })
                                        }}</template
                                    >
                                </span>
                            </span>
                            <span
                                :class="
                                    cn(
                                        'rounded-pill px-2.5 py-1 text-xs font-semibold',
                                        lesson.completed
                                            ? 'bg-success-tint text-success-text'
                                            : 'bg-brand-50 text-brand-700',
                                    )
                                "
                            >
                                {{
                                    lesson.completed
                                        ? $t('Completed')
                                        : $t('Open (lesson status)')
                                }}
                            </span>
                            <ArrowRight
                                class="text-brand-600 size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </Link>
                        <div
                            v-else
                            :class="cn(rowClass, 'border-line bg-app-alt')"
                            aria-disabled="true"
                        >
                            <span
                                class="bg-tint-grid text-ink-slate grid size-8 shrink-0 place-items-center rounded-full"
                            >
                                <Lock class="size-4" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="text-ink block text-base font-semibold"
                                >
                                    {{ lesson.title }}
                                </span>
                                <span class="text-ink-slate block text-sm">
                                    {{
                                        $t(
                                            'Locked — complete the Pre-test first.',
                                        )
                                    }}
                                    <Link
                                        :href="home()"
                                        class="text-brand-600 font-semibold underline-offset-4 hover:underline"
                                    >
                                        {{ $t('Go to the Pre-test') }}
                                    </Link>
                                </span>
                            </span>
                            <span
                                class="rounded-pill bg-danger-tint text-danger-text px-2.5 py-1 text-xs font-semibold"
                            >
                                {{ $t('Locked') }}
                            </span>
                        </div>
                    </MeaningRow>
                </li>
            </ul>
        </div>
    </section>
</template>
