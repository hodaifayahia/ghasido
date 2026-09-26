<script setup lang="ts">
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    Download,
    FileSpreadsheet,
    FileText,
    Lock,
    MessageSquareText,
    Minus,
    Search,
    SlidersHorizontal,
    TrendingDown,
    TrendingUp,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import ReportsRowActions from '@/components/reports/ReportsRowActions.vue';
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
import { useI18n } from '@/composables/useI18n';
import { intlLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    ReportDataset,
    ReportDatasetKey,
    ReportEmployeeRow,
    ReportExportAction,
    ReportExportActionTone,
    ReportExportFormat,
    ReportPagination,
    ReportResults,
    ReportRoleplayRow,
    ReportRoleplayStatus,
    ReportRowAction,
    ReportRowStatus,
    ReportScoreTarget,
    ReportsTab,
    ReportsTabKey,
} from '@/types';

type Props = {
    tabs: ReportsTab[];
    activeTab: ReportsTabKey;
    results: ReportResults;
    pagination: ReportPagination;
    search: string;
    exportActions: ReportExportAction[];
    datasets: ReportDataset[];
    canViewTranscripts: boolean;
    canExport: boolean;
    /** Adjust and re-grade AI scores (AIE-05; spec 0005 §2.5). */
    canOverrideScores?: boolean;
    /** True while a partial reload is in flight, for the skeleton rows. */
    loading?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    canOverrideScores: false,
});

const { t } = useI18n();

/** "Include detailed answers": the Employee Results export becomes the answers dataset (REP-06). */
const includeDetailedAnswers = defineModel<boolean>('includeDetailedAnswers', {
    required: true,
});

const emit = defineEmits<{
    tab: [tab: ReportsTabKey];
    search: [value: string];
    page: [page: number];
    perPage: [perPage: number];
    action: [action: ReportRowAction, row: ReportEmployeeRow];
    transcript: [row: ReportRoleplayRow];
    score: [target: ReportScoreTarget];
    export: [format: ReportExportFormat];
    download: [dataset: ReportDatasetKey, format: ReportExportFormat];
}>();

const search = ref(props.search);
const perPage = ref(String(props.pagination.currentPerPage));

// The server is the source of truth; keep the controls in step when it
// answers (a tab switch, a reset, the back button, a shared link).
watch(
    () => props.search,
    (value) => {
        search.value = value;
    },
);
watch(
    () => props.pagination.currentPerPage,
    (value) => {
        perPage.value = String(value);
    },
);

// Debounced so a keystroke does not repaint the page.
watchDebounced(
    search,
    (value) => {
        if (value !== props.search) {
            emit('search', value);
        }
    },
    { debounce: 300 },
);

function onPerPageSelect(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    perPage.value = value;
    emit('perPage', Number(value));
}

function onIncludeChange(value: boolean | 'indeterminate'): void {
    includeDetailedAnswers.value = value === true;
}

const statusTone: Record<ReportRowStatus, string> = {
    active: 'bg-success-tint text-success-text',
    in_progress: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
    completed: 'bg-brand-100/70 text-brand-700',
};

const roleplayTone: Record<ReportRoleplayStatus, string> = {
    completed: 'bg-success-tint text-success-text',
    evaluating: 'bg-warning-tint text-warning-text',
    in_progress: 'bg-brand-100/70 text-brand-700',
    abandoned: 'bg-danger-tint text-danger-text',
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

const downloadFormats: Array<{
    format: ReportExportFormat;
    label: string;
    tone: ReportExportActionTone;
}> = [
    { format: 'xlsx', label: 'Excel', tone: 'excel' },
    { format: 'csv', label: 'CSV', tone: 'brand' },
    { format: 'pdf', label: 'PDF', tone: 'danger' },
];

const emptyText = computed<string>(() => {
    switch (props.activeTab) {
        case 'detailedAnswers':
            return t('No answers match these filters');
        case 'roleplayLogs':
            return t('No attempts match these filters');
        case 'lessonProgress':
            return t('No completions match these filters');
        case 'comparison':
            return t('No rows match these filters');
        default:
            return t('No employees match these filters');
    }
});

const showingText = computed<string>(() => {
    const counts = {
        from: props.pagination.from,
        to: props.pagination.to,
        total: props.pagination.total,
    };

    switch (props.activeTab) {
        case 'detailedAnswers':
            return t('Showing :from-:to of :total answers', counts);
        case 'roleplayLogs':
            return t('Showing :from-:to of :total attempts', counts);
        case 'lessonProgress':
            return t('Showing :from-:to of :total completions', counts);
        case 'comparison':
            return t('Showing :from-:to of :total rows', counts);
        default:
            return t('Showing :from-:to of :total employees', counts);
    }
});

const columnCount = computed<number>(() => {
    switch (props.activeTab) {
        case 'detailedAnswers':
            return 9;
        case 'roleplayLogs':
            return 9;
        case 'lessonProgress':
            return 6;
        case 'comparison':
            return 7;
        default:
            return 11;
    }
});

const showsTable = computed(() => props.activeTab !== 'downloadCenter');
const isEmpty = computed(() => props.results.rows.length === 0);

function rank(index: number): number {
    return props.pagination.from + index;
}

function formatUnit(value: number, unit: 'minute' | 'second'): string {
    return new Intl.NumberFormat(intlLocale(), {
        style: 'unit',
        unit,
        unitDisplay: 'narrow',
    }).format(value);
}

function formatDuration(ms: number | null): string {
    if (ms === null) {
        return '—';
    }

    const seconds = Math.round(ms / 1000);

    return seconds < 60
        ? formatUnit(seconds, 'second')
        : `${formatUnit(Math.floor(seconds / 60), 'minute')} ${formatUnit(seconds % 60, 'second')}`;
}

function resultLabel(isCorrect: boolean | null): string {
    if (isCorrect === null) {
        return t('Pending');
    }

    return isCorrect ? t('Correct') : t('Incorrect');
}

function deltaLabel(delta: number | null): string {
    return delta === null
        ? '—'
        : t(':delta pts', { delta: `${delta > 0 ? '+' : ''}${delta}` });
}

function formatScore(score: number | null, max: number | null): string {
    if (score === null) {
        return '—';
    }

    return max === null ? String(score) : `${score} / ${max}`;
}

function percentLabel(value: number | null): string {
    return value === null ? '—' : `${value}%`;
}

function deltaIcon(delta: number | null): Component {
    if (delta === null || delta === 0) {
        return Minus;
    }

    return delta > 0 ? TrendingUp : TrendingDown;
}

function deltaClass(delta: number | null): string {
    if (delta === null || delta === 0) {
        return 'text-ink-muted';
    }

    return delta > 0 ? 'text-success-text' : 'text-danger-text';
}

const skeletonRows = [0, 1, 2, 3, 4, 5, 6];

const headCell = 'px-2 py-2 text-start';
const bodyCell = 'text-ink-muted px-2 py-[7px] align-middle';
const pill =
    'rounded-pill inline-flex min-h-5 items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap';
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card rounded-lg border p-3',
                props.class,
            )
        "
        :aria-busy="loading || undefined"
    >
        <div
            class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div
                class="flex min-w-0 gap-2 overflow-x-auto pb-1 xl:pb-0"
                role="tablist"
                :aria-label="$t('Report tabs')"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    :data-test="`reports-tab-${tab.key}`"
                    :class="
                        cn(
                            'inline-flex h-8 shrink-0 items-center rounded-md border px-3 text-[11.5px] font-semibold whitespace-nowrap transition-colors duration-150',
                            activeTab === tab.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/45 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="emit('tab', tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="showsTable" class="relative min-w-0 xl:w-[290px]">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="$t('Search by name, department...')"
                    :aria-label="$t('Search results')"
                    data-test="reports-search-input"
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>
        </div>

        <!-- Download Center: the export buttons per dataset (REP-03..08). -->
        <div
            v-if="results.tab === 'downloadCenter'"
            class="mt-3 grid gap-2 md:grid-cols-2"
            data-test="reports-download-center"
        >
            <article
                v-for="dataset in datasets"
                :key="dataset.key"
                class="border-line/80 bg-surface flex flex-col gap-3 rounded-lg border p-4"
            >
                <div class="flex items-start gap-3">
                    <div
                        :class="
                            cn(
                                'grid size-10 shrink-0 place-items-center rounded-xl',
                                dataset.anonymised
                                    ? 'bg-ai-tint text-ai'
                                    : 'bg-brand-100 text-brand-600',
                            )
                        "
                    >
                        <component
                            :is="dataset.anonymised ? Lock : FileSpreadsheet"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0">
                        <h3
                            class="font-heading text-brand-900 text-[13.5px] font-semibold"
                        >
                            {{ dataset.label }}
                        </h3>
                        <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                            {{ dataset.description }}
                        </p>
                    </div>
                </div>
                <div v-if="canExport" class="flex flex-wrap gap-2">
                    <button
                        v-for="entry in downloadFormats"
                        :key="entry.format"
                        type="button"
                        :data-test="`download-${dataset.key}-${entry.format}-button`"
                        :class="
                            cn(
                                'bg-surface inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-[12px] font-semibold transition-colors duration-150',
                                exportTone[entry.tone],
                            )
                        "
                        @click="emit('download', dataset.key, entry.format)"
                    >
                        <component
                            :is="exportIcon[entry.tone]"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{ entry.label }}
                    </button>
                </div>
                <p v-else class="text-ink-slate text-[12px]">
                    {{
                        $t('Exporting requires the reports.export permission.')
                    }}
                </p>
            </article>
        </div>

        <div
            v-else
            class="border-line/80 mt-3 overflow-hidden rounded-lg border"
        >
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            v-if="results.tab === 'employeeResults'"
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-9 py-2 ps-3 pe-2 text-start">
                                <Checkbox
                                    :model-value="false"
                                    :aria-label="$t('Select all')"
                                />
                            </th>
                            <th class="w-10 px-2 py-2 text-start">#</th>
                            <th class="w-[162px] px-2 py-2 text-start">
                                {{ $t('Employee Name') }}
                            </th>
                            <th class="w-[118px] px-2 py-2 text-start">
                                {{ $t('Department') }}
                            </th>
                            <th class="w-[80px] px-2 py-2 text-start">
                                {{ $t('Pre-test Score (%)') }}
                            </th>
                            <th class="w-[80px] px-2 py-2 text-start">
                                {{ $t('Post-test Score (%)') }}
                            </th>
                            <th class="w-[82px] px-2 py-2 text-start">
                                {{ $t('Lessons Completed') }}
                            </th>
                            <th class="w-[82px] px-2 py-2 text-start">
                                {{ $t('AI Scenarios Completed') }}
                            </th>
                            <th class="w-[88px] px-2 py-2 text-start">
                                {{ $t('Last Activity') }}
                            </th>
                            <th class="w-[92px] px-2 py-2 text-start">
                                {{ $t('Status') }}
                            </th>
                            <th class="w-[116px] px-2 py-2 text-start">
                                {{ $t('Actions') }}
                            </th>
                        </tr>
                        <tr
                            v-else-if="results.tab === 'detailedAnswers'"
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-10 py-2 ps-3 pe-2 text-start">#</th>
                            <th :class="cn(headCell, 'w-[130px]')">
                                {{ $t('Employee') }}
                            </th>
                            <th :class="cn(headCell, 'w-[120px]')">
                                {{ $t('Test / Lesson') }}
                            </th>
                            <th :class="cn(headCell, 'w-[90px]')">
                                {{ $t('Skill') }}
                            </th>
                            <th :class="headCell">{{ $t('Question') }}</th>
                            <th :class="headCell">{{ $t('Answer') }}</th>
                            <th :class="cn(headCell, 'w-[96px]')">
                                {{ $t('Result') }}
                            </th>
                            <th :class="cn(headCell, 'w-[70px]')">
                                {{ $t('Time') }}
                            </th>
                            <th :class="cn(headCell, 'w-[112px]')">
                                {{ $t('Submitted') }}
                            </th>
                        </tr>
                        <tr
                            v-else-if="results.tab === 'roleplayLogs'"
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-10 py-2 ps-3 pe-2 text-start">#</th>
                            <th :class="cn(headCell, 'w-[140px]')">
                                {{ $t('Employee') }}
                            </th>
                            <th :class="cn(headCell, 'w-[130px]')">
                                {{ $t('Scenario') }}
                            </th>
                            <th :class="cn(headCell, 'w-[62px]')">
                                {{ $t('Attempt') }}
                            </th>
                            <th :class="cn(headCell, 'w-[92px]')">
                                {{ $t('Status') }}
                            </th>
                            <th :class="cn(headCell, 'w-[64px]')">
                                {{ $t('Overall') }}
                            </th>
                            <th :class="headCell">{{ $t('Criteria') }}</th>
                            <th :class="cn(headCell, 'w-[112px]')">
                                {{ $t('Started') }}
                            </th>
                            <th :class="cn(headCell, 'w-[124px]')">
                                {{ $t('Transcript') }}
                            </th>
                        </tr>
                        <tr
                            v-else-if="results.tab === 'lessonProgress'"
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-10 py-2 ps-3 pe-2 text-start">#</th>
                            <th :class="cn(headCell, 'w-[170px]')">
                                {{ $t('Employee') }}
                            </th>
                            <th :class="cn(headCell, 'w-[130px]')">
                                {{ $t('Department') }}
                            </th>
                            <th :class="headCell">{{ $t('Course') }}</th>
                            <th :class="headCell">{{ $t('Lesson') }}</th>
                            <th :class="cn(headCell, 'w-[130px]')">
                                {{ $t('Completed') }}
                            </th>
                        </tr>
                        <tr
                            v-else
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-10 py-2 ps-3 pe-2 text-start">#</th>
                            <th :class="cn(headCell, 'w-[170px]')">
                                {{ $t('Employee') }}
                            </th>
                            <th :class="cn(headCell, 'w-[130px]')">
                                {{ $t('Department') }}
                            </th>
                            <th :class="headCell">{{ $t('Skill') }}</th>
                            <th :class="cn(headCell, 'w-[120px]')">
                                {{ $t('Pre-test') }}
                            </th>
                            <th :class="cn(headCell, 'w-[120px]')">
                                {{ $t('Post-test') }}
                            </th>
                            <th :class="cn(headCell, 'w-[96px]')">
                                {{ $t('Change') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-surface text-ink text-[12px]">
                        <template v-if="loading && isEmpty">
                            <tr
                                v-for="row in skeletonRows"
                                :key="row"
                                class="border-line/80 border-t"
                            >
                                <td
                                    v-for="cell in columnCount"
                                    :key="cell"
                                    class="px-2 py-[11px]"
                                >
                                    <div
                                        class="bg-tint-track h-3.5 animate-pulse rounded-sm motion-reduce:animate-none"
                                    />
                                </td>
                            </tr>
                        </template>

                        <tr v-else-if="isEmpty" class="border-line/80 border-t">
                            <td
                                :colspan="columnCount"
                                class="px-4 py-10 text-center"
                            >
                                <p
                                    class="font-heading text-brand-900 text-[14px] font-semibold"
                                >
                                    {{ emptyText }}
                                </p>
                                <p class="text-ink-slate mt-1 text-[12.5px]">
                                    {{
                                        $t(
                                            'Try another search, a wider date range, or reset the filters.',
                                        )
                                    }}
                                </p>
                            </td>
                        </tr>

                        <template v-else-if="results.tab === 'employeeResults'">
                            <tr
                                v-for="(row, index) in results.rows"
                                :key="row.id"
                                class="border-line/80 hover:bg-brand-50/35 border-t"
                                :data-test="`report-row-${row.id}`"
                            >
                                <td class="py-[7px] ps-3 pe-2 align-middle">
                                    <Checkbox
                                        :model-value="false"
                                        :aria-label="
                                            $t('Select :name', {
                                                name: row.name,
                                            })
                                        "
                                    />
                                </td>
                                <td :class="bodyCell">{{ rank(index) }}</td>
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
                                <td :class="bodyCell">{{ row.department }}</td>
                                <td :class="bodyCell">
                                    {{ row.preScore ?? '—' }}
                                </td>
                                <td :class="bodyCell">
                                    {{ row.postScore ?? '—' }}
                                </td>
                                <td :class="bodyCell">
                                    {{ row.lessonsCompleted }} /
                                    {{ row.lessonsTotal }}
                                </td>
                                <td :class="bodyCell">
                                    {{ row.scenariosCompleted }} /
                                    {{ row.scenariosTotal }}
                                </td>
                                <td :class="bodyCell">
                                    {{ row.lastActivity }}
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        :class="
                                            cn(
                                                pill,
                                                'min-w-[88px]',
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
                                            :aria-label="
                                                $t('View details for :name', {
                                                    name: row.name,
                                                })
                                            "
                                            :data-test="`report-${row.id}-details-button`"
                                            @click="
                                                emit('action', 'details', row)
                                            "
                                        >
                                            {{ $t('View Details') }}
                                        </button>
                                        <ReportsRowActions
                                            :row="row"
                                            :can-export="canExport"
                                            @select="
                                                emit('action', $event, row)
                                            "
                                        />
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <template v-else-if="results.tab === 'detailedAnswers'">
                            <tr
                                v-for="(row, index) in results.rows"
                                :key="row.id"
                                class="border-line/80 hover:bg-brand-50/35 border-t"
                                :data-test="`report-answer-${row.id}`"
                            >
                                <td
                                    class="text-ink-muted py-[7px] ps-3 pe-2 align-middle"
                                >
                                    {{ rank(index) }}
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ row.employee }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11px]"
                                    >
                                        {{ row.department }}
                                    </span>
                                </td>
                                <td :class="bodyCell">
                                    <span class="block truncate">{{
                                        row.context
                                    }}</span>
                                </td>
                                <td :class="bodyCell">{{ row.skill }}</td>
                                <td :class="bodyCell">
                                    <span
                                        class="line-clamp-2"
                                        :title="row.question"
                                    >
                                        {{ row.question }}
                                    </span>
                                </td>
                                <td :class="bodyCell">
                                    <span
                                        class="text-ink line-clamp-2"
                                        :title="row.answer"
                                    >
                                        {{ row.answer }}
                                    </span>
                                    <audio
                                        v-if="row.audioUrl !== null"
                                        controls
                                        preload="none"
                                        :src="row.audioUrl"
                                        class="mt-1 h-8 w-full max-w-[200px]"
                                        :data-test="`report-answer-${row.id}-audio`"
                                    />
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        :class="
                                            cn(
                                                'inline-flex items-center gap-1 text-[11.5px] font-semibold',
                                                row.isCorrect === null
                                                    ? 'text-ink-muted'
                                                    : row.isCorrect
                                                      ? 'text-success-text'
                                                      : 'text-danger-text',
                                            )
                                        "
                                    >
                                        <component
                                            :is="
                                                row.isCorrect === null
                                                    ? Clock
                                                    : row.isCorrect
                                                      ? Check
                                                      : X
                                            "
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {{ resultLabel(row.isCorrect) }}
                                    </span>
                                    <span
                                        class="text-ink-slate block text-[11px]"
                                    >
                                        {{
                                            formatScore(row.score, row.maxScore)
                                        }}
                                        <template v-if="row.overridden">{{
                                            $t('· overridden')
                                        }}</template>
                                        · v{{ row.version }}
                                    </span>
                                    <button
                                        v-if="canOverrideScores"
                                        type="button"
                                        class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 mt-0.5 inline-flex items-center gap-1 rounded-sm text-[11px] font-semibold hover:underline focus-visible:ring-3 focus-visible:outline-none"
                                        :data-test="`report-answer-${row.id}-adjust-button`"
                                        @click="
                                            emit('score', {
                                                kind: 'answer',
                                                row,
                                            })
                                        "
                                    >
                                        <SlidersHorizontal
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        {{ $t('Adjust') }}
                                    </button>
                                </td>
                                <td :class="bodyCell">
                                    {{ formatDuration(row.timeTakenMs) }}
                                </td>
                                <td :class="cn(bodyCell, 'text-[11.5px]')">
                                    {{ row.submittedAt }}
                                </td>
                            </tr>
                        </template>

                        <template v-else-if="results.tab === 'roleplayLogs'">
                            <tr
                                v-for="(row, index) in results.rows"
                                :key="row.id"
                                class="border-line/80 hover:bg-brand-50/35 border-t"
                                :data-test="`report-roleplay-${row.id}`"
                            >
                                <td
                                    class="text-ink-muted py-[7px] ps-3 pe-2 align-middle"
                                >
                                    {{ rank(index) }}
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ row.employee }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11px]"
                                    >
                                        {{ row.department }}
                                    </span>
                                </td>
                                <td :class="bodyCell">
                                    <span class="block truncate">{{
                                        row.scenario
                                    }}</span>
                                </td>
                                <td :class="bodyCell">{{ row.attemptNo }}</td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        :class="
                                            cn(pill, roleplayTone[row.status])
                                        "
                                    >
                                        {{ row.statusLabel }}
                                    </span>
                                </td>
                                <td
                                    :class="
                                        cn(bodyCell, 'text-ai font-semibold')
                                    "
                                >
                                    {{ row.overallScore ?? '—' }}
                                    <span
                                        v-if="row.overridden"
                                        class="text-ink-slate block text-[10.5px] font-normal"
                                        >{{ $t('overridden') }}</span
                                    >
                                    <button
                                        v-if="
                                            canOverrideScores &&
                                            row.status === 'completed'
                                        "
                                        type="button"
                                        class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex items-center gap-1 rounded-sm text-[11px] font-semibold hover:underline focus-visible:ring-3 focus-visible:outline-none"
                                        :data-test="`report-roleplay-${row.id}-adjust-button`"
                                        @click="
                                            emit('score', {
                                                kind: 'roleplay',
                                                row,
                                            })
                                        "
                                    >
                                        <SlidersHorizontal
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        {{ $t('Adjust') }}
                                    </button>
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <ul class="flex flex-wrap gap-1">
                                        <li
                                            v-for="criterion in row.criteria"
                                            :key="criterion.key"
                                            class="bg-ai-tint text-ai rounded-pill px-1.5 py-0.5 text-[10px] font-semibold whitespace-nowrap"
                                            :title="criterion.label"
                                        >
                                            {{ criterion.label.slice(0, 4) }}
                                            {{ criterion.score ?? '—' }}
                                        </li>
                                    </ul>
                                </td>
                                <td :class="cn(bodyCell, 'text-[11.5px]')">
                                    {{ row.startedAt }}
                                    <span
                                        class="text-ink-slate block text-[11px]"
                                    >
                                        {{ formatDuration(row.durationMs) }} ·
                                        {{
                                            $tc(
                                                ':count turn|:count turns',
                                                row.turns,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <button
                                        v-if="
                                            canViewTranscripts &&
                                            row.transcript !== null
                                        "
                                        type="button"
                                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-8 items-center justify-center gap-1.5 rounded-md border px-3 text-[11.5px] font-semibold"
                                        :data-test="`report-roleplay-${row.id}-transcript-button`"
                                        @click="emit('transcript', row)"
                                    >
                                        <MessageSquareText
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ $t('View transcript') }}
                                    </button>
                                    <span
                                        v-else
                                        class="text-ink-slate inline-flex items-center gap-1 text-[11px]"
                                        :title="
                                            $t(
                                                'Full transcripts are available to the Super Admin only',
                                            )
                                        "
                                    >
                                        <Lock
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        {{ $t('Scores only') }}
                                    </span>
                                </td>
                            </tr>
                        </template>

                        <template v-else-if="results.tab === 'lessonProgress'">
                            <tr
                                v-for="(row, index) in results.rows"
                                :key="row.id"
                                class="border-line/80 hover:bg-brand-50/35 border-t"
                                :data-test="`report-lesson-${row.id}`"
                            >
                                <td
                                    class="text-ink-muted py-[7px] ps-3 pe-2 align-middle"
                                >
                                    {{ rank(index) }}
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ row.employee }}
                                    </span>
                                </td>
                                <td :class="bodyCell">{{ row.department }}</td>
                                <td :class="bodyCell">
                                    <span class="block truncate">{{
                                        row.course
                                    }}</span>
                                </td>
                                <td :class="bodyCell">
                                    <span class="block truncate">{{
                                        row.lesson
                                    }}</span>
                                </td>
                                <td :class="cn(bodyCell, 'text-[11.5px]')">
                                    <span
                                        class="text-success-text inline-flex items-center gap-1 font-semibold"
                                    >
                                        <Check
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ row.completedAt }}
                                    </span>
                                </td>
                            </tr>
                        </template>

                        <template v-else-if="results.tab === 'comparison'">
                            <tr
                                v-for="(row, index) in results.rows"
                                :key="row.id"
                                class="border-line/80 hover:bg-brand-50/35 border-t"
                                :data-test="`report-comparison-${row.id}`"
                            >
                                <td
                                    class="text-ink-muted py-[7px] ps-3 pe-2 align-middle"
                                >
                                    {{ rank(index) }}
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ row.employee }}
                                    </span>
                                </td>
                                <td :class="bodyCell">{{ row.department }}</td>
                                <td :class="bodyCell">{{ row.skill }}</td>
                                <td :class="bodyCell">
                                    <span class="text-azure font-semibold">
                                        {{ percentLabel(row.prePercent) }}
                                    </span>
                                    <span
                                        v-if="row.preTotal !== null"
                                        class="text-ink-slate text-[11px]"
                                    >
                                        ({{ row.preCorrect }} /
                                        {{ row.preTotal }})
                                    </span>
                                </td>
                                <td :class="bodyCell">
                                    <span class="text-brand-700 font-semibold">
                                        {{ percentLabel(row.postPercent) }}
                                    </span>
                                    <span
                                        v-if="row.postTotal !== null"
                                        class="text-ink-slate text-[11px]"
                                    >
                                        ({{ row.postCorrect }} /
                                        {{ row.postTotal }})
                                    </span>
                                </td>
                                <td class="px-2 py-[7px] align-middle">
                                    <span
                                        :class="
                                            cn(
                                                'inline-flex items-center gap-1 text-[11.5px] font-semibold',
                                                deltaClass(row.delta),
                                            )
                                        "
                                    >
                                        <component
                                            :is="deltaIcon(row.delta)"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ deltaLabel(row.delta) }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Below md every table becomes stacked cards (RESP-01). -->
            <ul class="divide-line divide-y md:hidden">
                <li
                    v-if="isEmpty && !loading"
                    class="bg-surface p-6 text-center"
                >
                    <p
                        class="font-heading text-brand-900 text-[14px] font-semibold"
                    >
                        {{ emptyText }}
                    </p>
                </li>

                <template v-else-if="results.tab === 'employeeResults'">
                    <li
                        v-for="row in results.rows"
                        :key="row.id"
                        class="bg-surface p-4"
                    >
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
                            <ReportsRowActions
                                :row="row"
                                :can-export="canExport"
                                size="card"
                                @select="emit('action', $event, row)"
                            />
                        </div>

                        <div
                            class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                        >
                            <p>
                                <span class="text-brand-900 font-medium">{{
                                    $t('Pre/Post:')
                                }}</span>
                                {{ row.preScore ?? '—' }} /
                                {{ row.postScore ?? '—' }}
                            </p>
                            <p>
                                <span class="text-brand-900 font-medium">{{
                                    $t('Lessons:')
                                }}</span>
                                {{ row.lessonsCompleted }} /
                                {{ row.lessonsTotal }}
                            </p>
                            <p>
                                <span class="text-brand-900 font-medium">{{
                                    $t('AI Scenarios:')
                                }}</span>
                                {{ row.scenariosCompleted }} /
                                {{ row.scenariosTotal }}
                            </p>
                            <p>
                                <span class="text-brand-900 font-medium">{{
                                    $t('Last Activity:')
                                }}</span>
                                {{ row.lastActivity }}
                            </p>
                        </div>

                        <div
                            class="mt-3 flex items-center justify-between gap-3"
                        >
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
                                :aria-label="
                                    $t('View details for :name', {
                                        name: row.name,
                                    })
                                "
                                @click="emit('action', 'details', row)"
                            >
                                {{ $t('View Details') }}
                            </button>
                        </div>
                    </li>
                </template>

                <template v-else-if="results.tab === 'detailedAnswers'">
                    <li
                        v-for="row in results.rows"
                        :key="row.id"
                        class="bg-surface p-4"
                    >
                        <p
                            class="font-heading text-brand-800 text-[14px] font-semibold"
                        >
                            {{ row.employee }}
                            <span
                                class="text-ink-slate font-sans text-[12px] font-normal"
                            >
                                · {{ row.context }} · {{ row.skill }}
                            </span>
                        </p>
                        <p class="text-ink-muted mt-2 text-[13px] leading-5">
                            {{ row.question }}
                        </p>
                        <p
                            class="text-ink mt-1 text-[13px] leading-5 font-medium"
                        >
                            {{ row.answer }}
                        </p>
                        <audio
                            v-if="row.audioUrl !== null"
                            controls
                            preload="none"
                            :src="row.audioUrl"
                            class="mt-2 h-10 w-full"
                        />
                        <p class="text-ink-slate mt-2 text-[12px]">
                            {{ resultLabel(row.isCorrect) }}
                            · {{ formatScore(row.score, row.maxScore) }} ·
                            {{ formatDuration(row.timeTakenMs) }} · v{{
                                row.version
                            }}
                            ·
                            {{ row.submittedAt }}
                            <template v-if="row.overridden">
                                {{ $t('· overridden') }}</template
                            >
                        </p>
                        <button
                            v-if="canOverrideScores"
                            type="button"
                            class="border-line text-brand-700 hover:bg-brand-50 bg-surface mt-3 inline-flex h-9 items-center justify-center gap-1.5 rounded-md border px-3 text-[12px] font-semibold"
                            @click="emit('score', { kind: 'answer', row })"
                        >
                            <SlidersHorizontal
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ $t('Adjust score') }}
                        </button>
                    </li>
                </template>

                <template v-else-if="results.tab === 'roleplayLogs'">
                    <li
                        v-for="row in results.rows"
                        :key="row.id"
                        class="bg-surface p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="font-heading text-brand-800 truncate text-[14px] font-semibold"
                                >
                                    {{ row.employee }}
                                </p>
                                <p class="text-ink-muted text-[13px]">
                                    {{ row.scenario }} ·
                                    {{
                                        $t('attempt :number', {
                                            number: row.attemptNo,
                                        })
                                    }}
                                </p>
                            </div>
                            <span
                                :class="
                                    cn(
                                        pill,
                                        'min-h-6',
                                        roleplayTone[row.status],
                                    )
                                "
                            >
                                {{ row.statusLabel }}
                            </span>
                        </div>
                        <ul class="mt-2 flex flex-wrap gap-1">
                            <li
                                class="bg-brand-100/70 text-brand-700 rounded-pill px-2 py-0.5 text-[11px] font-semibold"
                            >
                                {{
                                    $t('Overall :score', {
                                        score: row.overallScore ?? '—',
                                    })
                                }}
                            </li>
                            <li
                                v-for="criterion in row.criteria"
                                :key="criterion.key"
                                class="bg-ai-tint text-ai rounded-pill px-2 py-0.5 text-[11px] font-semibold"
                            >
                                {{ criterion.label }}
                                {{ criterion.score ?? '—' }}
                            </li>
                        </ul>
                        <div
                            class="mt-3 flex items-center justify-between gap-3"
                        >
                            <span class="text-ink-slate text-[12px]">
                                {{ row.startedAt }} ·
                                {{ formatDuration(row.durationMs) }}
                            </span>
                            <button
                                v-if="
                                    canViewTranscripts &&
                                    row.transcript !== null
                                "
                                type="button"
                                class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-9 items-center justify-center gap-1.5 rounded-md border px-3 text-[12px] font-semibold"
                                @click="emit('transcript', row)"
                            >
                                <MessageSquareText
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('Transcript') }}
                            </button>
                        </div>
                        <button
                            v-if="
                                canOverrideScores && row.status === 'completed'
                            "
                            type="button"
                            class="border-line text-brand-700 hover:bg-brand-50 bg-surface mt-2 inline-flex h-9 items-center justify-center gap-1.5 rounded-md border px-3 text-[12px] font-semibold"
                            @click="emit('score', { kind: 'roleplay', row })"
                        >
                            <SlidersHorizontal
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ $t('Adjust score') }}
                        </button>
                    </li>
                </template>

                <template v-else-if="results.tab === 'lessonProgress'">
                    <li
                        v-for="row in results.rows"
                        :key="row.id"
                        class="bg-surface p-4"
                    >
                        <p
                            class="font-heading text-brand-800 text-[14px] font-semibold"
                        >
                            {{ row.employee }}
                            <span
                                class="text-ink-slate font-sans text-[12px] font-normal"
                            >
                                · {{ row.department }}
                            </span>
                        </p>
                        <p class="text-ink-muted mt-1 text-[13px]">
                            {{ row.course }} · {{ row.lesson }}
                        </p>
                        <p
                            class="text-success-text mt-2 inline-flex items-center gap-1 text-[12px] font-semibold"
                        >
                            <Check class="size-3.5" aria-hidden="true" />
                            {{ row.completedAt }}
                        </p>
                    </li>
                </template>

                <template v-else-if="results.tab === 'comparison'">
                    <li
                        v-for="row in results.rows"
                        :key="row.id"
                        class="bg-surface p-4"
                    >
                        <p
                            class="font-heading text-brand-800 text-[14px] font-semibold"
                        >
                            {{ row.employee }}
                            <span
                                class="text-ink-slate font-sans text-[12px] font-normal"
                            >
                                · {{ row.skill }}
                            </span>
                        </p>
                        <p class="text-ink-muted mt-2 text-[13px]">
                            {{
                                $t('Pre :pre → Post :post', {
                                    pre: percentLabel(row.prePercent),
                                    post: percentLabel(row.postPercent),
                                })
                            }}
                            <span
                                :class="
                                    cn('font-semibold', deltaClass(row.delta))
                                "
                            >
                                ({{ deltaLabel(row.delta) }})
                            </span>
                        </p>
                    </li>
                </template>
            </ul>
        </div>

        <div
            v-if="showsTable"
            class="mt-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between xl:flex-1"
            >
                <p
                    class="text-ink-muted text-[12.5px] leading-5"
                    data-test="reports-showing"
                >
                    {{ showingText }}
                </p>

                <nav
                    :aria-label="$t('Reports pagination')"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <button
                        type="button"
                        class="text-brand-700 hover:bg-brand-50 inline-flex size-8 items-center justify-center rounded-md disabled:opacity-40"
                        :disabled="pagination.currentPage <= 1"
                        :aria-label="$t('Previous page')"
                        data-test="reports-previous-page"
                        @click="emit('page', pagination.currentPage - 1)"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </button>

                    <template
                        v-for="(page, index) in pagination.pages"
                        :key="`${String(page)}-${index}`"
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
                            :aria-current="
                                page === pagination.currentPage
                                    ? 'page'
                                    : undefined
                            "
                            :data-test="`reports-page-${page}`"
                            :class="
                                cn(
                                    'inline-flex size-8 items-center justify-center rounded-md text-[12px] font-semibold',
                                    page === pagination.currentPage
                                        ? 'bg-brand-600 shadow-btn text-white'
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
                        class="text-brand-700 hover:bg-brand-50 inline-flex size-8 items-center justify-center rounded-md disabled:opacity-40"
                        :disabled="
                            pagination.currentPage >= pagination.lastPage
                        "
                        :aria-label="$t('Next page')"
                        data-test="reports-next-page"
                        @click="emit('page', pagination.currentPage + 1)"
                    >
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </nav>
            </div>

            <div class="text-ink-muted flex items-center gap-2 text-[12px]">
                <span>{{ $t('Rows per page') }}</span>
                <Select
                    :model-value="perPage"
                    @update:model-value="onPerPageSelect"
                >
                    <SelectTrigger
                        :aria-label="$t('Rows per page')"
                        data-test="reports-per-page"
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
            v-if="canExport"
            class="mt-5 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
            >
                <p class="text-brand-800 text-[13px] font-semibold">
                    {{ $t('Export Selected Data:') }}
                </p>
                <button
                    v-for="action in exportActions"
                    :key="action.id"
                    type="button"
                    :data-test="`export-${action.id}-button`"
                    :class="
                        cn(
                            'bg-surface inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-4 text-[12.5px] font-semibold transition-colors duration-150',
                            exportTone[action.tone],
                        )
                    "
                    @click="emit('export', action.id)"
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
                <Checkbox
                    :model-value="includeDetailedAnswers"
                    data-test="include-detailed-answers-checkbox"
                    @update:model-value="onIncludeChange"
                />
                <span>{{ $t('Include detailed answers') }}</span>
            </label>
        </div>
    </section>
</template>
