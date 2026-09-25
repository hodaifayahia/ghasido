<script setup lang="ts">
import {
    BuildingComplex,
    ChartNoAxesColumnIncreasing,
    Check,
    Play,
    User,
    UserGroup,
} from '@lucide/vue';
import type { LucideIcon, LucideProps } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import StatCard from '@/components/common/StatCard.vue';
import type { StatTone } from '@/components/common/StatCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import { cn } from '@/lib/utils';
import type { DashboardStat, DashboardStatKey } from '@/types';

type Props = {
    stats: DashboardStat[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

type Cutout = {
    /** SVG path data in Lucide's 24-unit grid. */
    d: string;
    strokeWidth: number;
};

type StatLook = {
    icon: LucideIcon;
    tone: StatTone;
    /** Lucide is outline-only; the mockup's glyphs are solid. */
    iconProps: LucideProps;
    /** Detail redrawn in white over a filled glyph. */
    cutouts?: Cutout[];
};

const look: Record<DashboardStatKey, StatLook> = {
    hotels: {
        icon: BuildingComplex,
        tone: 'brand',
        iconProps: { size: 26, fill: 'currentColor' },
        cutouts: [
            {
                d: 'M10.5 7h.01M13.5 7h.01M10.5 11h.01M13.5 11h.01M10.5 15h.01M13.5 15h.01',
                strokeWidth: 2.25,
            },
            { d: 'M6 21V10M18 21V7', strokeWidth: 1.25 },
        ],
    },
    departments: {
        icon: UserGroup,
        tone: 'ai',
        iconProps: { size: 28, fill: 'currentColor' },
    },
    employees: {
        icon: User,
        tone: 'success',
        iconProps: { size: 26, fill: 'currentColor' },
    },
    trainingStarted: {
        icon: Play,
        tone: 'azure',
        iconProps: { size: 24, fill: 'currentColor' },
    },
    trainingCompleted: {
        icon: Check,
        tone: 'success',
        iconProps: { size: 28, strokeWidth: 3.25 },
    },
    averageProgress: {
        icon: ChartNoAxesColumnIncreasing,
        tone: 'warning',
        iconProps: { size: 24, strokeWidth: 5 },
    },
};
</script>

<template>
    <ul
        role="list"
        aria-label="Key figures"
        :class="
            cn(
                'grid min-w-0 grid-cols-2 gap-2 pt-0.5 pb-3',
                'md:grid-cols-3 md:p-0',
                '2xl:grid-cols-[157fr_164fr_162fr_166fr_178fr_171fr]',
                props.class,
            )
        "
    >
        <li v-for="stat in stats" :key="stat.key" class="min-w-0">
            <StatCard
                :value="stat.value"
                :unit="stat.unit"
                :label="stat.label"
                :detail="stat.detail"
                :tone="look[stat.key].tone"
            >
                <template #icon>
                    <component
                        :is="look[stat.key].icon"
                        v-bind="look[stat.key].iconProps"
                        aria-hidden="true"
                    >
                        <path
                            v-for="cutout in look[stat.key].cutouts"
                            :key="cutout.d"
                            :d="cutout.d"
                            :stroke-width="cutout.strokeWidth"
                            class="stroke-surface"
                        />
                    </component>
                </template>

                <ProgressBar
                    v-if="stat.key === 'averageProgress'"
                    :value="stat.value"
                    tone="warning"
                    :label="stat.label"
                    class="mt-[7px] h-2.5"
                />
            </StatCard>
        </li>
    </ul>
</template>
