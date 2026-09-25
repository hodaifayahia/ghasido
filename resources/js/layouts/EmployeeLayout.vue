<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    CircleQuestionMark,
    ClipboardCheck,
    GraduationCap,
    LogOut,
    Star,
} from '@lucide/vue';
import type { SidebarNav } from '@/components/AppSidebar.vue';
import { computed } from 'vue';
import SolidBarsIcon from '@/components/icons/SolidBarsIcon.vue';
import SolidHouseIcon from '@/components/icons/SolidHouseIcon.vue';
import SolidMailIcon from '@/components/icons/SolidMailIcon.vue';
import TrainingDepartmentSwitcher from '@/components/learning/TrainingDepartmentSwitcher.vue';
import BottomNav from '@/components/shell/BottomNav.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import {
    certificate,
    home,
    lessons,
    messages,
    phrasebook,
    progress,
} from '@/routes/learn';
import type { SidebarNavItem } from '@/types';

/*
 * The employee shell (spec 0003 H.1): the approved sidebar chrome with the
 * learner lists. Rhythm measured on desginphotos/employ/photo_20 at 1280×853:
 * first pill at y 101, 42px pills on a 53px pitch (11px gap), the divider
 * 33px under the last main item and 15px above Help, Help → Log out 56px
 * apart. The complete learner navigation stays stable on every page so
 * changing to My Progress cannot make another group appear or disappear.
 * Below md a bottom tab bar
 * carries the sections (H.4, RESP-01). No topbar tagline: the Home mockup
 * shows none.
 */
const solid = 'fill-current';
const outline = 'stroke-[2.25]';

const mainItems: SidebarNavItem[] = [
    { title: 'Home', href: home(), icon: SolidHouseIcon },
    {
        title: 'My Lessons',
        href: lessons(),
        icon: BookOpen,
        iconClass: outline,
    },
    {
        title: 'My Phrasebook',
        href: phrasebook(),
        icon: Star,
        iconClass: solid,
    },
    { title: 'My Progress', href: progress(), icon: SolidBarsIcon },
    { title: 'Messages', href: messages(), icon: SolidMailIcon },
];

const journeyItems: SidebarNavItem[] = [
    {
        title: 'Pre-test',
        href: home(),
        icon: ClipboardCheck,
        iconClass: `${solid} [&>path:last-child]:fill-none [&>path:last-child]:stroke-surface`,
    },
    {
        title: 'Training',
        href: lessons(),
        icon: GraduationCap,
        iconClass: outline,
    },
    {
        // The Post-test intro is the tests lane's: until it lands the entry
        // announces "coming soon" rather than pointing nowhere.
        title: 'Post-test',
        icon: ClipboardCheck,
        iconClass: `${solid} [&>path:last-child]:fill-none [&>path:last-child]:stroke-surface`,
    },
    {
        title: 'Certificate',
        href: certificate(),
        icon: Award,
        iconClass: outline,
    },
];

const accountItems: SidebarNavItem[] = [
    {
        title: 'Help',
        href: '/help',
        icon: CircleQuestionMark,
        iconClass: `${solid} [&>path]:fill-none [&>path]:stroke-surface`,
    },
    { title: 'Log out', icon: LogOut, action: 'logout', iconClass: outline },
];

// 11px and 14px gaps at 853px, shrinking with the sidebar's --sb-unit
// rhythm on shorter windows, as the admin nav does.
const pitch = 'gap-[clamp(2.5px,calc(var(--sb-unit)*1.41),11px)]';
const accountPitch = 'gap-[clamp(3px,calc(var(--sb-unit)*1.8),14px)]';
const page = usePage();

const nav = computed((): SidebarNav => ({
    homeHref: home(),
    contentClass: 'pt-[clamp(10px,calc(var(--sb-unit)*3.7),29px)]',
    main: { items: mainItems, class: pitch },
    journey: { items: journeyItems, class: pitch },
    account: { items: accountItems, class: accountPitch },
    separatorClass:
        'mt-[clamp(8px,calc(var(--sb-unit)*4.2),33px)] mb-[clamp(5px,calc(var(--sb-unit)*1.92),15px)]',
}));

// Employee-preview routes are also available to the Super Admin, and a
// manager works through the training as an employee (client decision
// 2026-09-23). Both keep their own admin sidebar — with the manager's "My
// Training" group — rather than the learner-only nav, so they never lose the
// back-office navigation while learning (ROLE-03, AC-5).
const keepsAdminNav = computed(() => {
    const role = page.props.auth.user?.role;

    return role === 'super_admin' || role === 'manager';
});

// A manager gets a compact department switcher above every learner page once
// they have chosen a department to train in (client decision 2026-09-23); for
// a real employee trainingContext is null and nothing renders.
const showSwitcher = computed(
    () => page.props.trainingContext?.currentDepartmentId != null,
);
</script>

<template>
    <AppSidebarLayout
        :nav="keepsAdminNav ? undefined : nav"
        topbar-tagline-src=""
    >
        <div class="pb-bottomnav flex min-w-0 flex-1 flex-col md:pb-0">
            <div v-if="showSwitcher" class="px-4 pt-4 md:px-6">
                <TrainingDepartmentSwitcher />
            </div>
            <slot />
        </div>
        <BottomNav />
    </AppSidebarLayout>
</template>
