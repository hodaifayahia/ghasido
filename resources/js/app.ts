import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { initializePwa } from '@/composables/usePwaInstall';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import EmployeeLayout from '@/layouts/EmployeeLayout.vue';
import LessonLayout from '@/layouts/LessonLayout.vue';
import OwnerLayout from '@/layouts/OwnerLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeI18n, installI18n } from '@/lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'GHASIDO';

// The interface language's strings load before the first render, so an
// Arabic page never flashes English first (I18N-02).
void initializeI18n().then(() =>
    createInertiaApp({
        withApp: (app) => installI18n(app),
        title: (title) => (title ? `${title} - ${appName}` : appName),
        layout: (name) => {
            switch (true) {
                case name === 'Welcome':
                case name === 'Contact':
                case name === 'Checkout':
                    return null;
                case name.startsWith('auth/'):
                case name === 'owner/Login':
                    return AuthLayout;
                // The platform owner's console (spec 0007): its own slim shell,
                // not the app sidebar, since the owner is not an app user.
                case name.startsWith('owner/'):
                    return OwnerLayout;
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
    }),
);

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Install as an app from the landing page (public/manifest.webmanifest).
initializePwa();
