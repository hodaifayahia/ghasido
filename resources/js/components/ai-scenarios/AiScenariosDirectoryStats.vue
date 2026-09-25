<script setup lang="ts">
import { Bot, Building2, Check, Pencil } from '@lucide/vue';
import type { Component } from 'vue';
import StatCard from '@/components/common/StatCard.vue';
import type {
    AiScenarioDirectoryMetric,
    AiScenarioDirectoryMetricKey,
} from '@/types';

defineProps<{
    stats: AiScenarioDirectoryMetric[];
}>();

const icons: Record<AiScenarioDirectoryMetricKey, Component> = {
    totalScenarios: Bot,
    publishedScenarios: Check,
    draftScenarios: Pencil,
    departments: Building2,
};

const tones = {
    totalScenarios: 'brand',
    publishedScenarios: 'success',
    draftScenarios: 'warning',
    departments: 'ai',
} as const;
</script>

<template>
    <div class="grid grid-cols-2 gap-2 xl:grid-cols-4">
        <StatCard
            v-for="stat in stats"
            :key="stat.key"
            :value="stat.value"
            :label="stat.label"
            :detail="stat.detail"
            :tone="tones[stat.key]"
        >
            <template #icon>
                <component
                    :is="icons[stat.key]"
                    class="size-5"
                    aria-hidden="true"
                />
            </template>
        </StatCard>
    </div>
</template>
