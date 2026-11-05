<script setup lang="ts">
import { BookOpen, Eye, Link2, Users } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { EssentialsStudioStats } from '@/types';

/*
 * The English Essentials studio at a glance (client request 2026-11-05):
 * visible topics, published lessons, employees who practised and the
 * department steps that link into Essentials.
 */
type Props = {
    stats: EssentialsStudioStats;
};

const props = defineProps<Props>();

const tiles = computed(
    (): { icon: Component; tone: string; value: string; label: string }[] => [
        {
            icon: Eye,
            tone: 'bg-success-tint text-success',
            value: `${props.stats.visibleTopics} / ${props.stats.topics}`,
            label: tk('Topics visible'),
        },
        {
            icon: BookOpen,
            tone: 'bg-brand-50 text-brand-600',
            value: `${props.stats.publishedLessons} / ${props.stats.lessons}`,
            label: tk('Lessons published'),
        },
        {
            icon: Users,
            tone: 'bg-ai-tint text-ai',
            value: String(props.stats.learners),
            label: tk('Employees practised'),
        },
        {
            icon: Link2,
            tone: 'bg-aqua-tint text-aqua',
            value: String(props.stats.helpLinks),
            label: tk('Links from lessons'),
        },
    ],
);
</script>

<template>
    <ul class="grid list-none grid-cols-2 gap-3 xl:grid-cols-4">
        <li
            v-for="tile in tiles"
            :key="tile.label"
            class="border-line bg-surface shadow-card flex min-w-0 items-center gap-3 rounded-lg border p-3.5"
        >
            <span
                :class="
                    cn(
                        'grid size-11 shrink-0 place-items-center rounded-xl',
                        tile.tone,
                    )
                "
                aria-hidden="true"
            >
                <component :is="tile.icon" class="size-[22px]" />
            </span>
            <span class="min-w-0">
                <span
                    class="font-heading text-brand-800 block text-[20px] leading-7 font-bold tabular-nums"
                >
                    {{ tile.value }}
                </span>
                <span class="text-ink-slate block text-[12px] leading-4">
                    {{ $t(tile.label) }}
                </span>
            </span>
        </li>
    </ul>
</template>
