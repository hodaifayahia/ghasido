<script setup lang="ts">
import { ClipboardCheck, FileText, ListChecks, Pencil } from '@lucide/vue';
import type { Component } from 'vue';
import StatCard from '@/components/common/StatCard.vue';
import type { TestDirectoryMetric, TestDirectoryMetricKey } from '@/types';

defineProps<{
    stats: TestDirectoryMetric[];
}>();

const icons: Record<TestDirectoryMetricKey, Component> = {
    totalTests: ClipboardCheck,
    activeTests: ListChecks,
    draftTests: Pencil,
    questions: FileText,
};

const tones = {
    totalTests: 'brand',
    activeTests: 'success',
    draftTests: 'warning',
    questions: 'ai',
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
