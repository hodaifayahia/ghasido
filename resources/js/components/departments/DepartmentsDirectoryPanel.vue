<script setup lang="ts">
import {
    BookOpen,
    Bot,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    Pencil,
    RotateCcw,
    Search,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type {
    DepartmentFilters,
    DepartmentPagination,
    DepartmentQuotaState,
    DepartmentRecord,
    DepartmentScope,
    DepartmentStatus,
} from '@/types';

type Props = {
    filters: DepartmentFilters;
    departments: DepartmentRecord[];
    pagination: DepartmentPagination;
};

const props = defineProps<Props>();

const search = ref(props.filters.search);
const scope = ref(props.filters.scope);
const status = ref(props.filters.status);

const statusText: Record<DepartmentStatus, string> = {
    active: 'Active',
    review: 'In Review',
    draft: 'Draft',
};

const statusTone: Record<DepartmentStatus, string> = {
    active: 'bg-success-tint text-success-text',
    review: 'bg-warning-tint text-warning-text',
    draft: 'bg-brand-100/70 text-brand-700',
};

const scopeTone: Record<DepartmentScope, string> = {
    shared: 'bg-brand-100/65 text-brand-700',
    hotel: 'bg-ai/12 text-ai',
};

const quotaTone: Record<DepartmentQuotaState, 'brand' | 'warning'> = {
    available: 'brand',
    full: 'warning',
    over: 'warning',
};

function onSelect(target: 'scope' | 'status', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'scope') {
        scope.value = value;
        return;
    }

    status.value = value;
}

function resetFilters(): void {
    search.value = props.filters.search;
    scope.value = props.filters.scope;
    status.value = props.filters.status;
}

function seatPercent(department: DepartmentRecord): number {
    if (department.totalSeats === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((department.usedSeats / department.totalSeats) * 100),
    );
}

function quotaState(department: DepartmentRecord): DepartmentQuotaState {
    if (department.usedSeats > department.totalSeats) {
        return 'over';
    }

    if (department.usedSeats === department.totalSeats) {
        return 'full';
    }

    return 'available';
}

function hotelsLabel(department: DepartmentRecord): string {
    return department.hotelCount === 1
        ? '1 hotel'
        : `${department.hotelCount} hotels`;
}
</script>

<template>
    <section
        aria-label="Departments directory"
        class="border-line bg-surface shadow-card rounded-lg border p-2.5"
    >
        <div class="flex flex-col gap-2 md:flex-row md:items-center">
            <div class="relative min-w-0 flex-1">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search by department, focus or scope..."
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 md:flex md:items-center">
                <Select
                    :model-value="scope"
                    @update:model-value="onSelect('scope', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-[148px] rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.scopes"
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
                        class="border-line text-ink bg-surface h-9 min-w-[148px] rounded-md px-3 text-[12.5px] shadow-none"
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
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-10 py-2 ps-3 pe-2 text-start">#</th>
                            <th class="w-[170px] px-2 py-2 text-start">
                                Department
                            </th>
                            <th class="w-[116px] px-2 py-2 text-start">
                                Scope
                            </th>
                            <th class="w-[78px] px-2 py-2 text-start">
                                Hotels
                            </th>
                            <th class="w-[86px] px-2 py-2 text-start">
                                Employees
                            </th>
                            <th class="w-[154px] px-2 py-2 text-start">
                                Seat Usage
                            </th>
                            <th class="w-[92px] px-2 py-2 text-start">
                                Lessons
                            </th>
                            <th class="w-[114px] px-2 py-2 text-start">
                                Assessments
                            </th>
                            <th class="w-[104px] px-2 py-2 text-start">
                                Status
                            </th>
                            <th class="w-[108px] px-2 py-2 text-start">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <tr
                            v-for="department in departments"
                            :key="department.id"
                            class="border-line/80 hover:bg-brand-50/35 border-t"
                        >
                            <td
                                class="text-ink-muted py-[7px] ps-3 pe-2 align-middle"
                            >
                                {{ department.rank }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ department.name }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11.5px]"
                                    >
                                        {{ department.focus }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[96px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            scopeTone[department.scope],
                                        )
                                    "
                                >
                                    {{
                                        department.scope === 'shared'
                                            ? 'Shared'
                                            : 'Hotel Specific'
                                    }}
                                </span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ hotelsLabel(department) }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ department.employeeCount }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-brand-900 w-14 shrink-0 text-[11.5px] font-medium"
                                    >
                                        {{ department.usedSeats }}/{{
                                            department.totalSeats
                                        }}
                                    </span>
                                    <ProgressBar
                                        :value="seatPercent(department)"
                                        :tone="
                                            quotaTone[quotaState(department)]
                                        "
                                        :label="`${department.name} seat usage`"
                                        class="h-[6px] w-[68px]"
                                    />
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle text-[11.5px]"
                            >
                                {{ department.lessonCount }} lessons
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate text-[11.5px] font-medium"
                                    >
                                        {{ department.testCount }} tests
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11px]"
                                    >
                                        {{ department.scenarioCount }} AI
                                        scenarios
                                    </span>
                                </div>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[90px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            statusTone[department.status],
                                        )
                                    "
                                >
                                    {{ statusText[department.status] }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`View ${department.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${department.name} details`,
                                            )
                                        "
                                    >
                                        <Eye
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`Edit ${department.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${department.name} settings`,
                                            )
                                        "
                                    >
                                        <Pencil
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`Manage ${department.name} content`"
                                        @click="
                                            notifyComingSoon(
                                                `${department.name} content`,
                                            )
                                        "
                                    >
                                        <BookOpen
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`More actions for ${department.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${department.name} actions`,
                                            )
                                        "
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
                    v-for="department in departments"
                    :key="department.id"
                    class="bg-surface p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="font-heading text-brand-800 text-[15px] leading-5 font-semibold"
                            >
                                {{ department.name }}
                            </p>
                            <p
                                class="text-ink-muted mt-0.5 text-[13px] leading-5"
                            >
                                {{ department.focus }}
                            </p>
                        </div>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center justify-center px-2.5 text-[11px] font-semibold',
                                    statusTone[department.status],
                                )
                            "
                        >
                            {{ statusText[department.status] }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center justify-center px-2.5 text-[11px] font-semibold',
                                    scopeTone[department.scope],
                                )
                            "
                        >
                            {{ department.scopeLabel }}
                        </span>
                        <span class="text-brand-900 text-[12px] font-medium">
                            {{ hotelsLabel(department) }}
                        </span>
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Employees:</span
                            >
                            {{ department.employeeCount }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Lessons:</span
                            >
                            {{ department.lessonCount }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Assessments:</span
                            >
                            {{ department.testCount }} tests /
                            {{ department.scenarioCount }} AI
                        </p>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-brand-900 text-[13px] font-semibold">
                            {{ department.usedSeats }}/{{
                                department.totalSeats
                            }}
                        </span>
                        <ProgressBar
                            :value="seatPercent(department)"
                            :tone="quotaTone[quotaState(department)]"
                            :label="`${department.name} seat usage`"
                            class="h-2 flex-1"
                        />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <div
                            class="rounded-pill bg-ai/10 text-ai inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold"
                        >
                            <Bot class="size-3.5" aria-hidden="true" />
                            {{ department.scenarioCount }} AI
                        </div>

                        <div class="ms-auto flex items-center gap-1.5">
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`View ${department.name}`"
                                @click="
                                    notifyComingSoon(
                                        `${department.name} details`,
                                    )
                                "
                            >
                                <Eye class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`Edit ${department.name}`"
                                @click="
                                    notifyComingSoon(
                                        `${department.name} settings`,
                                    )
                                "
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`More actions for ${department.name}`"
                                @click="
                                    notifyComingSoon(
                                        `${department.name} actions`,
                                    )
                                "
                            >
                                <EllipsisVertical
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div
            class="text-ink-muted mt-2.5 flex flex-col gap-2 text-[12.5px] leading-5 md:flex-row md:items-center md:justify-between"
        >
            <p>
                Showing {{ pagination.from }}-{{ pagination.to }} of
                {{ pagination.total }} departments
            </p>

            <nav
                aria-label="Departments pagination"
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
                        class="text-ink-muted inline-flex min-h-8 min-w-8 items-center justify-center"
                    >
                        ...
                    </span>
                    <button
                        v-else
                        type="button"
                        :class="
                            cn(
                                'inline-flex min-h-8 min-w-8 items-center justify-center rounded-md px-2 text-[12px] font-semibold',
                                page === pagination.currentPage
                                    ? 'bg-brand-600 text-white'
                                    : 'text-brand-700 hover:bg-brand-50',
                            )
                        "
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    class="text-brand-700 hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2"
                >
                    Next
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </section>
</template>
