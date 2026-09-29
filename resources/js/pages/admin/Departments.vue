<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import DepartmentFormDialog from '@/components/departments/DepartmentFormDialog.vue';
import DepartmentsDirectoryPanel from '@/components/departments/DepartmentsDirectoryPanel.vue';
import type { DepartmentFilterValues } from '@/components/departments/DepartmentsDirectoryPanel.vue';
import DepartmentsStatsRow from '@/components/departments/DepartmentsStatsRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import {
    dashboard,
    departments as departmentsRoute,
    hotels as hotelsRoute,
    lessonsContent,
} from '@/routes';
import { toggle } from '@/routes/departments';
import type {
    DepartmentFilters,
    DepartmentMetric,
    DepartmentOverview,
    DepartmentPagination,
    DepartmentRecord,
    DepartmentRowAction,
    DepartmentSelectOption,
} from '@/types';
import { tk } from '@/lib/i18n';

type Props = {
    stats: DepartmentMetric[];
    filters: DepartmentFilters;
    departments: DepartmentRecord[];
    pagination: DepartmentPagination;
    overview: DepartmentOverview | null;
    hotelOptions: DepartmentSelectOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: tk('Dashboard'),
                href: dashboard(),
            },
            {
                title: tk('Departments'),
                href: departmentsRoute(),
            },
        ],
    },
});

const { can } = useCan();
const canManage = can('departments.manage');

// ------------------------------------------------------------ navigation
//
// Search, both filters, the page and the selected department live in the
// query string, so a refresh holds them (spec 0003 Part D). Partial reloads
// keep the shell and the stat cards still.

type Query = {
    search?: string;
    scope?: string;
    status?: string;
    page?: number;
    department?: number;
};

const loading = ref(false);

function currentQuery(): Query {
    const query: Query = {};

    if (props.filters.search !== '') {
        query.search = props.filters.search;
    }
    if (props.filters.scope !== 'all-scopes') {
        query.scope = props.filters.scope;
    }
    if (props.filters.status !== 'all-statuses') {
        query.status = props.filters.status;
    }
    if (props.pagination.currentPage > 1) {
        query.page = props.pagination.currentPage;
    }
    if (props.overview !== null) {
        query.department = props.overview.id;
    }

    return query;
}

function visit(query: Query, only: string[], onSuccess?: () => void): void {
    router.get(departmentsRoute().url, query, {
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

function applyFilters(values: DepartmentFilterValues): void {
    const query: Query = {};

    if (values.search !== '') {
        query.search = values.search;
    }
    if (values.scope !== 'all-scopes') {
        query.scope = values.scope;
    }
    if (values.status !== 'all-statuses') {
        query.status = values.status;
    }

    visit(query, ['filters', 'departments', 'pagination', 'overview']);
}

function goToPage(page: number): void {
    const query = currentQuery();
    delete query.department;

    if (page > 1) {
        query.page = page;
    } else {
        delete query.page;
    }

    visit(query, ['departments', 'pagination', 'overview']);
}

function selectDepartment(department: DepartmentRecord): void {
    if (props.overview?.id === department.id) {
        return;
    }

    visit({ ...currentQuery(), department: department.id }, ['overview']);
}

function openContent(department: DepartmentRecord): void {
    router.get(lessonsContent().url, { department: department.id });
}

// --------------------------------------------------------------- dialogs

const formOpen = ref(false);
const formDepartment = ref<DepartmentRecord | null>(null);

function openCreate(): void {
    formDepartment.value = null;
    formOpen.value = true;
}

function openEdit(department: DepartmentRecord): void {
    formDepartment.value = department;
    formOpen.value = true;
}

function postToggle(department: DepartmentRecord): void {
    router.post(
        toggle(department.id).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

function onAction(
    action: DepartmentRowAction,
    department: DepartmentRecord,
): void {
    switch (action) {
        case 'view':
            selectDepartment(department);
            return;
        case 'edit':
            openEdit(department);
            return;
        case 'content':
            openContent(department);
            return;
        case 'archive':
        case 'restore':
            postToggle(department);
    }
}
</script>

<template>
    <Head :title="$t('Departments')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <div class="relative">
            <PageHeader
                :title="$t('Departments')"
                :description="
                    $t(
                        'Configure department scope, seat quotas and content coverage across your hotel portfolio.',
                    )
                "
                class="mb-1"
            >
                <template #accent>
                    <ScriptAccent />
                </template>
            </PageHeader>

            <!-- Create Department sits beside the title, as on Hotels: the
                 Quick Actions panel that held it was removed (client request
                 2026-09-29). -->
            <Button
                v-if="canManage"
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 mt-3 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:absolute md:end-44 md:top-0 md:mt-0"
                data-test="add-department-button"
                @click="openCreate"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ $t('Create Department') }}
            </Button>
        </div>

        <DepartmentsStatsRow :stats="stats" />

        <!-- Full width: Department Overview, Hotel Coverage and Quick
             Actions were removed (client request 2026-09-29). -->
        <div class="grid min-w-0 gap-3">
            <DepartmentsDirectoryPanel
                :filters="filters"
                :departments="departments"
                :pagination="pagination"
                :loading="loading"
                @filter="applyFilters"
                @page="goToPage"
                @action="onAction"
            />
        </div>
    </div>

    <DepartmentFormDialog
        v-if="canManage"
        v-model:open="formOpen"
        :department="formDepartment"
        :hotel-options="hotelOptions"
    />
</template>
