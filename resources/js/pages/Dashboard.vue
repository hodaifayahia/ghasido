<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardStats from '@/components/dashboard/DashboardStats.vue';
import DepartmentProgressPanel from '@/components/dashboard/DepartmentProgressPanel.vue';
import QuickActionsPanel from '@/components/dashboard/QuickActionsPanel.vue';
import TrainingProgressPanel from '@/components/dashboard/TrainingProgressPanel.vue';
import ActivityFeed from '@/components/data/ActivityFeed.vue';
import NeedsAttentionList from '@/components/data/NeedsAttentionList.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { dashboard } from '@/routes';
import type {
    AttentionGroup,
    DashboardStat,
    DepartmentProgress,
    RecentActivity,
    TrainingOverview,
} from '@/types';

type Props = {
    stats: DashboardStat[];
    trainingOverview: TrainingOverview;
    departmentProgress: DepartmentProgress[];
    needsAttention: AttentionGroup[];
    recentActivity: RecentActivity[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            title="Admin Dashboard"
            description="Manage your hotels, staff, content and track progress"
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <DashboardStats :stats="stats" />

        <div
            class="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-[388fr_302fr_320fr]"
        >
            <TrainingProgressPanel :overview="trainingOverview" />
            <DepartmentProgressPanel :departments="departmentProgress" />
            <QuickActionsPanel class="md:col-span-2 xl:col-span-1" />
        </div>

        <div class="grid min-w-0 gap-3 xl:grid-cols-[536fr_481fr] xl:gap-4">
            <NeedsAttentionList :groups="needsAttention" />
            <ActivityFeed :items="recentActivity" />
        </div>
    </div>
</template>
