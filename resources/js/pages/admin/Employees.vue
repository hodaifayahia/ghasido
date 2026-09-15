<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmployeesActionPanels from '@/components/employees/EmployeesActionPanels.vue';
import EmployeesCreatePanel from '@/components/employees/EmployeesCreatePanel.vue';
import EmployeesDirectoryPanel from '@/components/employees/EmployeesDirectoryPanel.vue';
import EmployeesStatsRow from '@/components/employees/EmployeesStatsRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { dashboard, employees as employeesRoute } from '@/routes';
import type {
    EmployeeCreateForm,
    EmployeeFilters,
    EmployeeMetric,
    EmployeePagination,
    EmployeeRecord,
    EmployeeSelectOption,
} from '@/types';

type Props = {
    stats: EmployeeMetric[];
    filters: EmployeeFilters;
    employees: EmployeeRecord[];
    pagination: EmployeePagination;
    bulkActions: EmployeeSelectOption[];
    createForm: EmployeeCreateForm;
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
                title: 'Employees',
                href: employeesRoute(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Manage Employees" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            title="Manage Employees"
            description="Create accounts, assign departments, track progress and send reminders."
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <EmployeesStatsRow :stats="stats" />

        <div
            class="grid min-w-0 gap-2.5 xl:grid-cols-[minmax(0,1fr)_260px] xl:items-start"
        >
            <div class="flex min-w-0 flex-col gap-3">
                <EmployeesDirectoryPanel
                    :filters="filters"
                    :employees="employees"
                    :pagination="pagination"
                />

                <EmployeesActionPanels :bulk-actions="bulkActions" />
            </div>

            <EmployeesCreatePanel :create-form="createForm" />
        </div>
    </div>
</template>
