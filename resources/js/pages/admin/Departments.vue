<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DepartmentsDirectoryPanel from '@/components/departments/DepartmentsDirectoryPanel.vue';
import DepartmentsSidebarPanel from '@/components/departments/DepartmentsSidebarPanel.vue';
import DepartmentsStatsRow from '@/components/departments/DepartmentsStatsRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { dashboard, departments as departmentsRoute } from '@/routes';
import type {
    DepartmentFilters,
    DepartmentMetric,
    DepartmentOverview,
    DepartmentPagination,
    DepartmentRecord,
} from '@/types';

type Props = {
    stats: DepartmentMetric[];
    filters: DepartmentFilters;
    departments: DepartmentRecord[];
    pagination: DepartmentPagination;
    overview: DepartmentOverview;
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
                title: 'Departments',
                href: departmentsRoute(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Departments" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            title="Departments"
            description="Configure department scope, seat quotas and content coverage across your hotel portfolio."
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <DepartmentsStatsRow :stats="stats" />

        <div class="grid min-w-0 gap-3 xl:grid-cols-4 xl:items-start">
            <DepartmentsDirectoryPanel
                :filters="filters"
                :departments="departments"
                :pagination="pagination"
                class="xl:col-span-3"
            />

            <DepartmentsSidebarPanel :overview="overview" />
        </div>
    </div>
</template>
