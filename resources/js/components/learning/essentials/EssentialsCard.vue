<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sprout } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';
import { cn } from '@/lib/utils';
import type { EssentialsSummary } from '@/types';

/*
 * English Essentials on My Lessons (client request 2026-10-18): a card
 * under the department training, with the sprout mark the client chose,
 * the topic count, the learner's own Essentials progress and one way in.
 * It is never part of the journey: this progress counts toward nothing.
 * Arabic stays behind Show Meaning (CTRL-01, CTRL-02).
 */
type Props = {
    essentials: EssentialsSummary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-brand-50/60 shadow-card flex min-w-0 flex-col gap-4 rounded-lg border p-5',
                props.class,
            )
        "
        aria-labelledby="english-essentials-title"
    >
        <header class="flex items-start gap-3">
            <span
                class="bg-surface text-success grid size-11 shrink-0 place-items-center rounded-xl"
                aria-hidden="true"
            >
                <Sprout class="size-[22px]" />
            </span>
            <div class="min-w-0 flex-1">
                <MeaningText
                    id="english-essentials-title"
                    as="h2"
                    :text="$t('English Essentials')"
                    class="font-heading text-ink-night text-xl leading-7 font-semibold"
                />
                <MeaningText
                    :text="
                        $t(
                            'Practise letters, numbers, time and more whenever you need them.',
                        )
                    "
                    class="text-ink-slate text-sm"
                    wrapper-class="mt-1"
                />
            </div>
        </header>

        <p class="text-ink-slate text-sm">
            {{ $tc(':count topic|:count topics', essentials.topics) }}
            <template v-if="essentials.lessonsTotal > 0">
                ·
                {{
                    $t(':completed of :total lessons done', {
                        completed: essentials.lessonsCompleted,
                        total: essentials.lessonsTotal,
                    })
                }}
            </template>
        </p>

        <Link
            :href="essentials.url"
            class="border-brand-600 text-brand-700 bg-surface hover:bg-brand-50 focus-visible:ring-brand-600/30 font-heading flex min-h-12 w-full items-center justify-center gap-2 rounded-md border-2 px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] md:w-auto md:self-start"
            data-test="explore-essentials-button"
        >
            {{ $t('Explore essentials') }}
            <ArrowRight class="size-4 rtl:rotate-180" aria-hidden="true" />
        </Link>
    </section>
</template>
