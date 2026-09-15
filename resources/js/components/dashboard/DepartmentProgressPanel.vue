<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import type { ProgressTone } from '@/components/data/ProgressBar.vue';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type { DepartmentProgress } from '@/types';

type Props = {
    departments: DepartmentProgress[];
};

const props = defineProps<Props>();

const topPercent = computed(() =>
    Math.max(0, ...props.departments.map((d) => d.percent)),
);

/** Finished → green; the leading department → brand blue; the rest azure. */
function toneFor(percent: number): ProgressTone {
    if (percent >= 100) {
        return 'success';
    }

    return percent > 0 && percent === topPercent.value ? 'brand' : 'azure';
}
</script>

<template>
    <PanelCard
        title="Progress by Department"
        title-id="department-progress"
        class="pe-3.5 pt-1.5"
        title-class="pb-1 text-[14px] tracking-tight"
        body-class="mt-0"
    >
        <template #actions>
            <button
                type="button"
                class="text-brand-700 hover:text-brand-600 focus-visible:ring-brand-600/40 relative -mx-1 mb-1 inline-flex shrink-0 items-center gap-1 rounded-sm px-1 text-[12px] font-medium tracking-tight transition-colors before:absolute before:-inset-x-2 before:-inset-y-3 focus-visible:ring-2 focus-visible:outline-none md:before:hidden"
                @click="notifyComingSoon('Department progress')"
            >
                View Details
                <ArrowRight
                    class="size-3"
                    :stroke-width="2.25"
                    aria-hidden="true"
                />
            </button>
        </template>

        <ul class="grid grid-cols-[max-content_minmax(0,1fr)_auto]">
            <li
                v-for="department in departments"
                :key="department.id"
                class="col-span-3 grid h-[24.7px] grid-cols-subgrid items-center"
            >
                <span
                    class="text-ink/80 truncate pe-2 text-[12px] tracking-tight"
                >
                    {{ department.name }}
                </span>
                <ProgressBar
                    :value="department.percent"
                    :tone="toneFor(department.percent)"
                    :label="`${department.name} training progress`"
                    class="bg-line h-2.5"
                />
                <span
                    :class="
                        cn(
                            'ps-2.5 text-end text-[12px] tracking-tight',
                            department.percent > 0 &&
                                department.percent === topPercent
                                ? 'text-ink font-semibold'
                                : 'text-ink/80',
                        )
                    "
                >
                    {{ department.percent }}%
                </span>
            </li>
        </ul>
    </PanelCard>
</template>
