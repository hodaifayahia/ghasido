<script setup lang="ts">
import {
    Calendar,
    ChevronDown,
    Download,
    FileSpreadsheet,
    FileText,
    Printer,
    RotateCcw,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, reactive, watch } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    ReportExportFormat,
    ReportsFilters,
    ReportSelectOption,
} from '@/types';

type Props = {
    filters: ReportsFilters;
    /** Whether the Export Data menu is offered (reports.export). */
    canExport: boolean;
    class?: HTMLAttributes['class'];
};

type FilterKey =
    | 'hotel'
    | 'department'
    | 'employee'
    | 'activityType'
    | 'completionStatus';

export type ReportFilterValues = {
    range: string;
    from: string;
    to: string;
    hotel: string;
    department: string;
    employee: string;
    activityType: string;
    completionStatus: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    /** A filter changed: the caller reloads page 1 with these values. */
    filter: [values: ReportFilterValues];
    reset: [];
    export: [format: ReportExportFormat];
}>();

const values = reactive<Record<FilterKey, string>>({
    hotel: props.filters.hotel,
    department: props.filters.department,
    employee: props.filters.employee,
    activityType: props.filters.activityType,
    completionStatus: props.filters.completionStatus,
});

const dateRange = reactive({
    value: props.filters.range,
    from: props.filters.from,
    to: props.filters.to,
});

// The server is the source of truth: keep the controls in step when it
// answers (a reset, the back button, a shared link).
watch(
    () => props.filters,
    (filters) => {
        values.hotel = filters.hotel;
        values.department = filters.department;
        values.employee = filters.employee;
        values.activityType = filters.activityType;
        values.completionStatus = filters.completionStatus;
        dateRange.value = filters.range;
        dateRange.from = filters.from;
        dateRange.to = filters.to;
    },
    { deep: true },
);

const filterFields = computed<
    Array<{ key: FilterKey; label: string; options: ReportSelectOption[] }>
>(() => [
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
]);

const isCustomRange = computed(() => dateRange.value === 'custom');

const exportFormats: Array<{
    format: ReportExportFormat;
    label: string;
    icon: Component;
}> = [
    { format: 'xlsx', label: 'Excel (.xlsx)', icon: FileSpreadsheet },
    { format: 'csv', label: 'CSV (.csv)', icon: FileText },
    { format: 'pdf', label: 'PDF report (print)', icon: Printer },
];

function current(): ReportFilterValues {
    return {
        range: dateRange.value,
        from: dateRange.from,
        to: dateRange.to,
        hotel: values.hotel,
        department: values.department,
        employee: values.employee,
        activityType: values.activityType,
        completionStatus: values.completionStatus,
    };
}

function onSelect(target: FilterKey | 'range', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'range') {
        dateRange.value = value;

        // A custom range waits for both dates; the presets apply at once.
        if (value !== 'custom') {
            emit('filter', current());
        }

        return;
    }

    values[target] = value;
    emit('filter', current());
}

function applyCustomRange(): void {
    if (dateRange.from !== '' && dateRange.to !== '') {
        emit('filter', current());
    }
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
                        aria-label="Date range"
                        data-test="reports-range-select"
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

            <DropdownMenu v-if="canExport">
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        data-test="reports-export-menu-button"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white"
                    >
                        <Download class="size-4" aria-hidden="true" />
                        Export Data
                        <ChevronDown class="size-4" aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="border-line bg-surface shadow-pop min-w-[200px] rounded-md p-1"
                >
                    <DropdownMenuItem
                        v-for="entry in exportFormats"
                        :key="entry.format"
                        class="focus:bg-brand-50 text-brand-900 focus:text-brand-900 [&_svg]:text-brand-700 cursor-pointer gap-2.5 rounded-sm px-2.5 py-2 text-[13px] font-medium"
                        :data-test="`reports-export-${entry.format}-item`"
                        @select="emit('export', entry.format)"
                    >
                        <component
                            :is="entry.icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ entry.label }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div
            v-if="isCustomRange"
            class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end"
        >
            <label class="text-brand-900 grid gap-1 text-[11px] font-semibold">
                From
                <Input
                    v-model="dateRange.from"
                    type="date"
                    data-test="reports-range-from-input"
                    class="border-line text-ink bg-surface h-9 w-[160px] rounded-md text-[12.5px] shadow-none"
                    @change="applyCustomRange"
                />
            </label>
            <label class="text-brand-900 grid gap-1 text-[11px] font-semibold">
                To
                <Input
                    v-model="dateRange.to"
                    type="date"
                    data-test="reports-range-to-input"
                    class="border-line text-ink bg-surface h-9 w-[160px] rounded-md text-[12.5px] shadow-none"
                    @change="applyCustomRange"
                />
            </label>
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
                        :aria-label="`Filter by ${field.label.toLowerCase()}`"
                        :data-test="`reports-${field.key}-filter`"
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
                data-test="reset-report-filters-button"
                class="border-line text-brand-700 hover:bg-brand-50 shadow-card h-[52px] gap-2 rounded-md px-4 text-[12.5px] font-semibold"
                @click="emit('reset')"
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
