<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    Mail,
    Pencil,
    RotateCcw,
    Search,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    EmployeeFilters,
    EmployeePagination,
    EmployeeRecord,
    EmployeeStatus,
} from '@/types';

type Props = {
    filters: EmployeeFilters;
    employees: EmployeeRecord[];
    pagination: EmployeePagination;
};

const props = defineProps<Props>();

const search = ref(props.filters.search);
const hotel = ref(props.filters.hotel);
const department = ref(props.filters.department);
const status = ref(props.filters.status);

const selectedRows = ref<Record<number, boolean>>({
    1: true,
    2: true,
});

const statusText: Record<EmployeeStatus, string> = {
    completed: 'Completed',
    in_progress: 'In Progress',
    not_started: 'Not Started',
    inactive: 'Inactive',
};

const statusTone: Record<EmployeeStatus, string> = {
    completed: 'bg-success-tint text-success-text',
    in_progress: 'bg-brand-100/65 text-brand-700',
    not_started: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
};

const progressTone: Record<EmployeeStatus, 'azure' | 'success'> = {
    completed: 'success',
    in_progress: 'azure',
    not_started: 'azure',
    inactive: 'azure',
};

const allVisibleSelected = computed(() =>
    props.employees.every((employee) => selectedRows.value[employee.id]),
);

function onSelect(
    target: 'hotel' | 'department' | 'status',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'hotel') {
        hotel.value = value;
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    status.value = value;
}

function toggleAll(checked: boolean | 'indeterminate'): void {
    const enabled = checked === true;

    selectedRows.value = Object.fromEntries(
        props.employees.map((employee) => [employee.id, enabled]),
    );
}

function setRowSelection(id: number, checked: boolean | 'indeterminate'): void {
    selectedRows.value = {
        ...selectedRows.value,
        [id]: checked === true,
    };
}

function resetFilters(): void {
    search.value = props.filters.search;
    hotel.value = props.filters.hotel;
    department.value = props.filters.department;
    status.value = props.filters.status;
}
</script>

<template>
    <section
        aria-label="Employees directory"
        class="border-line bg-surface shadow-card rounded-lg border p-2.5"
    >
        <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
            <div class="relative min-w-0 flex-1">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search by name, username or email..."
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 xl:flex xl:items-center">
                <Select
                    :model-value="hotel"
                    @update:model-value="onSelect('hotel', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-[148px] rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.hotels"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-[156px] rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.departments"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="status"
                    @update:model-value="onSelect('status', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-[138px] rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.statuses"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12.5px] font-semibold shadow-none"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                    Reset
                </Button>
            </div>
        </div>

        <div class="border-line/80 mt-2.5 overflow-hidden rounded-lg border">
            <div class="overflow-x-auto max-md:hidden">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-9 px-3 py-2 text-center">
                                <Checkbox
                                    :model-value="allVisibleSelected"
                                    aria-label="Select all employees"
                                    @update:model-value="toggleAll"
                                />
                            </th>
                            <th class="w-10 ps-1 pe-2 text-start">#</th>
                            <th class="w-[108px] px-2 py-[7px] text-start">
                                Name
                            </th>
                            <th class="w-[74px] px-2 py-[7px] text-start">
                                Username
                            </th>
                            <th class="w-[94px] px-2 py-[7px] text-start">
                                Hotel
                            </th>
                            <th class="w-[92px] px-2 py-[7px] text-start">
                                Department
                            </th>
                            <th class="w-[142px] px-2 py-[7px] text-start">
                                Email
                            </th>
                            <th class="w-[100px] px-2 py-[7px] text-start">
                                Progress
                            </th>
                            <th class="w-[86px] px-2 py-[7px] text-start">
                                Status
                            </th>
                            <th class="w-[78px] px-2 py-[7px] text-start">
                                Last Login
                            </th>
                            <th class="w-[118px] px-2 py-[7px] text-start">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <tr
                            v-for="employee in employees"
                            :key="employee.id"
                            class="border-line/80 hover:bg-brand-50/35 border-t"
                        >
                            <td class="px-3 py-[6px] text-center align-middle">
                                <Checkbox
                                    :model-value="
                                        Boolean(selectedRows[employee.id])
                                    "
                                    :aria-label="`Select ${employee.name}`"
                                    @update:model-value="
                                        setRowSelection(employee.id, $event)
                                    "
                                />
                            </td>
                            <td class="text-ink-muted ps-1 pe-2 align-middle">
                                {{ employee.rank }}
                            </td>
                            <td
                                class="text-brand-900 px-2 py-[6px] align-middle font-medium"
                            >
                                <span class="block truncate">{{
                                    employee.name
                                }}</span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[6px] align-middle"
                            >
                                <span class="block truncate">{{
                                    employee.username
                                }}</span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[6px] align-middle"
                            >
                                <span class="block truncate">{{
                                    employee.hotel
                                }}</span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[6px] align-middle"
                            >
                                <span class="block truncate">{{
                                    employee.department
                                }}</span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[6px] align-middle"
                            >
                                <span class="block truncate">{{
                                    employee.email
                                }}</span>
                            </td>
                            <td class="px-2 py-[6px] align-middle">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-brand-900 w-8 shrink-0 text-[11.5px] font-medium"
                                    >
                                        {{ employee.progress }}%
                                    </span>
                                    <ProgressBar
                                        :value="employee.progress"
                                        :tone="progressTone[employee.status]"
                                        :label="`${employee.name} progress`"
                                        class="h-[6px] w-[56px]"
                                    />
                                </div>
                            </td>
                            <td class="px-2 py-[6px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[82px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            statusTone[employee.status],
                                        )
                                    "
                                >
                                    {{ statusText[employee.status] }}
                                </span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[6px] align-middle text-[11.5px]"
                            >
                                {{ employee.lastLogin }}
                            </td>
                            <td class="px-2 py-[6px] align-middle">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`View ${employee.name}`"
                                    >
                                        <Eye
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`Edit ${employee.name}`"
                                    >
                                        <Pencil
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`Email ${employee.name}`"
                                    >
                                        <Mail
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`More actions for ${employee.name}`"
                                    >
                                        <EllipsisVertical
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-for="employee in employees"
                    :key="employee.id"
                    class="bg-surface p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="font-heading text-brand-800 text-[15px] leading-5 font-semibold"
                            >
                                {{ employee.name }}
                            </p>
                            <p
                                class="text-ink-muted mt-0.5 text-[13px] leading-5"
                            >
                                {{ employee.username }}
                            </p>
                        </div>
                        <Checkbox
                            :model-value="Boolean(selectedRows[employee.id])"
                            :aria-label="`Select ${employee.name}`"
                            @update:model-value="
                                setRowSelection(employee.id, $event)
                            "
                        />
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Hotel:</span
                            >
                            {{ employee.hotel }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Department:</span
                            >
                            {{ employee.department }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Email:</span
                            >
                            {{ employee.email }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Last Login:</span
                            >
                            {{ employee.lastLogin }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-brand-900 text-[13px] font-semibold">
                            {{ employee.progress }}%
                        </span>
                        <ProgressBar
                            :value="employee.progress"
                            :tone="progressTone[employee.status]"
                            :label="`${employee.name} progress`"
                            class="h-2 flex-1"
                        />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center justify-center px-2.5 text-[11px] font-semibold',
                                    statusTone[employee.status],
                                )
                            "
                        >
                            {{ statusText[employee.status] }}
                        </span>

                        <div class="ms-auto flex items-center gap-1.5">
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`View ${employee.name}`"
                            >
                                <Eye class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`Edit ${employee.name}`"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`Email ${employee.name}`"
                            >
                                <Mail class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div
            class="text-ink-muted mt-2.5 flex flex-col gap-2 text-[12.5px] leading-5 lg:flex-row lg:items-center lg:justify-between"
        >
            <p>
                Showing {{ pagination.from }}-{{ pagination.to }} of
                {{ pagination.total }} employees
            </p>

            <nav
                aria-label="Employees pagination"
                class="flex flex-wrap items-center gap-1.5"
            >
                <button
                    type="button"
                    class="text-ink-muted hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2"
                >
                    <ChevronLeft class="size-3.5" aria-hidden="true" />
                    Previous
                </button>

                <template v-for="page in pagination.pages" :key="String(page)">
                    <span
                        v-if="page === 'ellipsis'"
                        class="text-ink-muted inline-flex min-w-8 justify-center px-1"
                    >
                        ...
                    </span>
                    <button
                        v-else
                        type="button"
                        :class="
                            cn(
                                'inline-flex size-8 items-center justify-center rounded-md border text-[12.5px] font-semibold',
                                page === pagination.currentPage
                                    ? 'border-brand-600 bg-brand-600 text-surface shadow-btn'
                                    : 'border-line text-brand-800 hover:bg-brand-50 bg-surface',
                            )
                        "
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex min-h-8 items-center gap-1 rounded-md border px-2.5 text-[12.5px] font-semibold"
                >
                    Next
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </section>
</template>
