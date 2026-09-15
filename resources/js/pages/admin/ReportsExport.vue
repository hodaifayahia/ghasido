<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportsChartsRow from '@/components/reports/ReportsChartsRow.vue';
import ReportsHeaderAccent from '@/components/reports/ReportsHeaderAccent.vue';
import ReportsResultsPanel from '@/components/reports/ReportsResultsPanel.vue';
import ReportsStatsRow from '@/components/reports/ReportsStatsRow.vue';
import ReportsToolbar from '@/components/reports/ReportsToolbar.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { dashboard, reportsExport as reportsExportRoute } from '@/routes';
import type {
    ReportActivityBreakdown,
    ReportCompletionBreakdown,
    ReportEmployeeRow,
    ReportExportAction,
    ReportGroupedBarPoint,
    ReportMetric,
    ReportPagination,
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
    rows: ReportEmployeeRow[];
    pagination: ReportPagination;
    search: string;
    includeDetailedAnswers: boolean;
    exportActions: ReportExportAction[];
};

defineProps<Props>();

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

        <ReportsToolbar :filters="filters" />

        <ReportsStatsRow :stats="stats" />

        <ReportsChartsRow
            :pre-post="prePost"
            :completion="completion"
            :ai-performance="aiPerformance"
            :activity="activity"
        />

        <ReportsResultsPanel
            :tabs="tabs"
            :active-tab="activeTab"
            :rows="rows"
            :pagination="pagination"
            :search="search"
            :include-detailed-answers="includeDetailedAnswers"
            :export-actions="exportActions"
        />
    </div>
</template>