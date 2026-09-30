<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import EmployeeLayout from '@/layouts/EmployeeLayout.vue';

/*
 * The shell around Settings. An employee keeps the learner sidebar and the
 * bottom tab bar there (client report 2026-09-30: opening Settings used to
 * load the admin shell, whose items an employee cannot see, so the sidebar
 * emptied out); everyone else keeps the admin shell.
 */
const page = usePage();
const learner = computed(() => page.props.auth.user?.role === 'employee');
</script>

<template>
    <EmployeeLayout v-if="learner">
        <slot />
    </EmployeeLayout>
    <AppLayout v-else>
        <slot />
    </AppLayout>
</template>
