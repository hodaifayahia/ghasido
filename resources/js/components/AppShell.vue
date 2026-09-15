<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <!-- 200px sidebar / 72px collapsed icon rail — desgin/10-design-system.md
         §10.5. shadcn defaults are 16rem/3rem; override here rather than in the
         vendored components/ui/sidebar files. -->
    <SidebarProvider
        v-else
        :default-open="isOpen"
        :style="{
            '--sidebar-width': 'var(--spacing-sidebar)',
            '--sidebar-width-icon': 'var(--spacing-rail)',
        }"
    >
        <slot />
    </SidebarProvider>
</template>
