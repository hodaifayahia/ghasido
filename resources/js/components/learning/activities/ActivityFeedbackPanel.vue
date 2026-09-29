<script setup lang="ts">
import { ArrowRight, CircleAlert, LoaderCircle, Sparkles } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import PronunciationResult from '@/components/learning/pronunciation/PronunciationResult.vue';
import { cn } from '@/lib/utils';
import type { ActivityFeedback } from '@/types';

/*
 * What a practice learner reads after a spoken or written answer
 * (client report 2026-09-29; PERF-04, AIE-01, AIE-04, ACC-02): a labelled
 * "evaluating" state while the queued check runs, then either the
 * pronunciation check (score, every word marked, the coach's tip) or the
 * AI's verdict — the score, one line per criterion, the corrections and an
 * improved answer. A failure says the answer is kept, never the provider's
 * error.
 */
type Props = {
    status: string | null;
    feedback: ActivityFeedback | null | undefined;
    class?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <section
        :class="cn('flex flex-col gap-4', $props.class)"
        aria-live="polite"
        data-test="activity-feedback"
    >
        <p
            v-if="status === 'pending' || status === 'running'"
            class="bg-app-alt text-brand-900 flex items-center gap-2 rounded-lg p-4 text-base font-semibold"
        >
            <LoaderCircle
                class="text-brand-600 size-5 shrink-0 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            {{
                feedback?.kind === 'pronunciation' ||
                (feedback === null && status === 'running')
                    ? $t('Checking your answer…')
                    : $t('Evaluating your answer…')
            }}
        </p>
        <p
            v-else-if="status === 'failed'"
            class="bg-danger-tint text-danger-text flex items-start gap-2 rounded-lg p-4 text-sm font-semibold"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            {{
                $t(
                    'We could not evaluate this answer right now. It is saved, and your trainer can review it.',
                )
            }}
        </p>

        <PronunciationResult
            v-if="feedback?.kind === 'pronunciation'"
            :result="feedback.check"
        />

        <div
            v-else-if="feedback"
            class="bg-app-alt flex flex-col gap-4 rounded-lg p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span
                    class="text-ai flex items-center gap-2 text-sm font-semibold"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    {{ $t('AI feedback') }}
                </span>
                <p
                    v-if="feedback.score !== null"
                    class="text-ink-slate text-sm"
                >
                    <span
                        class="font-heading text-brand-700 text-2xl font-bold tabular-nums"
                        >{{ Math.round(feedback.score) }}</span
                    >
                    / {{ Math.round(feedback.maxScore ?? 100) }}
                </p>
            </div>
            <p v-if="feedback.summary" class="text-ink text-base leading-7">
                {{ feedback.summary }}
            </p>
            <p
                v-if="feedback.transcript"
                class="text-ink-slate text-sm leading-6"
            >
                <span class="font-semibold">{{ $t('We heard:') }}</span>
                “{{ feedback.transcript }}”
            </p>

            <ul v-if="feedback.criteria.length > 0" class="grid gap-3">
                <li
                    v-for="criterion in feedback.criteria"
                    :key="criterion.key"
                    class="grid gap-1"
                >
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="text-ink font-semibold">
                            {{ criterion.label }}
                        </span>
                        <span
                            v-if="criterion.score !== null"
                            class="text-brand-700 font-semibold tabular-nums"
                        >
                            {{ criterion.score }} / 100
                        </span>
                    </div>
                    <ProgressBar
                        v-if="criterion.score !== null"
                        :value="criterion.score"
                        :label="criterion.label"
                        class="h-2"
                    />
                    <p
                        v-if="criterion.comment"
                        class="text-ink-slate text-sm leading-6"
                    >
                        {{ criterion.comment }}
                    </p>
                </li>
            </ul>

            <div v-if="feedback.corrections.length > 0" class="grid gap-2">
                <h3 class="font-heading text-brand-900 text-base font-semibold">
                    {{ $t('Corrections') }}
                </h3>
                <ul class="grid gap-2">
                    <li
                        v-for="(correction, index) in feedback.corrections"
                        :key="index"
                        class="border-line bg-surface grid gap-1 rounded-md border p-3 text-sm"
                    >
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="text-danger-text line-through">
                                {{ correction.original }}
                            </span>
                            <ArrowRight
                                class="text-ink-faint size-4 rtl:rotate-180"
                                :aria-label="$t('becomes')"
                            />
                            <span class="text-success-text font-semibold">
                                {{ correction.corrected }}
                            </span>
                        </p>
                        <p
                            v-if="correction.note"
                            class="text-ink-slate leading-6"
                        >
                            {{ correction.note }}
                        </p>
                    </li>
                </ul>
            </div>

            <div v-if="feedback.betterAnswer" class="grid gap-2">
                <h3 class="font-heading text-brand-900 text-base font-semibold">
                    {{ $t('A better answer') }}
                </h3>
                <p
                    class="border-line bg-surface text-ink rounded-md border p-3 text-base leading-7 whitespace-pre-line"
                >
                    {{ feedback.betterAnswer }}
                </p>
            </div>
        </div>
    </section>
</template>
