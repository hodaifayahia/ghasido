<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import Donut from '@/components/data/Donut.vue';
import type { DonutSegment } from '@/components/data/Donut.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TrainingBreakdown, TrainingOverview } from '@/types';

type Props = {
    overview: TrainingOverview;
};

const props = defineProps<Props>();
const { t } = useI18n();

const ALL = 'all';
const ALL_LABEL = tk('All Departments');

const scope = ref<string>(ALL);

const department = computed(() =>
    props.overview.departments.find((d) => String(d.id) === scope.value),
);

const breakdown = computed<TrainingBreakdown>(
    () => department.value?.breakdown ?? props.overview.all,
);

const total = computed(
    () =>
        breakdown.value.completed +
        breakdown.value.inProgress +
        breakdown.value.notStarted,
);

const segments = computed<DonutSegment[]>(() => [
    {
        label: t('Completed'),
        value: breakdown.value.completed,
        colorClass: 'text-success',
    },
    {
        label: t('In Progress'),
        value: breakdown.value.inProgress,
        colorClass: 'text-azure',
    },
    {
        label: t('Not Started'),
        value: breakdown.value.notStarted,
        colorClass: 'text-ink-faint/50',
    },
]);

function percentOf(value: number): string {
    return total.value === 0 ? '0.0' : ((value / total.value) * 100).toFixed(1);
}

const summary = computed(() => {
    const parts = segments.value
        .map((s) => `${s.value} ${s.label} (${percentOf(s.value)}%)`)
        .join(', ');

    return t(':scope: :total employees. :parts.', {
        scope: department.value?.name ?? t(ALL_LABEL),
        total: total.value,
        parts,
    });
});

function onScopeChange(value: AcceptableValue): void {
    scope.value = typeof value === 'string' ? value : ALL;
}
</script>

<template>
    <PanelCard
        :title="$t('Training Progress Overview')"
        title-id="training-progress"
        class="pt-1.5"
        title-class="pb-1 text-[15px] tracking-tight max-xl:whitespace-normal"
        body-class="mt-[7px] @container"
    >
        <template #actions>
            <Select :model-value="scope" @update:model-value="onScopeChange">
                <SelectTrigger
                    :aria-label="$t('Filter by department')"
                    :class="
                        cn(
                            'border-line bg-surface text-ink/80 relative min-w-[123px] shrink-0 gap-1.5 rounded-sm ps-2.5 pe-2 text-[12px] tracking-tight shadow-none',
                            'data-[size=default]:h-[25px] [&_svg]:size-3',
                            'hover:border-line-strong focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3',
                            // 44px tap target on touch screens without changing the layout.
                            'before:absolute before:inset-x-0 before:-inset-y-2.5 md:before:hidden',
                        )
                    "
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent align="end" class="border-line shadow-pop">
                    <SelectItem :value="ALL" class="text-[13px]">
                        {{ $t(ALL_LABEL) }}
                    </SelectItem>
                    <SelectItem
                        v-for="d in overview.departments"
                        :key="d.id"
                        :value="String(d.id)"
                        class="text-[13px]"
                    >
                        {{ d.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>

        <div
            class="flex flex-col items-center gap-5 @min-[340px]:flex-row @min-[340px]:justify-center @min-[340px]:gap-[19px]"
        >
            <Donut
                :segments="segments"
                :label="$t('Training progress. :summary', { summary })"
                :size="164"
                :thickness="32"
                :gap="2"
                class="@min-[340px]:ms-0.5"
            >
                <p
                    class="font-heading text-ink text-[24px] leading-none font-semibold tabular-nums"
                >
                    {{ total }}
                </p>
                <p class="text-ink/85 mt-1 text-[13px] leading-none">
                    {{ $t('Employees') }}
                </p>
            </Donut>

            <ul
                class="flex w-full max-w-72 min-w-0 flex-col gap-3.5 @min-[340px]:mb-3 @min-[340px]:flex-1"
            >
                <li
                    v-for="segment in segments"
                    :key="segment.label"
                    class="flex items-center gap-2 leading-5"
                >
                    <span
                        aria-hidden="true"
                        :class="
                            cn(
                                'size-3 shrink-0 rounded-full bg-current',
                                segment.colorClass,
                            )
                        "
                    />
                    <span
                        class="text-ink/80 min-w-0 flex-1 truncate text-[12.5px]"
                    >
                        {{ segment.label }}
                    </span>
                    <span
                        class="text-ink shrink-0 text-[13px] tracking-tight whitespace-nowrap"
                    >
                        <span class="font-semibold">{{ segment.value }}</span>
                        ({{ percentOf(segment.value) }}%)
                    </span>
                </li>
            </ul>
        </div>
    </PanelCard>
</template>
