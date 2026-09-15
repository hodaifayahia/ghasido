<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    Download,
    EllipsisVertical,
    FileSpreadsheet,
    FileText,
    Search,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
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
    ReportEmployeeRow,
    ReportExportAction,
    ReportExportActionTone,
    ReportPagination,
    ReportsTab,
    ReportsTabKey,
    ReportRowStatus,
} from '@/types';

type Props = {
    tabs: ReportsTab[];
    activeTab: ReportsTabKey;
    rows: ReportEmployeeRow[];
    pagination: ReportPagination;
    search: string;
    includeDetailedAnswers: boolean;
    exportActions: ReportExportAction[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const activeTab = ref<ReportsTabKey>(props.activeTab);
const search = ref(props.search);
const perPage = ref(String(props.pagination.currentPerPage));

const statusTone: Record<ReportRowStatus, string> = {
    active: 'bg-success-tint text-success-text',
    in_progress: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
    completed: 'bg-brand-100/70 text-brand-700',
};

const exportTone: Record<ReportExportActionTone, string> = {
    excel: 'border-excel/35 text-excel hover:bg-excel-tint',
    brand: 'border-brand-200 text-brand-700 hover:bg-brand-50',
    danger: 'border-danger/25 text-danger-text hover:bg-danger-tint',
};

const exportIcon: Record<ReportExportActionTone, Component> = {
    excel: FileSpreadsheet,
    brand: FileText,
    danger: Download,
};

function onPerPageSelect(value: AcceptableValue): void {
    if (typeof value === 'string') {
        perPage.value = value;
    }
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card rounded-lg border p-3',
                props.class,
            )
        "
    >
        <div
            class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="flex min-w-0 gap-2 overflow-x-auto pb-1 xl:pb-0">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="
                        cn(
                            'inline-flex h-8 shrink-0 items-center rounded-md border px-3 text-[11.5px] font-semibold whitespace-nowrap transition-colors duration-150',
                            activeTab === tab.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/45 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="relative min-w-0 xl:w-[290px]">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search by name, department..."
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>
        </div>

        <div class="border-line/80 mt-3 overflow-hidden rounded-lg border">
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-9 py-2 ps-3 pe-2 text-start">
                                <Checkbox :model-value="false" />
                            </th>
                            <th class="w-10 px-2 py-2 text-start">#</th>
                            <th class="w-[162px] px-2 py-2 text-start">
                                Employee Name
                            </th>
                            <th class="w-[118px] px-2 py-2 text-start">
                                Department
                            </th>
                            <th class="w-[80px] px-2 py-2 text-start">
                                Pre-test Score (%)
                            </th>
                            <th class="w-[80px] px-2 py-2 text-start">
                                Post-test Score (%)
                            </th>
                            <th class="w-[82px] px-2 py-2 text-start">
                                Lessons Completed
                            </th>
                            <th class="w-[82px] px-2 py-2 text-start">
                                AI Scenarios Completed
                            </th>
                            <th class="w-[88px] px-2 py-2 text-start">
                                Last Activity
                            </th>
                            <th class="w-[92px] px-2 py-2 text-start">
                                Status
                            </th>
                            <th class="w-[116px] px-2 py-2 text-start">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12px]">
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="border-line/80 hover:bg-brand-50/35 border-t"
                        >
                            <td class="py-[7px] ps-3 pe-2 align-middle">
                                <Checkbox :model-value="false" />
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.rank }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-2.5">
                                    <Avatar class="size-6.5">
                                        <AvatarFallback
                                            class="bg-brand-100 font-heading text-brand-700 text-[10px] font-semibold"
                                        >
                                            {{ row.initials }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        class="text-brand-900 truncate font-medium"
                                    >
                                        {{ row.name }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.department }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.preScore }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.postScore }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.lessonsCompleted }} /
                                {{ row.lessonsTotal }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.scenariosCompleted }} /
                                {{ row.scenariosTotal }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ row.lastActivity }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[88px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            statusTone[row.status],
                                        )
                                    "
                                >
                                    {{ row.statusLabel }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-8 min-w-[92px] items-center justify-center rounded-md border px-3 text-[11.5px] font-semibold"
                                        :aria-label="`View details for ${row.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${row.name} details`,
                                            )
                                        "
                                    >
                                        View Details
                                    </button>
                                    <button
                                        type="button"
                                        class="text-ink-faint hover:bg-brand-50 inline-flex size-7 items-center justify-center rounded-md"
                                        :aria-label="`More actions for ${row.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${row.name} actions`,
                                            )
                                        "
                                    >
                                        <EllipsisVertical
                                            class="size-3.5"
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
                <li v-for="row in rows" :key="row.id" class="bg-surface p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <Avatar class="size-8">
                                <AvatarFallback
                                    class="bg-brand-100 font-heading text-brand-700 text-[11px] font-semibold"
                                >
                                    {{ row.initials }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0">
                                <p
                                    class="font-heading text-brand-800 truncate text-[15px] font-semibold"
                                >
                                    {{ row.name }}
                                </p>
                                <p class="text-ink-muted text-[13px]">
                                    {{ row.department }}
                                </p>
                            </div>
                        </div>
                        <Checkbox :model-value="false" />
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Pre/Post:</span
                            >
                            {{ row.preScore }} / {{ row.postScore }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Lessons:</span
                            >
                            {{ row.lessonsCompleted }} / {{ row.lessonsTotal }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >AI Scenarios:</span
                            >
                            {{ row.scenariosCompleted }} /
                            {{ row.scenariosTotal }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Last Activity:</span
                            >
                            {{ row.lastActivity }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                                    statusTone[row.status],
                                )
                            "
                        >
                            {{ row.statusLabel }}
                        </span>
                        <button
                            type="button"
                            class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-9 items-center justify-center rounded-md border px-3 text-[12px] font-semibold"
                            :aria-label="`View details for ${row.name}`"
                            @click="notifyComingSoon(`${row.name} details`)"
                        >
                            View Details
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <div
            class="mt-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between xl:flex-1"
            >
                <p class="text-ink-muted text-[12.5px] leading-5">
                    Showing {{ pagination.from }}-{{ pagination.to }} of
                    {{ pagination.total }} employees
                </p>

                <nav
                    aria-label="Reports pagination"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <button
                        type="button"
                        class="text-brand-700 hover:bg-brand-50 inline-flex size-8 items-center justify-center rounded-md"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </button>

                    <template
                        v-for="page in pagination.pages"
                        :key="String(page)"
                    >
                        <span
                            v-if="page === 'ellipsis'"
                            class="text-ink-muted inline-flex size-8 items-center justify-center"
                        >
                            ...
                        </span>
                        <button
                            v-else
                            type="button"
                            :class="
                                cn(
                                    'inline-flex size-8 items-center justify-center rounded-md text-[12px] font-semibold',
                                    page === pagination.currentPage
                                        ? 'bg-brand-600 shadow-btn text-white'
                                        : 'text-brand-700 hover:bg-brand-50',
                                )
                            "
                        >
                            {{ page }}
                        </button>
                    </template>

                    <button
                        type="button"
                        class="text-brand-700 hover:bg-brand-50 inline-flex size-8 items-center justify-center rounded-md"
                    >
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </nav>
            </div>

            <div class="text-ink-muted flex items-center gap-2 text-[12px]">
                <span>Rows per page</span>
                <Select
                    :model-value="perPage"
                    @update:model-value="onPerPageSelect"
                >
                    <SelectTrigger
                        class="border-line bg-surface h-8 w-[72px] rounded-md px-3 text-[12px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in pagination.perPageOptions"
                            :key="option"
                            :value="String(option)"
                            class="text-[12px]"
                        >
                            {{ option }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div
            class="mt-5 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
            >
                <p class="text-brand-800 text-[13px] font-semibold">
                    Export Selected Data:
                </p>
                <button
                    v-for="action in exportActions"
                    :key="action.id"
                    type="button"
                    :class="
                        cn(
                            'bg-surface inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 text-[12.5px] font-semibold transition-colors duration-150',
                            exportTone[action.tone],
                        )
                    "
                    @click="notifyComingSoon(action.label)"
                >
                    <component
                        :is="exportIcon[action.tone]"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ action.label }}
                </button>
            </div>

            <label
                class="text-brand-900 flex items-center gap-2 rounded-md text-[12.5px]"
            >
                <Checkbox :model-value="includeDetailedAnswers" />
                <span>Include detailed answers</span>
            </label>
        </div>
    </section>
</template>
