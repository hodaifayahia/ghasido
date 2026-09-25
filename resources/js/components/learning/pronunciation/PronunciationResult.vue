<script setup lang="ts">
import { Lightbulb } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { PronunciationResult, PronunciationWord } from '@/types';
import { LEVEL_STYLE, WORD_STATUS, weakWordHint } from './pronunciationStatus';

/*
 * The result of one pronunciation check (spec 0006 §5): the level and
 * score, every word of the sentence marked with an icon and a word (ACC-02),
 * the three sub-scores, then the coach's tip once it arrives. A weak word
 * is a button: tapping it opens the drill for that word alone. The Arabic
 * hint is only rendered behind Show Meaning (CTRL-01, CTRL-02).
 */
type Props = {
    result: PronunciationResult;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{ drill: [word: PronunciationWord] }>();

const meaning = useShowMeaning();

const level = computed(() =>
    props.result.level ? LEVEL_STYLE[props.result.level] : null,
);

/*
 * Nothing heard at all: one message, neutral chips, no per-word verdicts
 * and no zero score — the recording failed, not every word.
 */
const heardAnything = computed(() => props.result.level !== 'not_heard');

function isWeak(word: PronunciationWord): boolean {
    return (
        heardAnything.value &&
        word.status !== 'correct' &&
        word.status !== 'skipped'
    );
}

const weak = computed(() => props.result.words.filter(isWeak));

type ScoreRow = { label: string; value: number };

const scores = computed<ScoreRow[]>(() => {
    const rows: ScoreRow[] = [];
    const parts: [string, number | null][] = [
        ['Words', props.result.scores.words],
        ['Clarity', props.result.scores.clarity],
        ['Flow', props.result.scores.flow],
    ];

    for (const [label, value] of parts) {
        if (value !== null) {
            rows.push({ label, value });
        }
    }

    return rows;
});

const coaching = computed(() => props.result.feedbackStatus === 'pending');
</script>

<template>
    <section
        :class="
            cn('bg-app-alt flex flex-col gap-4 rounded-lg p-4', props.class)
        "
        aria-live="polite"
        data-test="pronunciation-result"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span
                v-if="level"
                :class="
                    cn(
                        'rounded-pill inline-flex min-h-8 items-center gap-1.5 px-3 text-sm font-semibold',
                        level.chip,
                    )
                "
            >
                <component
                    :is="level.icon"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ result.levelLabel ?? level.label }}
            </span>
            <p
                v-if="result.score !== null && result.level !== 'not_heard'"
                class="text-ink-slate text-sm"
            >
                <span
                    class="font-heading text-brand-700 text-2xl font-bold tabular-nums"
                    >{{ Math.round(result.score) }}</span
                >
                / 100
            </p>
        </div>

        <div>
            <p class="text-ink-slate mb-2 text-sm font-medium">
                {{ result.isDrill ? 'Your word' : 'Your sentence' }}
            </p>
            <ul class="flex flex-wrap gap-2">
                <li v-for="word in result.words" :key="word.index">
                    <button
                        v-if="isWeak(word)"
                        type="button"
                        :class="
                            cn(
                                'rounded-pill focus-visible:ring-brand-600/40 inline-flex min-h-11 items-center gap-1.5 px-3 text-base font-semibold focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]',
                                WORD_STATUS[word.status].chip,
                            )
                        "
                        :aria-label="`${word.text}: ${WORD_STATUS[word.status].label}. Practise this word`"
                        :data-test="`pronunciation-word-${word.index}`"
                        @click="emit('drill', word)"
                    >
                        <component
                            :is="WORD_STATUS[word.status].icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ word.text }}
                    </button>
                    <span
                        v-else-if="heardAnything"
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-11 items-center gap-1.5 px-3 text-base font-semibold',
                                WORD_STATUS[word.status].chip,
                            )
                        "
                        :aria-label="`${word.text}: ${WORD_STATUS[word.status].label}`"
                    >
                        <component
                            :is="WORD_STATUS[word.status].icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ word.text }}
                    </span>
                    <span
                        v-else
                        class="rounded-pill bg-tint-grid text-ink-slate inline-flex min-h-11 items-center px-3 text-base font-semibold"
                    >
                        {{ word.text }}
                    </span>
                </li>
            </ul>
        </div>

        <ul v-if="weak.length > 0" class="flex flex-col gap-1.5">
            <li
                v-for="word in weak"
                :key="`hint-${word.index}`"
                class="text-ink-slate text-sm leading-5"
            >
                <span class="text-ink font-semibold">{{ word.text }}</span>
                — {{ weakWordHint(word.status, word.heard, word.sound) }}
            </li>
            <li class="text-brand-700 text-sm font-medium">
                Tap a word to practise it on its own.
            </li>
        </ul>

        <dl v-if="heardAnything && scores.length > 0" class="grid gap-2">
            <div
                v-for="row in scores"
                :key="row.label"
                class="grid grid-cols-[4.5rem_minmax(0,1fr)_2.5rem] items-center gap-3"
            >
                <dt class="text-ink-slate text-sm">{{ row.label }}</dt>
                <dd class="contents">
                    <ProgressBar
                        :value="row.value"
                        :label="`${row.label} score`"
                        :tone="
                            row.value >= 75
                                ? 'success'
                                : row.value >= 50
                                  ? 'warning'
                                  : 'brand'
                        "
                    />
                    <span class="text-ink text-end text-sm tabular-nums">
                        {{ Math.round(row.value) }}
                    </span>
                </dd>
            </div>
        </dl>

        <div v-if="coaching" class="flex flex-col gap-2">
            <p class="text-ink-slate text-sm">Your coach is preparing a tip…</p>
            <Skeleton class="h-4 w-3/4 rounded-sm" />
            <Skeleton class="h-4 w-1/2 rounded-sm" />
        </div>

        <aside
            v-else-if="result.feedback"
            class="bg-brand-50 flex items-start gap-3 rounded-md p-4"
            data-test="pronunciation-coach"
        >
            <Lightbulb
                class="text-brand-600 mt-0.5 size-6 shrink-0 stroke-[2]"
                aria-hidden="true"
            />
            <div class="flex min-w-0 flex-col gap-2">
                <p class="font-heading text-brand-700 text-base font-semibold">
                    {{ result.feedback.headline }}
                </p>
                <ul v-if="result.feedback.tips.length > 0" class="grid gap-1">
                    <li
                        v-for="tip in result.feedback.tips"
                        :key="tip.word"
                        class="text-ink-slate text-sm leading-5"
                    >
                        <span class="text-ink font-semibold"
                            >{{ tip.word }}:</span
                        >
                        {{ tip.tip }}
                    </li>
                </ul>
                <p class="text-ink text-sm font-medium">
                    {{ result.feedback.next }}
                </p>
                <ShowMeaningButton
                    v-if="result.feedback.arabic"
                    size="sm"
                    :shown="meaning.shown.value"
                    :controls="`pronunciation-meaning-${result.id}`"
                    class="mt-1 self-start"
                    @toggle="meaning.toggle()"
                />
                <ShowMeaningPanel
                    v-if="result.feedback.arabic"
                    :id="`pronunciation-meaning-${result.id}`"
                    :shown="meaning.shown.value"
                    :arabic="result.feedback.arabic"
                />
            </div>
        </aside>
    </section>
</template>
