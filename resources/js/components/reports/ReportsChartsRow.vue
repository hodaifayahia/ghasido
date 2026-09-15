<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import Donut from '@/components/data/Donut.vue';
import type { DonutSegment } from '@/components/data/Donut.vue';
import { cn } from '@/lib/utils';
import type {
    ReportActivityBreakdown,
    ReportCompletionBreakdown,
    ReportGroupedBarPoint,
    ReportSingleBarPoint,
} from '@/types';

type Props = {
    prePost: ReportGroupedBarPoint[];
    completion: ReportCompletionBreakdown;
    aiPerformance: ReportSingleBarPoint[];
    activity: ReportActivityBreakdown;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const completionSegments = computed<DonutSegment[]>(() => [
    {
        label: 'Completed',
        value: props.completion.completed,
        colorClass: 'text-success',
    },
    {
        label: 'In Progress',
        value: props.completion.inProgress,
        colorClass: 'text-azure',
    },
    {
        label: 'Not Started',
        value: props.completion.notStarted,
        colorClass: 'text-ink-faint/35',
    },
]);

const activitySegments = computed<DonutSegment[]>(() => [
    {
        label: 'Active this week',
        value: props.activity.activeThisWeek,
        colorClass: 'text-success',
    },
    {
        label: 'Active this month',
        value: props.activity.activeThisMonth,
        colorClass: 'text-azure',
    },
    {
        label: 'Inactive',
        value: props.activity.inactive,
        colorClass: 'text-ink-faint/35',
    },
]);

function barHeight(value: number): string {
    return `${Math.max(8, Math.min(100, value))}%`;
}

function percent(value: number, total: number): string {
    if (total === 0) {
        return '0%';
    }

    return `${Math.round((value / total) * 100)}%`;
}
</script>

<template>
    <div :class="cn('reports-charts-layout grid gap-3', props.class)">
        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <div class="flex items-start justify-between gap-3">
                <h2
                    class="font-heading text-brand-800 text-[15px] font-semibold"
                >
                    Pre-test vs Post-test Scores
                </h2>
                <div class="text-ink-slate flex items-center gap-3 text-[11px]">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="bg-azure size-2.5 rounded-full" />
                        Pre-test
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="bg-brand-600 size-2.5 rounded-full" />
                        Post-test
                    </span>
                </div>
            </div>

            <div
                class="report-grid-lines border-line/70 bg-surface mt-3 grid grid-cols-5 gap-3 rounded-md border px-3 pt-3 pb-2"
            >
                <div
                    v-for="point in prePost"
                    :key="point.label"
                    class="flex flex-col items-center"
                >
                    <div class="flex h-[110px] items-end gap-1.5">
                        <span
                            class="bg-azure w-3 rounded-t-md"
                            :style="{ height: barHeight(point.first) }"
                        />
                        <span
                            class="bg-brand-600 w-3 rounded-t-md"
                            :style="{ height: barHeight(point.second) }"
                        />
                    </div>
                    <span
                        class="text-ink-slate mt-2 text-center text-[10.5px] leading-4"
                    >
                        {{ point.label }}
                    </span>
                </div>
            </div>
        </section>

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <h2 class="font-heading text-brand-800 text-[15px] font-semibold">
                Lesson Completion Rate
            </h2>

            <div
                class="mt-3 flex flex-col items-center gap-4 sm:flex-row sm:justify-center"
            >
                <Donut
                    :segments="completionSegments"
                    :max="100"
                    :size="146"
                    :thickness="24"
                    :gap="3"
                    rounded
                    label="Lesson completion rate"
                >
                    <p
                        class="font-heading text-brand-800 text-[28px] leading-none font-bold"
                    >
                        {{ completion.overall }}%
                    </p>
                    <p class="text-ink-slate mt-1 text-[11px] leading-4">
                        Overall Completion
                    </p>
                </Donut>

                <ul class="text-ink-slate grid gap-2.5 text-[11.5px]">
                    <li class="flex items-center gap-2">
                        <span class="bg-success size-2.5 rounded-full" />
                        Completed ({{ completion.completed }}%)
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="bg-azure size-2.5 rounded-full" />
                        In Progress ({{ completion.inProgress }}%)
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="bg-tint-grid size-2.5 rounded-full" />
                        Not Started ({{ completion.notStarted }}%)
                    </li>
                </ul>
            </div>
        </section>

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <div class="flex items-start justify-between gap-3">
                <h2
                    class="font-heading text-brand-800 text-[15px] font-semibold"
                >
                    AI Role-play Performance
                </h2>
                <span
                    class="text-ink-slate inline-flex items-center gap-1.5 text-[11px]"
                >
                    <span class="bg-ai size-2.5 rounded-full" />
                    Average Score (%)
                </span>
            </div>

            <div
                class="report-grid-lines border-line/70 bg-surface mt-3 grid grid-cols-5 gap-3 rounded-md border px-3 pt-3 pb-2"
            >
                <div
                    v-for="point in aiPerformance"
                    :key="point.label"
                    class="flex flex-col items-center"
                >
                    <div class="flex h-[110px] items-end">
                        <span
                            class="bg-ai w-5 rounded-t-md"
                            :style="{ height: barHeight(point.value) }"
                        />
                    </div>
                    <span
                        class="text-ink-slate mt-2 text-center text-[10.5px] leading-4"
                    >
                        {{ point.label }}
                    </span>
                </div>
            </div>
        </section>

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <h2 class="font-heading text-brand-800 text-[15px] font-semibold">
                Employee Activity Status
            </h2>

            <div
                class="mt-3 flex flex-col items-center gap-4 sm:flex-row sm:justify-center"
            >
                <Donut
                    :segments="activitySegments"
                    :max="activity.total"
                    :size="146"
                    :thickness="24"
                    :gap="3"
                    rounded
                    label="Employee activity status"
                >
                    <p
                        class="font-heading text-brand-800 text-[28px] leading-none font-bold"
                    >
                        {{ activity.total }}
                    </p>
                    <p class="text-ink-slate mt-1 text-[11px] leading-4">
                        Employees
                    </p>
                </Donut>

                <ul class="text-ink-slate grid gap-2.5 text-[11.5px]">
                    <li class="flex items-center gap-2">
                        <span class="bg-success size-2.5 rounded-full" />
                        Active this week {{ activity.activeThisWeek }} ({{
                            percent(activity.activeThisWeek, activity.total)
                        }})
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="bg-azure size-2.5 rounded-full" />
                        Active this month {{ activity.activeThisMonth }} ({{
                            percent(activity.activeThisMonth, activity.total)
                        }})
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="bg-tint-grid size-2.5 rounded-full" />
                        Inactive {{ activity.inactive }} ({{
                            percent(activity.inactive, activity.total)
                        }})
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>

<style scoped>
.report-grid-lines {
    background-image: repeating-linear-gradient(
        to top,
        transparent 0,
        transparent 24%,
        var(--color-tint-grid) 24%,
        var(--color-tint-grid) 25%
    );
}

@media (min-width: 768px) {
    .reports-charts-layout {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1280px) {
    .reports-charts-layout {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>
