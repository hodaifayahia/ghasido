<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import type { SidebarNav } from '@/components/AppSidebar.vue';
import AppTopbar from '@/components/shell/AppTopbar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    // Still accepted from page layouts; the Guesvia topbar has no breadcrumb
    // trail (the mockup shows none), the page title carries the location.
    breadcrumbs?: BreadcrumbItem[];
    topbarTaglineSrc?: string;
    /** The sidebar's lists; the approved admin lists when omitted. */
    nav?: SidebarNav;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    topbarTaglineSrc: '/decor/script-real-situations.png',
    nav: undefined,
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar :nav="nav" />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppTopbar :topbar-tagline-src="topbarTaglineSrc" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
