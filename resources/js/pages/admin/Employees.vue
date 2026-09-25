<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EmployeeCredentialsDialog from '@/components/employees/EmployeeCredentialsDialog.vue';
import EmployeeFormDialog from '@/components/employees/EmployeeFormDialog.vue';
import EmployeeResetDialog from '@/components/employees/EmployeeResetDialog.vue';
import EmployeeViewDialog from '@/components/employees/EmployeeViewDialog.vue';
import EmployeesActionPanels from '@/components/employees/EmployeesActionPanels.vue';
import EmployeeCreateDialog from '@/components/employees/EmployeeCreateDialog.vue';
import EmployeesDirectoryPanel from '@/components/employees/EmployeesDirectoryPanel.vue';
import type { EmployeeFilterValues } from '@/components/employees/EmployeesDirectoryPanel.vue';
import EmployeesStatsRow from '@/components/employees/EmployeesStatsRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { useCan } from '@/composables/useCan';
import { dashboard, employees as employeesRoute } from '@/routes';
import { activate, bulk, deactivate, remind } from '@/routes/employees';
import type {
    EmployeeBulkAction,
    EmployeeCreateForm,
    EmployeeCredentials,
    EmployeeFilters,
    EmployeeMetric,
    EmployeePagination,
    EmployeeRecord,
    EmployeeRowAction,
    EmployeeSelectOption,
} from '@/types';

type Props = {
    stats: EmployeeMetric[];
    filters: EmployeeFilters;
    employees: EmployeeRecord[];
    pagination: EmployeePagination;
    bulkActions: EmployeeSelectOption[];
    createForm: EmployeeCreateForm;
    reminderTemplates: EmployeeSelectOption[];
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
                title: 'Employees',
                href: employeesRoute(),
            },
        ],
    },
});

const { can } = useCan();
const canManage = can('employees.manage');
// Account creation is separately authorized from employee management (SUB-02).
const canCreate = can('employees.create');

// ------------------------------------------------------------ navigation
//
// Search, the three filters and the page live in the query string, so a
// refresh or a shared link holds them. Partial reloads keep the shell and
// stat cards still (REP-01).

type Query = {
    search?: string;
    hotel?: string;
    department?: string;
    status?: string;
    page?: number;
};

const loading = ref(false);

function queryFrom(values: EmployeeFilterValues, page?: number): Query {
    const query: Query = {};

    if (values.search !== '') {
        query.search = values.search;
    }
    if (values.hotel !== 'all-hotels') {
        query.hotel = values.hotel;
    }
    if (values.department !== 'all-departments') {
        query.department = values.department;
    }
    if (values.status !== 'all-statuses') {
        query.status = values.status;
    }
    if (page !== undefined && page > 1) {
        query.page = page;
    }

    return query;
}

function currentFilters(): EmployeeFilterValues {
    return {
        search: props.filters.search,
        hotel: props.filters.hotel,
        department: props.filters.department,
        status: props.filters.status,
    };
}

function visit(query: Query, only: string[]): void {
    router.get(employeesRoute().url, query, {
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
    });
}

function applyFilters(values: EmployeeFilterValues): void {
    visit(queryFrom(values), ['filters', 'employees', 'pagination']);
}

function goToPage(page: number): void {
    visit(queryFrom(currentFilters(), page), ['employees', 'pagination']);
}

// --------------------------------------------------------------- selection
//
// The checked rows are shared by the table, the Bulk Actions panel and the
// Send Reminder panel. A reload of the rows drops ids that left the page.

const selected = ref<number[]>([]);

watch(
    () => props.employees,
    (rows) => {
        const visible = new Set(rows.map((row) => row.id));
        selected.value = selected.value.filter((id) => visible.has(id));
    },
);

// ----------------------------------------------------------------- writes

function post(url: string, data: RequestPayload = {}): void {
    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            loading.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

function sendReminder(ids: number[]): void {
    if (ids.length === 0) {
        return;
    }

    post(remind().url, { ids });
}

function applyBulk(action: EmployeeBulkAction): void {
    if (selected.value.length === 0) {
        return;
    }

    post(bulk().url, { action, ids: selected.value });
}

// --------------------------------------------------------------- dialogs

const viewOpen = ref(false);
const formOpen = ref(false);
const resetOpen = ref(false);
const credentialsOpen = ref(false);
const actionEmployee = ref<EmployeeRecord | null>(null);
const credentials = ref<EmployeeCredentials | null>(null);

function onAction(action: EmployeeRowAction, employee: EmployeeRecord): void {
    actionEmployee.value = employee;

    switch (action) {
        case 'view':
            viewOpen.value = true;
            return;
        case 'edit':
            formOpen.value = true;
            return;
        case 'remind':
            sendReminder([employee.id]);
            return;
        case 'reset-password':
            resetOpen.value = true;
            return;
        case 'activate':
            post(activate(employee.id).url);
            return;
        case 'deactivate':
            post(deactivate(employee.id).url);
    }
}

// The reset password lands as Inertia flash data (never in history state)
// and is shown exactly once (AUTH-07).
let stopFlash: (() => void) | null = null;

onMounted(() => {
    stopFlash = router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash as
            | Record<string, unknown>
            | undefined;
        const data = flash?.employeeCredentials as
            | EmployeeCredentials
            | undefined;

        if (data) {
            credentials.value = data;
            credentialsOpen.value = true;
        }
    });
});

onBeforeUnmount(() => {
    stopFlash?.();
});
</script>

<template>
    <Head title="Manage Employees" />

    <div class="flex w-full min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
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

        <EmployeesActionPanels
            :bulk-actions="bulkActions"
            :filters="filters"
            :selected="selected"
            :busy="loading"
            @bulk="applyBulk"
            @remind="sendReminder(selected)"
        />

        <EmployeesDirectoryPanel
            v-model:selected="selected"
            :filters="filters"
            :employees="employees"
            :pagination="pagination"
            :loading="loading"
            @filter="applyFilters"
            @page="goToPage"
            @action="onAction"
        >
            <template #actions>
                <div v-if="canCreate" class="flex justify-end xl:ms-auto">
                    <EmployeeCreateDialog :create-form="createForm" />
                </div>
            </template>
        </EmployeesDirectoryPanel>
    </div>

    <EmployeeViewDialog v-model:open="viewOpen" :employee="actionEmployee" />

    <template v-if="canManage">
        <EmployeeFormDialog
            v-model:open="formOpen"
            :employee="actionEmployee"
            :create-form="createForm"
        />
        <EmployeeResetDialog
            v-model:open="resetOpen"
            :employee="actionEmployee"
        />
        <EmployeeCredentialsDialog
            v-model:open="credentialsOpen"
            :credentials="credentials"
        />
    </template>
</template>
