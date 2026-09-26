<script setup lang="ts">
// The content lives in a component so employee/Help.vue can reuse it without
// importing this page: a page imported by another page loses its own entry
// in the production Vite manifest, and Inertia then cannot render 'Help'.
import HelpCenter from '@/components/help/HelpCenter.vue';
import { tk } from '@/lib/i18n';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: '/dashboard' },
            { title: tk('Help'), href: '/help' },
        ],
    },
});
</script>

<template>
    <HelpCenter />
</template>
