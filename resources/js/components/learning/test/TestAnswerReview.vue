<script setup lang="ts">
import { CircleCheck, CircleMinus, CircleX, Sparkles } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TestReviewRow, TestReviewStatus } from '@/types';

/*
 * The answer review on a finished test's result page (`show_answers`): each
 * question with the learner's answer and the correct one. Only rendered
 * after the sitting and only when the server sent it (TEST-03, TEST-04);
 * every verdict is an icon plus words, never colour alone (ACC-02).
 */
type Props = {
    rows: TestReviewRow[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const verdict: Record<
    TestReviewStatus,
    { icon: typeof CircleCheck; label: string; tone: string }
> = {
    correct: {
        icon: CircleCheck,
        label: tk('Correct'),
        tone: 'bg-success-tint text-success-text',
    },
    incorrect: {
        icon: CircleX,
        label: tk('Not correct'),
        tone: 'bg-danger-tint text-danger-text',
    },
    unanswered: {
        icon: CircleMinus,
        label: tk('Not answered'),
        tone: 'bg-app-alt text-ink-slate',
    },
    evaluated: {
        icon: Sparkles,
        label: tk('Reviewed separately'),
        tone: 'bg-ai-tint text-ai',
    },
};
</script>

<template>
    <section
        :class="cn('grid w-full gap-3 text-start', props.class)"
        data-test="test-answer-review"
    >
        <h2 class="font-heading text-ink-royal text-[18px] font-semibold">
            {{ $t('Your answers') }}
        </h2>
        <ol class="grid gap-3">
            <li
                v-for="row in rows"
                :key="row.number"
                class="border-line bg-surface grid gap-2.5 rounded-lg border p-4"
            >
                <div
                    class="flex flex-col-reverse items-start gap-2 sm:flex-row sm:justify-between"
                >
                    <!-- Test content is English: keep its own direction in
                         the Arabic interface (I18N-03). -->
                    <p
                        class="text-ink flex min-w-0 gap-1 text-[14.5px] font-medium sm:flex-1"
                    >
                        <span class="text-ink-slate shrink-0"
                            >{{ row.number }}.</span
                        >
                        <span dir="auto">{{ row.question }}</span>
                    </p>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex h-6 shrink-0 items-center gap-1 px-2.5 text-[12px] font-semibold',
                                verdict[row.status].tone,
                            )
                        "
                    >
                        <component
                            :is="verdict[row.status].icon"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{ $t(verdict[row.status].label) }}
                    </span>
                </div>
                <dl class="grid gap-1.5 text-[13.5px]">
                    <div class="grid gap-0.5 sm:grid-cols-[9rem_1fr] sm:gap-2">
                        <dt class="text-ink-slate">{{ $t('Your answer') }}</dt>
                        <dd class="text-ink break-words" dir="auto">
                            {{ row.yourAnswer ?? $t('No answer') }}
                        </dd>
                    </div>
                    <div
                        v-if="row.correctAnswer"
                        class="grid gap-0.5 sm:grid-cols-[9rem_1fr] sm:gap-2"
                    >
                        <dt class="text-ink-slate">
                            {{ $t('Correct answer') }}
                        </dt>
                        <dd
                            class="text-success-text flex items-start gap-1.5 font-medium break-words"
                        >
                            <CircleCheck
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="min-w-0" dir="auto">{{
                                row.correctAnswer
                            }}</span>
                        </dd>
                    </div>
                </dl>
            </li>
        </ol>
    </section>
</template>
