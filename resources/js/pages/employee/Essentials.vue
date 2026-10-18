<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, Lock, Sprout } from '@lucide/vue';
import EssentialsIconChip from '@/components/learning/essentials/EssentialsIconChip.vue';
import LearnerEmptyState from '@/components/learning/LearnerEmptyState.vue';
import MeaningRow from '@/components/learning/meaning/MeaningRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { lessons } from '@/routes/learn';
import type { EssentialsTopic } from '@/types';

/*
 * English Essentials (client request 2026-10-18): the beginner topics, one
 * row each, stacked in one column like the client's sketch. Progress is
 * the learner's own and counts toward nothing in the journey. No mockup
 * covers this page; it reuses the design system's cards and chips.
 */
type Props = {
    topics: EssentialsTopic[];
    locked: boolean;
};

defineProps<Props>();
</script>

<template>
    <Head :title="$t('English Essentials')" />
    <h1 class="sr-only">{{ $t('English Essentials') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <Link
            :href="lessons()"
            class="text-brand-600 focus-visible:ring-brand-600/30 inline-flex min-h-11 items-center gap-2 self-start rounded-md text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
        >
            <ArrowLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
            {{ $t('My Lessons') }}
        </Link>

        <PageHeader
            :title="$t('English Essentials')"
            :description="$t('Choose what you need to practise.')"
        />

        <p
            v-if="locked && topics.length > 0"
            class="bg-warning-tint text-warning-text flex items-start gap-2 rounded-md px-4 py-3 text-sm"
        >
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{
                $t(
                    'You can look around now. The lessons open as soon as you have taken the Pre-test.',
                )
            }}
        </p>

        <LearnerEmptyState
            v-if="topics.length === 0"
            :icon="Sprout"
            :text="
                $t(
                    'No English Essentials have been published yet. Please check back soon.',
                )
            "
            :action="{ label: $t('Back to My Lessons'), href: lessons() }"
        />

        <ul v-else class="flex list-none flex-col gap-3">
            <li v-for="topic in topics" :key="topic.id">
                <MeaningRow :text="topic.title">
                    <Link
                        :href="topic.url"
                        class="border-line bg-surface shadow-card hover:border-brand-300 hover:shadow-hover focus-visible:ring-brand-600/40 flex min-h-[76px] items-center gap-4 rounded-lg border px-4 py-3 text-start transition-[transform,box-shadow] duration-150 ease-out hover:-translate-y-0.5 focus-visible:ring-3 focus-visible:outline-none motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                    >
                        <EssentialsIconChip :icon="topic.icon" />
                        <span class="min-w-0 flex-1">
                            <span
                                class="text-ink-night block text-base font-semibold"
                            >
                                {{ topic.title }}
                            </span>
                            <span
                                v-if="topic.description"
                                class="text-ink-slate line-clamp-2 block text-sm"
                            >
                                {{ topic.description }}
                            </span>
                            <span class="text-ink-slate block text-xs">
                                {{
                                    $t(':completed of :total lessons done', {
                                        completed: topic.lessonsCompleted,
                                        total: topic.lessonsTotal,
                                    })
                                }}
                            </span>
                        </span>
                        <ChevronRight
                            class="text-brand-600 size-5 shrink-0 rtl:rotate-180"
                            aria-hidden="true"
                        />
                    </Link>
                </MeaningRow>
            </li>
        </ul>
    </div>
</template>
