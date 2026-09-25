<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ReportsChartsRow from '@/components/reports/ReportsChartsRow.vue';
import ReportsEmployeeDialog from '@/components/reports/ReportsEmployeeDialog.vue';
import ReportsHeaderAccent from '@/components/reports/ReportsHeaderAccent.vue';
import ReportsResultsPanel from '@/components/reports/ReportsResultsPanel.vue';
import ReportsStatsRow from '@/components/reports/ReportsStatsRow.vue';
import ReportsToolbar from '@/components/reports/ReportsToolbar.vue';
import type { ReportFilterValues } from '@/components/reports/ReportsToolbar.vue';
import ReportsScoreDialog from '@/components/reports/ReportsScoreDialog.vue';
import ReportsTranscriptDialog from '@/components/reports/ReportsTranscriptDialog.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { dashboard, reportsExport as reportsExportRoute } from '@/routes';
import { exportMethod } from '@/routes/reports';
import type {
    ReportActivityBreakdown,
    ReportCompletionBreakdown,
    ReportDataset,
    ReportDatasetKey,
    ReportEmployeeDetail,
    ReportEmployeeRow,
    ReportExportAction,
    ReportExportFormat,
    ReportGroupedBarPoint,
    ReportMetric,
    ReportPagination,
    ReportResults,
    ReportRoleplayRow,
    ReportScoreTarget,
    ReportRowAction,
    ReportsFilters,
    ReportSingleBarPoint,
    ReportsTab,
    ReportsTabKey,
} from '@/types';

type Props = {
    filters: ReportsFilters;
    stats: ReportMetric[];
    prePost: ReportGroupedBarPoint[];
    completion: ReportCompletionBreakdown;
    aiPerformance: ReportSingleBarPoint[];
    activity: ReportActivityBreakdown;
    tabs: ReportsTab[];
    activeTab: ReportsTabKey;
    results: ReportResults;
    pagination: ReportPagination;
    search: string;
    includeDetailedAnswers: boolean;
    exportActions: ReportExportAction[];
    datasets: ReportDataset[];
    detail: ReportEmployeeDetail | null;
    canViewTranscripts: boolean;
    canOverrideScores: boolean;
    canExport: boolean;
    canExportAnonymised: boolean;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Reports & Export',
                href: reportsExportRoute(),
            },
        ],
    },
});

// ------------------------------------------------------------ navigation
//
// Every filter, the tab, the search, the page, the page size and the open
// drill-down live in the query string, so a refresh or a shared link holds
// them (REP-02). Partial reloads keep the shell and the header still.

type Query = Record<string, string | number>;

const DEFAULTS: Record<string, string> = {
    range: 'last-90-days',
    hotel: 'all-hotels',
    department: 'all-departments',
    employee: 'all-employees',
    activityType: 'all-activities',
    completionStatus: 'all',
};

const DATA_PROPS = [
    'filters',
    'stats',
    'prePost',
    'completion',
    'aiPerformance',
    'activity',
    'activeTab',
    'results',
    'pagination',
    'search',
];

const ROW_PROPS = ['activeTab', 'results', 'pagination', 'search'];

const loading = ref(false);

function filterQuery(values?: ReportFilterValues): Query {
    const source = values ?? props.filters;
    const query: Query = {};

    for (const key of Object.keys(DEFAULTS)) {
        const value = source[key as keyof ReportFilterValues];

        if (value !== DEFAULTS[key]) {
            query[key] = value;
        }
    }

    if (source.range === 'custom') {
        query.from = source.from;
        query.to = source.to;
    }

    return query;
}

function currentQuery(): Query {
    const query = filterQuery();

    if (props.activeTab !== 'employeeResults') {
        query.tab = props.activeTab;
    }
    if (props.search !== '') {
        query.search = props.search;
    }
    if (props.pagination.currentPage > 1) {
        query.page = props.pagination.currentPage;
    }
    if (props.pagination.currentPerPage !== 7) {
        query.per_page = props.pagination.currentPerPage;
    }

    return query;
}

function visit(query: Query, only: string[], onSuccess?: () => void): void {
    router.get(reportsExportRoute().url, query, {
        only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            loading.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
        onSuccess,
    });
}

function applyFilters(values: ReportFilterValues): void {
    const query = filterQuery(values);

    if (props.activeTab !== 'employeeResults') {
        query.tab = props.activeTab;
    }
    if (props.pagination.currentPerPage !== 7) {
        query.per_page = props.pagination.currentPerPage;
    }

    visit(query, DATA_PROPS);
}

function resetFilters(): void {
    visit({}, DATA_PROPS);
}

function selectTab(tab: ReportsTabKey): void {
    const query = currentQuery();
    delete query.page;
    delete query.search;

    if (tab === 'employeeResults') {
        delete query.tab;
    } else {
        query.tab = tab;
    }

    visit(query, ROW_PROPS);
}

function applySearch(search: string): void {
    const query = currentQuery();
    delete query.page;

    if (search === '') {
        delete query.search;
    } else {
        query.search = search;
    }

    visit(query, ROW_PROPS);
}

function goToPage(page: number): void {
    const query = currentQuery();

    if (page > 1) {
        query.page = page;
    } else {
        delete query.page;
    }

    visit(query, ROW_PROPS);
}

function setPerPage(perPage: number): void {
    const query = currentQuery();
    delete query.page;

    if (perPage === 7) {
        delete query.per_page;
    } else {
        query.per_page = perPage;
    }

    visit(query, ROW_PROPS);
}

// ---------------------------------------------------------------- dialogs

const detailOpen = ref(false);
const transcriptOpen = ref(false);
const transcriptRow = ref<ReportRoleplayRow | null>(null);
const includeDetailedAnswers = ref(props.includeDetailedAnswers);

function openDetail(row: ReportEmployeeRow): void {
    detailOpen.value = true;

    if (props.detail?.id === row.id) {
        return;
    }

    visit({ ...currentQuery(), detail: row.id }, ['detail']);
}

function onRowAction(action: ReportRowAction, row: ReportEmployeeRow): void {
    switch (action) {
        case 'details':
            openDetail(row);
            return;
        case 'export':
            download('answers', 'csv', { employee: row.id });
            return;
        case 'answers':
        case 'roleplay':
        case 'lessons':
        case 'comparison': {
            const tabs: Record<
                'answers' | 'roleplay' | 'lessons' | 'comparison',
                ReportsTabKey
            > = {
                answers: 'detailedAnswers',
                roleplay: 'roleplayLogs',
                lessons: 'lessonProgress',
                comparison: 'comparison',
            };
            const query = filterQuery();
            query.employee = row.id;
            query.tab = tabs[action];
            visit(query, DATA_PROPS);
        }
    }
}

function showTranscript(row: ReportRoleplayRow): void {
    transcriptRow.value = row;
    transcriptOpen.value = true;
}

// Adjusting an AI score (AIE-05; spec 0005 §2.5).
const scoreOpen = ref(false);
const scoreTarget = ref<ReportScoreTarget | null>(null);

function showScore(target: ReportScoreTarget): void {
    scoreTarget.value = target;
    scoreOpen.value = true;
}

// ---------------------------------------------------------------- exports
//
// Every export runs reports.export with the page's current filters; CSV
// and XLSX download in place, the PDF opens the print page in a new tab
// (REP-03, REP-06; spec 0003 Part D).

function datasetForTab(): ReportDatasetKey {
    switch (props.activeTab) {
        case 'detailedAnswers':
            return 'answers';
        case 'roleplayLogs':
            return 'roleplay';
        case 'lessonProgress':
            return 'lessons';
        case 'comparison':
            return 'comparison';
        default:
            return includeDetailedAnswers.value ? 'answers' : 'employees';
    }
}

function exportUrl(
    dataset: ReportDatasetKey,
    format: ReportExportFormat,
    extra: Query = {},
): string {
    const query: Query = { ...filterQuery(), ...extra, dataset, format };

    if (props.search !== '') {
        query.search = props.search;
    }

    return exportMethod.url({ query });
}

function download(
    dataset: ReportDatasetKey,
    format: ReportExportFormat,
    extra: Query = {},
): void {
    const url = exportUrl(dataset, format, extra);

    if (format === 'pdf') {
        window.open(url, '_blank', 'noopener');
        return;
    }

    window.location.assign(url);
}

function runExport(format: ReportExportFormat): void {
    download(datasetForTab(), format);
}
</script>

<template>
    <Head title="Reports & Export" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">Reports & Export</h1>

        <PageHeader
            title="Reports & Export"
            description="View detailed results, track progress and export data for your research."
            class="mb-1"
        >
            <template #accent>
                <ReportsHeaderAccent />
            </template>
        </PageHeader>

        <ReportsToolbar
            :filters="filters"
            :can-export="canExport"
            @filter="applyFilters"
            @reset="resetFilters"
            @export="runExport"
        />

        <ReportsStatsRow :stats="stats" />

        <ReportsChartsRow
            :pre-post="prePost"
            :completion="completion"
            :ai-performance="aiPerformance"
            :activity="activity"
        />

        <ReportsResultsPanel
            v-model:include-detailed-answers="includeDetailedAnswers"
            :tabs="tabs"
            :active-tab="activeTab"
            :results="results"
            :pagination="pagination"
            :search="search"
            :export-actions="exportActions"
            :datasets="datasets"
            :can-view-transcripts="canViewTranscripts"
            :can-override-scores="canOverrideScores"
            :can-export="canExport"
            :loading="loading"
            @tab="selectTab"
            @search="applySearch"
            @page="goToPage"
            @per-page="setPerPage"
            @action="onRowAction"
            @transcript="showTranscript"
            @score="showScore"
            @export="runExport"
            @download="download"
        />
    </div>

    <ReportsEmployeeDialog v-model:open="detailOpen" :detail="detail" />
    <ReportsScoreDialog
        v-if="canOverrideScores"
        v-model:open="scoreOpen"
        :target="scoreTarget"
    />
    <ReportsTranscriptDialog
        v-if="canViewTranscripts"
        v-model:open="transcriptOpen"
        :attempt="transcriptRow"
    />
</template>
