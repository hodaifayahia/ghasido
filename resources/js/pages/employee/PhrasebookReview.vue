<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Layers, RotateCcw, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Celebration from '@/components/learning/Celebration.vue';
import LearnerEmptyState from '@/components/learning/LearnerEmptyState.vue';
import PhrasebookFlashcard from '@/components/learning/PhrasebookFlashcard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { useI18n } from '@/composables/useI18n';
import { phrasebook } from '@/routes/learn';
import { review } from '@/routes/learn/phrasebook';
import type { PhrasebookEntry } from '@/types';

/*
 * Review my phrases (PHRASE-01..05; spec 0005 §3.4). Today's due cards one
 * at a time; "I knew it" moves a card up its schedule, "Practise again"
 * brings it back today. Every answer is saved on the server as it is given
 * (PROG-03), so leaving half-way loses nothing. No client mockup: built
 * from the learner card, button and progress-bar recipes.
 */
type Props = {
    cards: PhrasebookEntry[];
    totalSaved: number;
};

const props = defineProps<Props>();

const { t } = useI18n();

const index = ref(0);
const knew = ref(0);
const again = ref(0);
const saving = ref(false);

const card = computed(
    (): PhrasebookEntry | null => props.cards[index.value] ?? null,
);
const finished = computed(
    () => props.cards.length > 0 && index.value >= props.cards.length,
);
const percent = computed(() =>
    props.cards.length === 0
        ? 0
        : Math.round((index.value / props.cards.length) * 100),
);

function xsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function answer(knewIt: boolean): Promise<void> {
    const current = card.value;

    if (current === null || saving.value) {
        return;
    }

    saving.value = true;

    try {
        const response = await fetch(current.reviewUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            // The card's version: a retried request can never move it twice.
            body: JSON.stringify({
                knew: knewIt,
                version: current.reviewCount,
            }),
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        if (knewIt) {
            knew.value++;
        } else {
            again.value++;
        }

        index.value++;
    } catch {
        // Nothing moves on until the server has the answer (PROG-03).
        toast.error(
            t(
                'Your answer was not saved. Check your connection and try again.',
            ),
        );
    } finally {
        saving.value = false;
    }
}

const primaryClass =
    'bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-12 items-center justify-center gap-2 rounded-md px-6 text-[15px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60';
const secondaryClass =
    'border-line text-brand-700 bg-surface hover:bg-brand-50 focus-visible:ring-brand-600/40 inline-flex h-12 items-center justify-center gap-2 rounded-md border px-6 text-[15px] font-semibold focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60';
</script>

<template>
    <Head :title="$t('Review my phrases')" />
    <h1 class="sr-only">{{ $t('Review my phrases') }}</h1>

    <div
        class="mx-auto flex w-full max-w-2xl min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6"
    >
        <PageHeader
            :title="$t('Review my phrases')"
            :description="
                cards.length === 0
                    ? $tc(
                          ':count saved phrase|:count saved phrases',
                          totalSaved,
                      )
                    : finished
                      ? $t('Review complete')
                      : $t('Card :current of :total', {
                            current: index + 1,
                            total: cards.length,
                        })
            "
        />

        <LearnerEmptyState
            v-if="cards.length === 0"
            :icon="Layers"
            :text="
                totalSaved === 0
                    ? $t(
                          'Save words and expressions with the star in your lessons, then review them here.',
                      )
                    : $t(
                          'Nothing to review right now. Your phrases come back on the day they are due.',
                      )
            "
            :action="{ label: $t('Back to My Phrasebook'), href: phrasebook() }"
        />

        <template v-else-if="!finished && card">
            <div
                class="bg-tint-track rounded-pill h-2 w-full overflow-hidden"
                role="progressbar"
                :aria-valuenow="percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="$t('Review progress')"
            >
                <div
                    class="bg-brand-600 rounded-pill h-full transition-[width] duration-500 ease-out motion-reduce:transition-none"
                    :style="{ width: `${percent}%` }"
                />
            </div>

            <PhrasebookFlashcard :key="card.id" :entry="card" />

            <p class="text-ink-slate text-center text-[14px]">
                {{ $t('Did you remember what it means and how to say it?') }}
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    :disabled="saving"
                    :class="secondaryClass"
                    data-test="review-again-button"
                    @click="answer(false)"
                >
                    <RotateCcw class="size-5" aria-hidden="true" />
                    {{ $t('Practise again') }}
                </button>
                <button
                    type="button"
                    :disabled="saving"
                    :class="primaryClass"
                    data-test="review-knew-button"
                    @click="answer(true)"
                >
                    <Check class="size-5" aria-hidden="true" />
                    {{ $t('I knew it') }}
                </button>
            </div>
        </template>

        <section
            v-else
            class="border-line bg-surface shadow-card relative grid justify-items-center gap-4 rounded-lg border p-8 text-center"
            data-test="review-complete"
        >
            <span
                class="bg-gold-tint text-gold animate-pop-in relative grid size-16 place-items-center rounded-xl motion-reduce:animate-none"
            >
                <Trophy class="size-8" aria-hidden="true" />
                <Celebration />
            </span>
            <div class="grid gap-1">
                <h2
                    class="font-heading text-ink-royal text-[24px] font-bold tracking-[-0.02em]"
                >
                    {{ $t('Review complete') }}
                </h2>
                <p class="text-ink-slate text-[15px]">
                    {{
                        $tc(
                            'You remembered :knew of :count phrase.|You remembered :knew of :count phrases.',
                            cards.length,
                            { knew },
                        )
                    }}
                    <template v-if="again > 0">
                        {{
                            $tc(
                                ':count will come back for more practice.|:count will come back for more practice.',
                                again,
                            )
                        }}
                    </template>
                </p>
            </div>
            <div
                :class="
                    again > 0
                        ? 'grid w-full gap-3 sm:grid-cols-2'
                        : 'flex w-full justify-center'
                "
            >
                <Link :href="phrasebook()" :class="secondaryClass">
                    {{ $t('Back to My Phrasebook') }}
                </Link>
                <Link
                    v-if="again > 0"
                    :href="review()"
                    :class="primaryClass"
                    data-test="review-missed-link"
                >
                    <RotateCcw class="size-5" aria-hidden="true" />
                    {{ $t('Practise missed ones') }}
                </Link>
            </div>
        </section>
    </div>
</template>
