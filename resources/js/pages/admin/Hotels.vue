<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import HotelDepartmentsDialog from '@/components/hotels/HotelDepartmentsDialog.vue';
import HotelExtendDialog from '@/components/hotels/HotelExtendDialog.vue';
import HotelFormDialog from '@/components/hotels/HotelFormDialog.vue';
import HotelReasonDialog from '@/components/hotels/HotelReasonDialog.vue';
import HotelSeatsDialog from '@/components/hotels/HotelSeatsDialog.vue';
import HotelsDirectoryPanel from '@/components/hotels/HotelsDirectoryPanel.vue';
import type { HotelFilterValues } from '@/components/hotels/HotelsDirectoryPanel.vue';
import HotelsStatsRow from '@/components/hotels/HotelsStatsRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { useCan } from '@/composables/useCan';
import { Button } from '@/components/ui/button';
import { dashboard, hotels as hotelsRoute } from '@/routes';
import { approve, pause, resume, show } from '@/routes/hotels';
import type {
    HotelFilters,
    HotelMetric,
    HotelOverview,
    HotelPagination,
    HotelRecord,
    HotelRowAction,
} from '@/types';
import { tk } from '@/lib/i18n';

type Props = {
    stats: HotelMetric[];
    filters: HotelFilters;
    hotels: HotelRecord[];
    pagination: HotelPagination;
    overview: HotelOverview | null;
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
                title: tk('Hotels'),
                href: hotelsRoute(),
            },
        ],
    },
});

const { can } = useCan();
const canManage = can('hotels.manage');

// ------------------------------------------------------------ navigation
//
// Search, both filters, the page and the selected hotel live in the query
// string, so a refresh holds them (spec 0002, AC-11, AC-18). Partial reloads
// keep the shell and the stat cards still.

type Query = {
    search?: string;
    status?: string;
    capacity?: string;
    page?: number;
    hotel?: number;
};

const loading = ref(false);

function currentQuery(): Query {
    const query: Query = {};

    if (props.filters.search !== '') {
        query.search = props.filters.search;
    }
    if (props.filters.status !== 'all-statuses') {
        query.status = props.filters.status;
    }
    if (props.filters.capacity !== 'all-capacities') {
        query.capacity = props.filters.capacity;
    }
    if (props.pagination.currentPage > 1) {
        query.page = props.pagination.currentPage;
    }
    if (props.overview !== null) {
        query.hotel = props.overview.id;
    }

    return query;
}

function visit(query: Query, only: string[], onSuccess?: () => void): void {
    router.get(hotelsRoute().url, query, {
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

function applyFilters(values: HotelFilterValues): void {
    const query: Query = {};

    if (values.search !== '') {
        query.search = values.search;
    }
    if (values.status !== 'all-statuses') {
        query.status = values.status;
    }
    if (values.capacity !== 'all-capacities') {
        query.capacity = values.capacity;
    }

    visit(query, ['filters', 'hotels', 'pagination', 'overview']);
}

function goToPage(page: number): void {
    const query = currentQuery();
    delete query.hotel;

    if (page > 1) {
        query.page = page;
    } else {
        delete query.page;
    }

    visit(query, ['hotels', 'pagination', 'overview']);
}

function selectHotel(hotel: HotelRecord, onSuccess?: () => void): void {
    if (props.overview?.id === hotel.id) {
        onSuccess?.();
        return;
    }

    visit({ ...currentQuery(), hotel: hotel.id }, ['overview'], onSuccess);
}

// --------------------------------------------------------------- dialogs

const formOpen = ref(false);
const formHotel = ref<HotelRecord | null>(null);
const seatsOpen = ref(false);
const departmentsOpen = ref(false);
const reasonOpen = ref(false);
const reasonMode = ref<'reject' | 'archive'>('archive');
const extendOpen = ref(false);
const actionHotel = ref<HotelRecord | null>(null);

const seatsOverview = computed(() =>
    actionHotel.value !== null && props.overview?.id === actionHotel.value.id
        ? props.overview
        : null,
);

function openCreate(): void {
    formHotel.value = null;
    formOpen.value = true;
}

function post(url: string): void {
    router.post(
        url,
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

function onAction(action: HotelRowAction, hotel: HotelRecord): void {
    actionHotel.value = hotel;

    switch (action) {
        case 'view':
            router.visit(show(hotel.id).url);
            return;
        case 'edit':
            formHotel.value = hotel;
            formOpen.value = true;
            return;
        case 'seats':
            // The dialog reads the seat catalogue from the sidebar payload,
            // so the hotel is selected first when it is not already.
            selectHotel(hotel, () => {
                seatsOpen.value = true;
            });
            return;
        case 'departments':
            selectHotel(hotel, () => {
                departmentsOpen.value = true;
            });
            return;
        case 'approve':
            post(approve(hotel.id).url);
            return;
        case 'pause':
            post(pause(hotel.id).url);
            return;
        case 'resume':
            post(resume(hotel.id).url);
            return;
        case 'reject':
        case 'archive':
            reasonMode.value = action;
            reasonOpen.value = true;
            return;
        case 'extend':
            extendOpen.value = true;
    }
}
</script>

<template>
    <Head :title="$t('Hotels')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <div class="relative">
            <PageHeader
                :title="$t('Hotels')"
                :description="
                    $t(
                        'Manage contract periods, seat quotas and access across your hotel portfolio.',
                    )
                "
                class="mb-1"
            >
                <template #accent>
                    <ScriptAccent />
                </template>
            </PageHeader>

            <!-- Create Hotel lives at the top of the page, beside the title,
                 in place of the old Quick Actions panel (spec 0002, AC-17). -->
            <Button
                v-if="canManage"
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 mt-3 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:absolute md:end-44 md:top-0 md:mt-0"
                data-test="add-hotel-button"
                @click="openCreate"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ $t('Add Hotel') }}
            </Button>
        </div>

        <HotelsStatsRow :stats="stats" />

        <!-- Full width: the Hotel Overview and Department Seat Quotas
             cards were removed (client request 2026-09-29). -->
        <div class="grid min-w-0 gap-3">
            <HotelsDirectoryPanel
                :filters="filters"
                :hotels="hotels"
                :pagination="pagination"
                :loading="loading"
                @filter="applyFilters"
                @page="goToPage"
                @action="onAction"
            />
        </div>
    </div>

    <template v-if="canManage">
        <HotelFormDialog v-model:open="formOpen" :hotel="formHotel" />
        <HotelSeatsDialog v-model:open="seatsOpen" :overview="seatsOverview" />
        <HotelDepartmentsDialog
            v-model:open="departmentsOpen"
            :overview="seatsOverview"
        />
        <HotelReasonDialog
            v-model:open="reasonOpen"
            :hotel="actionHotel"
            :mode="reasonMode"
        />
        <HotelExtendDialog v-model:open="extendOpen" :hotel="actionHotel" />
    </template>
</template>
