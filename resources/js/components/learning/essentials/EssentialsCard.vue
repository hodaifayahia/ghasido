<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight, Lock, Sprout } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import EssentialsIconChip from '@/components/learning/essentials/EssentialsIconChip.vue';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';
import { cn } from '@/lib/utils';
import type { EssentialsSummary } from '@/types';

/*
 * English Essentials on My Lessons (client requests 2026-10-18 and
 * 2026-11-05): a vertical panel beside the department training on wide
 * screens and under it on phones, headed by the sprout the client chose,
 * listing each topic with the learner's own progress. It is never part of
 * the journey: this progress counts toward nothing (no Post-test or
 * certificate condition reads it). Arabic stays behind Show Meaning
 * (CTRL-01, CTRL-02).
 */
type Props = {
    essentials: EssentialsSummary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

function percent(done: number, total: number): number {
    return total === 0 ? 0 : Math.round((done / total) * 100);
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col overflow-hidden rounded-lg border',
                props.class,
            )
        "
        aria-labelledby="english-essentials-title"
    >
        <header
            class="from-success-tint via-brand-50/60 to-surface flex items-start gap-3 bg-linear-to-b px-5 pt-5 pb-4"
        >
            <span
                class="bg-surface text-success shadow-card grid size-11 shrink-0 place-items-center rounded-xl"
                aria-hidden="true"
            >
                <Sprout class="size-[22px]" />
            </span>
            <div class="min-w-0 flex-1">
                <MeaningText
                    id="english-essentials-title"
                    as="h2"
                    :text="$t('English Essentials')"
                    class="font-heading text-ink-night text-lg leading-7 font-semibold"
                />
                <MeaningText
                    :text="
                        $t(
                            'Practise letters, numbers, time and more whenever you need them.',
                        )
                    "
                    class="text-ink-slate text-sm"
                    wrapper-class="mt-0.5"
                />
            </div>
        </header>

        <p
            v-if="essentials.locked"
            class="bg-warning-tint text-warning-text mx-5 mb-3 flex items-start gap-2 rounded-md px-3 py-2 text-xs"
        >
            <Lock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {{ $t('The lessons open as soon as you have taken the Pre-test.') }}
        </p>

        <ul class="flex list-none flex-col gap-2 px-3 pb-3">
            <li v-for="topic in essentials.items" :key="topic.id">
                <Link
                    :href="topic.url"
                    class="hover:bg-brand-50 focus-visible:ring-brand-600/30 group flex min-h-14 items-center gap-3 rounded-md px-2 py-2 text-start transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none motion-reduce:transition-none"
                    :data-test="`essentials-topic-${topic.id}`"
                >
                    <EssentialsIconChip :icon="topic.icon" class="size-10" />
                    <span class="min-w-0 flex-1">
                        <span
                            class="text-ink-night block truncate text-sm font-semibold"
                        >
                            {{ topic.title }}
                        </span>
                        <span class="mt-1 flex items-center gap-2">
                            <span
                                class="bg-tint-track h-1.5 min-w-0 flex-1 overflow-hidden rounded-full"
                                aria-hidden="true"
                            >
                                <span
                                    class="bg-success block h-full rounded-full transition-[width] duration-700 ease-out motion-reduce:transition-none"
                                    :style="{
                                        width: `${percent(topic.lessonsCompleted, topic.lessonsTotal)}%`,
                                    }"
                                />
                            </span>
                            <span
                                class="text-ink-slate shrink-0 text-[11px] tabular-nums"
                            >
                                {{
                                    $t(':completed of :total lessons done', {
                                        completed: topic.lessonsCompleted,
                                        total: topic.lessonsTotal,
                                    })
                                }}
                            </span>
                        </span>
                    </span>
                    <ChevronRight
                        class="text-brand-600 size-4 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5 motion-reduce:transition-none rtl:rotate-180 rtl:group-hover:-translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>

        <footer class="border-line border-t px-5 py-3">
            <Link
                :href="essentials.url"
                class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/30 font-heading inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
                data-test="explore-essentials-button"
            >
                {{ $t('Explore essentials') }}
                <ArrowRight class="size-4 rtl:rotate-180" aria-hidden="true" />
            </Link>
        </footer>
    </section>
</template>
