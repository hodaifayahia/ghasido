<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const {
    breadcrumbs = [],
    topbarTaglineSrc,
    topbarTaglineText,
} = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    topbarTaglineSrc?: string;
    topbarTaglineText?: string;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :topbar-tagline-src="topbarTaglineSrc"
        :topbar-tagline-text="topbarTaglineText"
    >
        <slot />
    </AppLayout>
</template>
