<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Check,
    CircleAlert,
    CircleCheck,
    Clock,
    Eye,
    History,
    Loader,
    X,
} from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import RecordingPlayer from '@/components/learning/RecordingPlayer.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
    formatAttemptDate,
    verdictLabel,
    type AttemptHistoryRow,
    type AttemptVerdict,
} from './attemptHistory';

/*
 * "Your attempts" (client report 2026-11-05; PROG-01, TEST-06, DATA-01):
 * every earlier try of one activity, check, conversation or test, newest
 * first, each with its number, date, score, feedback and answer, so the
 * learner can go back and compare. Rows come from the server, never from
 * browser storage. Verdicts are an icon AND a word (ACC-02); every action
 * is a ≥44px target (ACC-03). Skeleton while loading, a sentence when empty.
 */
type Props = {
    rows: AttemptHistoryRow[];
    /** The attempt shown on the page right now, marked in the list. */
    currentId?: number | null;
    title?: string;
    loading?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    currentId: null,
    title: undefined,
    loading: false,
});

const emit = defineEmits<{ select: [row: AttemptHistoryRow] }>();

const VERDICT: Record<AttemptVerdict, { icon: Component; class: string }> = {
    correct: { icon: Check, class: 'bg-success-tint text-success-text' },
    incorrect: { icon: X, class: 'bg-danger-tint text-danger-text' },
    evaluating: { icon: Loader, class: 'bg-warning-tint text-warning-text' },
    failed: { icon: CircleAlert, class: 'bg-danger-tint text-danger-text' },
    done: { icon: CircleCheck, class: 'bg-brand-50 text-brand-700' },
    in_progress: { icon: Clock, class: 'bg-warning-tint text-warning-text' },
};
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-4 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
        :aria-label="title ?? $t('Your attempts')"
        data-test="attempt-history"
    >
        <header class="flex items-center gap-3">
            <span
                class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
            >
                <History class="size-5" aria-hidden="true" />
            </span>
            <h2 class="font-heading text-ink-royal text-lg font-semibold">
                {{ title ?? $t('Your attempts') }}
            </h2>
            <span
                v-if="!loading && rows.length > 0"
                class="bg-app-alt text-ink-slate rounded-pill ms-auto px-2.5 py-1 text-xs font-semibold"
            >
                {{ rows.length }}
            </span>
        </header>

        <div v-if="loading" class="flex flex-col gap-3" aria-busy="true">
            <Skeleton v-for="n in 2" :key="n" class="h-20 w-full rounded-md" />
        </div>

        <p v-else-if="rows.length === 0" class="text-ink-slate text-sm">
            {{
                $t(
                    'No attempts yet. Your answers and feedback are saved here each time you try.',
                )
            }}
        </p>

        <ol v-else class="flex flex-col gap-3">
            <li
                v-for="row in rows"
                :key="row.id"
                :class="
                    cn(
                        'border-line flex min-w-0 flex-col gap-2 rounded-md border p-3',
                        row.id === currentId && 'border-brand-300 bg-brand-50',
                    )
                "
                :aria-current="row.id === currentId ? 'true' : undefined"
            >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="text-ink text-sm font-semibold">
                        {{ $t('Attempt :number', { number: row.attemptNo }) }}
                    </span>
                    <span v-if="row.date" class="text-ink-slate text-xs">
                        {{ formatAttemptDate(row.date) }}
                    </span>
                    <span
                        v-if="row.id === currentId"
                        class="text-brand-700 text-xs font-semibold"
                    >
                        {{ $t('Shown above') }}
                    </span>
                    <span class="ms-auto flex items-center gap-2">
                        <span
                            v-if="row.score !== null"
                            class="font-heading text-ink text-sm font-semibold"
                        >
                            {{ row.score }}
                        </span>
                        <span
                            v-if="row.verdict"
                            :class="
                                cn(
                                    'rounded-pill inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold',
                                    VERDICT[row.verdict].class,
                                )
                            "
                        >
                            <component
                                :is="VERDICT[row.verdict].icon"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ verdictLabel(row.verdict) }}
                        </span>
                    </span>
                </div>

                <p v-if="row.answer" class="text-ink text-sm break-words">
                    <span class="text-ink-slate font-medium">
                        {{ $t('Your answer:') }}
                    </span>
                    {{ row.answer }}
                </p>
                <p
                    v-if="row.feedback"
                    class="text-ink-slate text-sm leading-6 break-words"
                >
                    <span class="font-medium">{{ $t('Feedback:') }}</span>
                    {{ row.feedback }}
                </p>

                <RecordingPlayer
                    v-if="row.recordingUrl"
                    :src="row.recordingUrl"
                />

                <div v-if="row.id !== currentId" class="flex justify-end">
                    <Link
                        v-if="row.href"
                        :href="row.href"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/15 inline-flex min-h-11 items-center gap-2 rounded-md border px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        :data-test="`view-attempt-${row.attemptNo}-button`"
                    >
                        <Eye class="size-4" aria-hidden="true" />
                        {{ $t('View this attempt') }}
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/15 inline-flex min-h-11 items-center gap-2 rounded-md border px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        :data-test="`view-attempt-${row.attemptNo}-button`"
                        @click="emit('select', row)"
                    >
                        <Eye class="size-4" aria-hidden="true" />
                        {{ $t('View this attempt') }}
                    </button>
                </div>
            </li>
        </ol>
    </section>
</template>
