<script setup lang="ts">
import { Calendar, ChevronDown, Download, RotateCcw } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { reactive } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type { ReportsFilters, ReportSelectOption } from '@/types';

type Props = {
    filters: ReportsFilters;
    class?: HTMLAttributes['class'];
};

type FilterKey =
    | 'hotel'
    | 'department'
    | 'employee'
    | 'activityType'
    | 'completionStatus';

const props = defineProps<Props>();

const values = reactive<Record<FilterKey, string>>({
    hotel: props.filters.hotel,
    department: props.filters.department,
    employee: props.filters.employee,
    activityType: props.filters.activityType,
    completionStatus: props.filters.completionStatus,
});

const dateRange = reactive({ value: props.filters.range });

const filterFields: Array<{
    key: FilterKey;
    label: string;
    options: ReportSelectOption[];
}> = [
    { key: 'hotel', label: 'Hotel', options: props.filters.hotels },
    {
        key: 'department',
        label: 'Department',
        options: props.filters.departments,
    },
    { key: 'employee', label: 'Employee', options: props.filters.employees },
    {
        key: 'activityType',
        label: 'Activity Type',
        options: props.filters.activityTypes,
    },
    {
        key: 'completionStatus',
        label: 'Completion Status',
        options: props.filters.completionStatuses,
    },
];

function onSelect(target: FilterKey | 'range', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'range') {
        dateRange.value = value;
        return;
    }

    values[target] = value;
}

function resetFilters(): void {
    values.hotel = props.filters.hotel;
    values.department = props.filters.department;
    values.employee = props.filters.employee;
    values.activityType = props.filters.activityType;
    values.completionStatus = props.filters.completionStatus;
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-2.5', props.class)">
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <div class="relative min-w-0 sm:w-[260px]">
                <Calendar
                    aria-hidden="true"
                    class="text-brand-700 absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Select
                    :model-value="dateRange.value"
                    @update:model-value="onSelect('range', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface shadow-card h-10 rounded-md ps-9 pe-3 text-[12.5px] font-medium"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.ranges"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white"
                @click="notifyComingSoon('Export data')"
            >
                <Download class="size-4" aria-hidden="true" />
                Export Data
                <ChevronDown class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <div class="reports-filters-grid grid gap-2">
            <div
                v-for="field in filterFields"
                :key="field.key"
                class="border-line bg-surface shadow-card rounded-md border px-3 pt-[7px] pb-[5px]"
            >
                <p class="text-brand-900 text-[11px] leading-4 font-semibold">
                    {{ field.label }}
                </p>
                <Select
                    :model-value="values[field.key]"
                    @update:model-value="onSelect(field.key, $event)"
                >
                    <SelectTrigger
                        class="text-ink-indigo h-6 border-0 px-0 py-0 text-[12.5px] font-medium shadow-none focus-visible:ring-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 shadow-card h-[52px] gap-2 rounded-md px-4 text-[12.5px] font-semibold"
                @click="resetFilters"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                Reset Filters
            </Button>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1280px) {
    .reports-filters-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr)) 164px;
    }
}
</style>
