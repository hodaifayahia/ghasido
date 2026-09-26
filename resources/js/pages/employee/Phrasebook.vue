<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Layers, Star } from '@lucide/vue';
import LearnerEmptyState from '@/components/learning/LearnerEmptyState.vue';
import PhrasebookEntryCard from '@/components/learning/PhrasebookEntryCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { lessons } from '@/routes/learn';
import type { PhrasebookEntry } from '@/types';

/*
 * My Phrasebook (PHRASE-01..05; spec 0003 Part E). No client mockup covers
 * this page yet: design-system cards, refined by the learn-support lane.
 */
type Props = {
    items: PhrasebookEntry[];
    /** Spaced review (spec 0005 §3.4): cards due today and where to go. */
    review: { due: number; url: string };
};

defineProps<Props>();
</script>

<template>
    <Head :title="$t('My Phrasebook')" />
    <h1 class="sr-only">{{ $t('My Phrasebook') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('My Phrasebook')"
            :description="
                $tc(':count saved phrase|:count saved phrases', items.length)
            "
        />

        <LearnerEmptyState
            v-if="items.length === 0"
            :icon="Star"
            :text="
                $t(
                    'Tap the star on any word or expression in a lesson to keep it here.',
                )
            "
            :action="{ label: $t('Go to My Lessons'), href: lessons() }"
        />

        <section
            v-if="items.length > 0"
            class="border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center"
            data-test="phrasebook-review-banner"
        >
            <span
                class="bg-brand-50 text-brand-600 grid size-11 shrink-0 place-items-center rounded-xl"
            >
                <Layers class="size-6" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="font-heading text-ink text-base font-semibold">
                    {{
                        review.due > 0
                            ? $tc(
                                  ':count phrase is ready to review|:count phrases are ready to review',
                                  review.due,
                              )
                            : $t('All caught up')
                    }}
                </h2>
                <p class="text-ink-slate text-sm">
                    {{
                        review.due > 0
                            ? $t(
                                  'A few minutes of practice helps the words stay with you.',
                              )
                            : $t(
                                  'Your phrases come back for review on the day they are due.',
                              )
                    }}
                </p>
            </div>
            <Link
                v-if="review.due > 0"
                :href="review.url"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
                data-test="start-review-link"
            >
                {{ $t('Start review') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </section>

        <div
            v-if="items.length > 0"
            class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <PhrasebookEntryCard
                v-for="entry in items"
                :key="entry.id"
                :entry="entry"
            />
        </div>
    </div>
</template>
