import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import EmployeeLayout from '@/layouts/EmployeeLayout.vue';
import LessonLayout from '@/layouts/LessonLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Checkout':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            // The lesson runner and AI role-play share the step-tracker shell
            // (spec 0003 H.1); every other learner page gets the sidebar
            // shell with the employee nav. Order matters: the narrower
            // prefixes come first.
            case name.startsWith('employee/lesson/'):
            case name.startsWith('employee/roleplay/'):
                return LessonLayout;
            case name.startsWith('employee/'):
                return EmployeeLayout;
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#0B5CFF', // brand-600
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
