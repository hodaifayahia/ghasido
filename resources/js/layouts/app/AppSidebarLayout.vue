<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppTopbar from '@/components/shell/AppTopbar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    // Still accepted from page layouts; the Guesvia topbar has no breadcrumb
    // trail (the mockup shows none), the page title carries the location.
    breadcrumbs?: BreadcrumbItem[];
    topbarTaglineSrc?: string;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    topbarTaglineSrc: '/decor/script-real-situations.png',
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppTopbar :topbar-tagline-src="topbarTaglineSrc" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
