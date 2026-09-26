<script setup lang="ts">
import { BookOpen, Check, FilePenLine, ListChecks } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { LessonDirectoryMetric, LessonDirectoryMetricKey } from '@/types';

type Props = {
    stats: LessonDirectoryMetric[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

type MetricLook = {
    icon: Component;
    chipClass: string;
    iconClass: string;
    valueClass: string;
};

const looks: Record<LessonDirectoryMetricKey, MetricLook> = {
    totalLessons: {
        icon: BookOpen,
        chipClass: 'bg-brand-100 text-brand-600',
        iconClass: 'fill-current stroke-[1.8]',
        valueClass: 'text-brand-800',
    },
    publishedLessons: {
        icon: Check,
        chipClass: 'bg-success-tint text-success',
        iconClass: 'stroke-[3]',
        valueClass: 'text-success-text',
    },
    draftLessons: {
        icon: FilePenLine,
        chipClass: 'bg-warning-tint text-warning',
        iconClass: 'stroke-[2.2]',
        valueClass: 'text-sunset',
    },
    learningSteps: {
        icon: ListChecks,
        chipClass: 'bg-ai/14 text-ai',
        iconClass: 'stroke-[2]',
        valueClass: 'text-brand-700',
    },
};
</script>

<template>
    <ul
        role="list"
        :aria-label="$t('Lesson library summary')"
        :class="
            cn(
                'grid grid-cols-2 gap-2 pb-1',
                'md:grid-cols-2 xl:grid-cols-4',
                props.class,
            )
        "
    >
        <li v-for="stat in stats" :key="stat.key" class="min-w-0">
            <article
                class="border-line bg-surface shadow-card hover:shadow-hover flex h-full items-start gap-3 rounded-lg border px-3 pt-[11px] pb-[10px] transition duration-150 hover:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
            >
                <div
                    :class="
                        cn(
                            'mt-px grid size-11 shrink-0 place-items-center rounded-full',
                            looks[stat.key].chipClass,
                        )
                    "
                >
                    <component
                        :is="looks[stat.key].icon"
                        aria-hidden="true"
                        :class="cn('size-[22px]', looks[stat.key].iconClass)"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        :class="
                            cn(
                                'font-heading text-[24px] leading-[1.05] font-bold tracking-[-0.02em]',
                                looks[stat.key].valueClass,
                            )
                        "
                    >
                        {{ stat.value }}
                    </p>
                    <p
                        class="text-brand-900 mt-[5px] text-[11.5px] leading-[1.15rem] font-medium"
                    >
                        {{ stat.label }}
                    </p>
                    <p
                        v-if="stat.detail"
                        class="text-ink-slate mt-px text-[11.5px] leading-[1.05rem]"
                    >
                        {{ stat.detail }}
                    </p>
                </div>
            </article>
        </li>
    </ul>
</template>
