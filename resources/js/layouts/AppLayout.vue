<script setup lang="ts">
import WelcomeSplash from '@/components/shell/WelcomeSplash.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [], topbarTaglineSrc } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    topbarTaglineSrc?: string;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :topbar-tagline-src="topbarTaglineSrc"
    >
        <slot />
        <WelcomeSplash />
    </AppLayout>
</template>
