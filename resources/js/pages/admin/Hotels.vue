<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HotelsDirectoryPanel from '@/components/hotels/HotelsDirectoryPanel.vue';
import HotelsSidebarPanel from '@/components/hotels/HotelsSidebarPanel.vue';
import HotelsStatsRow from '@/components/hotels/HotelsStatsRow.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { dashboard, hotels as hotelsRoute } from '@/routes';
import type {
    HotelFilters,
    HotelMetric,
    HotelOverview,
    HotelPagination,
    HotelRecord,
} from '@/types';

type Props = {
    stats: HotelMetric[];
    filters: HotelFilters;
    hotels: HotelRecord[];
    pagination: HotelPagination;
    overview: HotelOverview;
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
                title: 'Hotels',
                href: hotelsRoute(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Hotels" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            title="Hotels"
            description="Manage contract periods, seat quotas and access across your hotel portfolio."
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <HotelsStatsRow :stats="stats" />

        <div class="grid min-w-0 gap-3 xl:grid-cols-4 xl:items-start">
            <HotelsDirectoryPanel
                :filters="filters"
                :hotels="hotels"
                :pagination="pagination"
                class="xl:col-span-3"
            />

            <HotelsSidebarPanel :overview="overview" />
        </div>
    </div>
</template>
