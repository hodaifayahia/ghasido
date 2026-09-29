<script setup lang="ts">
import {
    BookOpen,
    Bot,
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    RotateCcw,
    Search,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { ref, watch } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import DepartmentRowActions from '@/components/departments/DepartmentRowActions.vue';
import { useCan } from '@/composables/useCan';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import type {
    DepartmentFilters,
    DepartmentPagination,
    DepartmentQuotaState,
    DepartmentRecord,
    DepartmentRowAction,
    DepartmentScope,
    DepartmentStatus,
} from '@/types';

type Props = {
    filters: DepartmentFilters;
    departments: DepartmentRecord[];
    pagination: DepartmentPagination;
    /** True while a partial reload is in flight, for the skeleton rows. */
    loading?: boolean;
};

const props = withDefaults(defineProps<Props>(), { loading: false });

export type DepartmentFilterValues = {
    search: string;
    scope: string;
    status: string;
};

const emit = defineEmits<{
    /** Search or a filter changed: the caller reloads page 1. */
    filter: [values: DepartmentFilterValues];
    page: [page: number];
    action: [action: DepartmentRowAction, department: DepartmentRecord];
}>();

const { can } = useCan();
const { t, tc } = useI18n();
const canManage = can('departments.manage');

const search = ref(props.filters.search);
const scope = ref(props.filters.scope);
const status = ref(props.filters.status);

// The server is the source of truth for the filters; keep the controls in
// step when it answers (a Reset, a back button, a shared link).
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        scope.value = filters.scope;
        status.value = filters.status;
    },
    { deep: true },
);

function current(): DepartmentFilterValues {
    return {
        search: search.value,
        scope: scope.value,
        status: status.value,
    };
}

// Debounced so a keystroke does not repaint the page.
watchDebounced(
    search,
    (value) => {
        if (value !== props.filters.search) {
            emit('filter', current());
        }
    },
    { debounce: 300 },
);

const statusText: Record<DepartmentStatus, string> = {
    active: tk('Active'),
    review: tk('In Review'),
    draft: tk('Draft'),
};

const statusTone: Record<DepartmentStatus, string> = {
    active: 'bg-success-tint text-success-text',
    review: 'bg-warning-tint text-warning-text',
    draft: 'bg-brand-100/70 text-brand-700',
};

const archivedTone = 'bg-tint-grid text-ink-muted';

const scopeTone: Record<DepartmentScope, string> = {
    shared: 'bg-brand-100/65 text-brand-700',
    hotel: 'bg-ai/12 text-ai',
};

const quotaTone: Record<DepartmentQuotaState, 'brand' | 'warning'> = {
    available: 'brand',
    full: 'warning',
    over: 'warning',
};

function pillText(department: DepartmentRecord): string {
    return department.isActive
        ? t(statusText[department.status])
        : t('Archived');
}

function pillTone(department: DepartmentRecord): string {
    return department.isActive ? statusTone[department.status] : archivedTone;
}

function onSelect(target: 'scope' | 'status', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'scope') {
        scope.value = value;
    } else {
        status.value = value;
    }

    emit('filter', current());
}

function resetFilters(): void {
    search.value = '';
    scope.value = 'all-scopes';
    status.value = 'all-statuses';
    emit('filter', current());
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
    return tc(':count hotel|:count hotels', department.hotelCount);
}

const iconButton =
    'border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex items-center justify-center rounded-md border focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none';

const skeletonRows = [0, 1, 2, 3, 4, 5, 6];
</script>

<template>
    <section
        :aria-label="$t('Departments directory')"
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
                    :placeholder="$t('Search by department, focus or scope...')"
                    :aria-label="$t('Search departments')"
                    data-test="departments-search-input"
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 md:flex md:items-center">
                <Select
                    :model-value="scope"
                    @update:model-value="onSelect('scope', $event)"
                >
                    <SelectTrigger
                        :aria-label="$t('Filter by scope')"
                        data-test="departments-scope-filter"
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
                        :aria-label="$t('Filter by status')"
                        data-test="departments-status-filter"
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
                    data-test="reset-department-filters-button"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                    {{ $t('Reset') }}
                </Button>
            </div>
        </div>

        <div
            class="border-line/80 mt-2.5 overflow-hidden rounded-lg border"
            :aria-busy="loading || undefined"
        >
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="w-full min-w-[900px] table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-[4%] py-2 ps-3 pe-2 text-start">#</th>
                            <th class="w-[17%] px-2 py-2 text-start">
                                {{ $t('Department') }}
                            </th>
                            <th class="w-[12%] px-2 py-2 text-start">
                                {{ $t('Scope') }}
                            </th>
                            <th class="w-[7%] px-2 py-2 text-start">
                                {{ $t('Hotels') }}
                            </th>
                            <th class="w-[8%] px-2 py-2 text-start">
                                {{ $t('Employees') }}
                            </th>
                            <th class="w-[14%] px-2 py-2 text-start">
                                {{ $t('Seat Usage') }}
                            </th>
                            <th class="w-[8%] px-2 py-2 text-start">
                                {{ $t('Lessons') }}
                            </th>
                            <th class="w-[10%] px-2 py-2 text-start">
                                {{ $t('Assessments') }}
                            </th>
                            <th class="w-[11%] px-2 py-2 text-start">
                                {{ $t('Status') }}
                            </th>
                            <th class="w-[9%] px-2 py-2 text-start">
                                {{ $t('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <template v-if="loading && departments.length === 0">
                            <tr
                                v-for="row in skeletonRows"
                                :key="row"
                                class="border-line/80 border-t"
                            >
                                <td
                                    v-for="cell in 10"
                                    :key="cell"
                                    class="px-2 py-[11px]"
                                >
                                    <div
                                        class="bg-tint-track h-3.5 animate-pulse rounded-sm motion-reduce:animate-none"
                                    />
                                </td>
                            </tr>
                        </template>

                        <tr
                            v-else-if="departments.length === 0"
                            class="border-line/80 border-t"
                        >
                            <td colspan="10" class="px-4 py-10 text-center">
                                <p
                                    class="font-heading text-brand-900 text-[14px] font-semibold"
                                >
                                    {{
                                        $t('No departments match these filters')
                                    }}
                                </p>
                                <p class="text-ink-slate mt-1 text-[12.5px]">
                                    {{
                                        $t(
                                            'Try another search, or clear the filters to see the whole catalogue.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="border-line text-brand-700 hover:bg-brand-50 mt-3 h-9 gap-1.5 rounded-md px-3 text-[12.5px] font-semibold shadow-none"
                                    @click="resetFilters"
                                >
                                    <RotateCcw
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ $t('Clear filters') }}
                                </Button>
                            </td>
                        </tr>

                        <tr
                            v-for="department in departments"
                            v-else
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
                                            ? $t('Shared')
                                            : $t('Hotel Specific')
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
                                        :label="
                                            $t(':name seat usage', {
                                                name: department.name,
                                            })
                                        "
                                        class="h-[6px] w-[68px]"
                                    />
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle text-[11.5px]"
                            >
                                {{
                                    $tc(
                                        ':count lesson|:count lessons',
                                        department.lessonCount,
                                    )
                                }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate text-[11.5px] font-medium"
                                    >
                                        {{
                                            $tc(
                                                ':count test|:count tests',
                                                department.testCount,
                                            )
                                        }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11px]"
                                    >
                                        {{
                                            $tc(
                                                ':count AI scenario|:count AI scenarios',
                                                department.scenarioCount,
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[90px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            pillTone(department),
                                        )
                                    "
                                >
                                    {{ pillText(department) }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        :class="cn(iconButton, 'size-6.5')"
                                        :aria-label="
                                            $t('View :name', {
                                                name: department.name,
                                            })
                                        "
                                        :data-test="`department-${department.id}-view-button`"
                                        @click="
                                            emit('action', 'view', department)
                                        "
                                    >
                                        <Eye
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        :class="cn(iconButton, 'size-6.5')"
                                        :aria-label="
                                            $t('Edit :name', {
                                                name: department.name,
                                            })
                                        "
                                        :data-test="`department-${department.id}-edit-button`"
                                        @click="
                                            emit('action', 'edit', department)
                                        "
                                    >
                                        <Pencil
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        :class="cn(iconButton, 'size-6.5')"
                                        :aria-label="
                                            $t('Manage :name content', {
                                                name: department.name,
                                            })
                                        "
                                        :data-test="`department-${department.id}-content-button`"
                                        @click="
                                            emit(
                                                'action',
                                                'content',
                                                department,
                                            )
                                        "
                                    >
                                        <BookOpen
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <DepartmentRowActions
                                        :department="department"
                                        size="table"
                                        @select="
                                            emit('action', $event, department)
                                        "
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-if="departments.length === 0"
                    class="bg-surface px-4 py-10 text-center"
                >
                    <p
                        class="font-heading text-brand-900 text-[15px] font-semibold"
                    >
                        {{ $t('No departments match these filters') }}
                    </p>
                    <p class="text-ink-slate mt-1 text-[13px]">
                        {{ $t('Try another search, or clear the filters.') }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 mt-3 h-10 gap-1.5 rounded-md px-3 text-[13px] font-semibold shadow-none"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3.5" aria-hidden="true" />
                        {{ $t('Clear filters') }}
                    </Button>
                </li>
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
                                    pillTone(department),
                                )
                            "
                        >
                            {{ pillText(department) }}
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
                            <span class="text-brand-900 font-medium">{{
                                $t('Employees:')
                            }}</span>
                            {{ department.employeeCount }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Lessons:')
                            }}</span>
                            {{ department.lessonCount }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Assessments:')
                            }}</span>
                            {{
                                $t(':tests tests / :scenarios AI', {
                                    tests: department.testCount,
                                    scenarios: department.scenarioCount,
                                })
                            }}
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
                            :label="
                                $t(':name seat usage', {
                                    name: department.name,
                                })
                            "
                            class="h-2 flex-1"
                        />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <div
                            class="rounded-pill bg-ai/10 text-ai inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold"
                        >
                            <Bot class="size-3.5" aria-hidden="true" />
                            {{
                                $t(':count AI', {
                                    count: department.scenarioCount,
                                })
                            }}
                        </div>

                        <div class="ms-auto flex items-center gap-1.5">
                            <button
                                type="button"
                                :class="cn(iconButton, 'size-9')"
                                :aria-label="
                                    $t('View :name', { name: department.name })
                                "
                                @click="emit('action', 'view', department)"
                            >
                                <Eye class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                v-if="canManage"
                                type="button"
                                :class="cn(iconButton, 'size-9')"
                                :aria-label="
                                    $t('Edit :name', { name: department.name })
                                "
                                @click="emit('action', 'edit', department)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                            <DepartmentRowActions
                                :department="department"
                                size="card"
                                @select="emit('action', $event, department)"
                            />
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div
            class="text-ink-muted mt-2.5 flex flex-col gap-2 text-[12.5px] leading-5 md:flex-row md:items-center md:justify-between"
        >
            <p>
                {{
                    $t('Showing :from-:to of :total departments', {
                        from: pagination.from,
                        to: pagination.to,
                        total: pagination.total,
                    })
                }}
            </p>

            <nav
                :aria-label="$t('Departments pagination')"
                class="flex flex-wrap items-center gap-1.5"
            >
                <button
                    type="button"
                    :disabled="pagination.currentPage <= 1"
                    class="text-ink-muted hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent"
                    @click="emit('page', pagination.currentPage - 1)"
                >
                    <ChevronLeft class="size-3.5" aria-hidden="true" />
                    {{ $t('Previous') }}
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
                        :aria-current="
                            page === pagination.currentPage ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'inline-flex min-h-8 min-w-8 items-center justify-center rounded-md px-2 text-[12px] font-semibold',
                                page === pagination.currentPage
                                    ? 'bg-brand-600 text-white'
                                    : 'text-brand-700 hover:bg-brand-50',
                            )
                        "
                        @click="emit('page', page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    :disabled="pagination.currentPage >= pagination.lastPage"
                    class="text-brand-700 hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent"
                    @click="emit('page', pagination.currentPage + 1)"
                >
                    {{ $t('Next') }}
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </section>
</template>
