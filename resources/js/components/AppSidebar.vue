<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    Coins,
    CreditCard,
    BookOpen,
    Bot,
    CircleQuestionMark,
    ClipboardCheck,
    GraduationCap,
    Globe,
    LogOut,
    Settings,
    ShieldCheck,
    Star,
    User,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import SolidBarsIcon from '@/components/icons/SolidBarsIcon.vue';
import SolidBuildingIcon from '@/components/icons/SolidBuildingIcon.vue';
import SolidHouseIcon from '@/components/icons/SolidHouseIcon.vue';
import SolidMailIcon from '@/components/icons/SolidMailIcon.vue';
import SolidUsersGroupIcon from '@/components/icons/SolidUsersGroupIcon.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarRail,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    aiScenarios,
    aiPoints as aiPointsRoute,
    dashboard,
    departments,
    employees,
    hotels,
    lessonsContent,
    messagesReminders,
    payments,
    reportsExport,
    roles,
    subscriptions,
    tests,
} from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editLandingPage } from '@/routes/landing-page';
import {
    certificate as learnCertificate,
    home as learnHome,
    lessons as learnLessons,
    messages as learnMessages,
    phrasebook as learnPhrasebook,
    progress as learnProgress,
} from '@/routes/learn';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem, SidebarNavItem } from '@/types';

/** One group of entries; `class` merges into its list (the item pitch). */
export type SidebarNavGroup = {
    items: SidebarNavItem[];
    class?: HTMLAttributes['class'];
};

/**
 * The sidebar's contents (spec 0003 H.1). The admin lists below are the
 * default, so the approved admin chrome renders unchanged; EmployeeLayout
 * passes the learner lists. `journey` (Pre-test → Certificate) is drawn
 * between the main and account groups with the same divider treatment.
 */
export type SidebarNav = {
    /** Where the logo links; the admin dashboard when omitted. */
    homeHref?: NavItem['href'];
    main: SidebarNavGroup;
    journey?: SidebarNavGroup;
    account: SidebarNavGroup;
    /** Merged into every divider (its vertical margins). */
    separatorClass?: HTMLAttributes['class'];
    /** Merged into the scrolling content (its top padding). */
    contentClass?: HTMLAttributes['class'];
};

type Props = {
    nav?: SidebarNav;
};

const props = defineProps<Props>();

const { isMobile, setOpenMobile } = useSidebar();
// Arabic lays the page out right to left, sidebar on the right (I18N-02).
const { isRtl } = useI18n();
const page = usePage();

/*
 * The mockup's glyphs are solid. Lucide's outline icons are filled where their
 * inner details are drawn on top (robot, clipboard, gear, question mark) and
 * re-stroked in the surface colour; the glyphs whose details Lucide draws
 * underneath the outline (house door, building windows, envelope flap) or that
 * Lucide lacks (three-person group, chunky bars) are small solid SVGs in
 * components/icons with real cut-outs.
 */
const solid = 'fill-current';
const outline = 'stroke-[2.25]';

// Sections without a route yet have no href: NavMain renders them as buttons
// that announce "coming soon" instead of failing silently.
const mainNavItems: SidebarNavItem[] = [
    { title: tk('Dashboard'), href: dashboard(), icon: SolidHouseIcon },
    {
        title: tk('Hotels'),
        href: hotels(),
        icon: SolidBuildingIcon,
        permission: 'hotels.view',
    },
    {
        title: tk('Subscriptions'),
        href: subscriptions(),
        icon: CreditCard,
        permission: 'subscriptions.manage',
    },
    {
        // Checkout payments to review (client request 2026-09-27); the
        // badge is the shared pendingPayments count.
        title: tk('Payments'),
        href: payments(),
        // Outline like its neighbour Subscriptions (CreditCard).
        icon: Wallet,
        permission: 'subscriptions.manage',
    },
    {
        title: tk('Departments'),
        href: departments(),
        icon: SolidUsersGroupIcon,
        permission: 'departments.view',
    },
    {
        title: tk('Employees'),
        href: employees(),
        icon: User,
        iconClass: solid,
        permission: 'employees.view',
    },
    {
        title: tk('AI Points'),
        href: aiPointsRoute(),
        icon: Coins,
        roles: ['admin', 'manager'],
        permission: 'ai_points.manage',
    },
    {
        title: tk('Lessons & Content'),
        href: lessonsContent(),
        icon: BookOpen,
        iconClass: outline,
        permission: 'lessons.view',
    },
    {
        title: tk('AI Scenarios'),
        href: aiScenarios(),
        icon: Bot,
        iconClass: `${solid} [&>path:first-child]:fill-none [&>path:nth-last-child(-n+2)]:stroke-surface`,
        permission: 'scenarios.view',
    },
    {
        title: tk('Pre-test & Post-test'),
        href: tests(),
        icon: ClipboardCheck,
        iconClass: `${solid} [&>path:last-child]:fill-none [&>path:last-child]:stroke-surface`,
        permission: 'tests.view',
    },
    {
        title: tk('Messages & Reminders'),
        href: messagesReminders(),
        icon: SolidMailIcon,
        // The longest label: a touch tighter so it stays on one line in the
        // Keep the longest label comfortable in the expanded 280px sidebar.
        labelClass: 'text-[12px] tracking-[-0.045em]',
        permission: 'messages.view',
    },
    {
        title: tk('Reports & Export'),
        href: reportsExport(),
        icon: SolidBarsIcon,
        permission: 'reports.view',
    },
    {
        // A manager also learns as an employee (client decision 2026-09-23):
        // a collapsible group that opens to the full learner navigation.
        // Shown only to managers; the Super Admin previews training through
        // the CMS, and every learner route is authorized on the server.
        title: tk('My Training'),
        icon: GraduationCap,
        iconClass: outline,
        roles: ['manager'],
        children: [
            { title: tk('Home'), href: learnHome(), icon: SolidHouseIcon },
            {
                title: tk('My Lessons'),
                href: learnLessons(),
                icon: BookOpen,
                iconClass: outline,
            },
            {
                title: tk('My Phrasebook'),
                href: learnPhrasebook(),
                icon: Star,
                iconClass: solid,
            },
            {
                title: tk('My Progress'),
                href: learnProgress(),
                icon: SolidBarsIcon,
            },
            {
                title: tk('Messages'),
                href: learnMessages(),
                icon: SolidMailIcon,
            },
            {
                title: tk('Certificate'),
                href: learnCertificate(),
                icon: Award,
                iconClass: outline,
            },
        ],
    },
    {
        title: tk('Roles & Permissions'),
        href: roles(),
        icon: ShieldCheck,
        iconClass: outline,
        // Only the Super Admin holds roles.view, so this item is invisible to
        // every other role and the approved mockup is unchanged for them.
        permission: 'roles.view',
    },
    {
        title: tk('Users'),
        href: '/users',
        icon: User,
        iconClass: solid,
        // App access accounts are managed separately from hotel employees.
        permission: 'users.view',
    },
    {
        title: tk('Website Management'),
        href: editLandingPage(),
        icon: Globe,
        iconClass: outline,
        // Public website content is managed by the Super Admin (ADM-02,
        // ROLE-01, SEC-01); the route also enforces this permission server-side.
        roles: ['super_admin'],
    },
];

const accountNavItems: SidebarNavItem[] = [
    {
        title: tk('Settings'),
        href: editProfile(),
        activeFor: [editSecurity(), editAppearance()],
        icon: Settings,
        iconClass: `${solid} [&>circle]:fill-surface [&>circle]:stroke-none`,
    },
    {
        title: tk('Help'),
        href: '/help',
        icon: CircleQuestionMark,
        iconClass: `${solid} [&>path]:fill-none [&>path]:stroke-surface`,
    },
    {
        title: tk('Log out'),
        icon: LogOut,
        action: 'logout',
        iconClass: outline,
    },
];

const adminNav = computed((): SidebarNav => ({
    main: {
        items: mainNavItems.map((item) =>
            item.title === 'Payments'
                ? { ...item, badge: page.props.pendingPayments ?? 0 }
                : item,
        ),
    },
    account: { items: accountNavItems, class: 'gap-[5px]' },
}));

const nav = computed((): SidebarNav => props.nav ?? adminNav.value);
const homeHref = computed(() => nav.value.homeHref ?? dashboard());

// Divider: 2px, inset 22px, 15px above / 9px below at 853px, scaling with
// the same --sb-unit rhythm as the items.
const separatorClass =
    'bg-ink-faint/15 mx-[22px] mt-[clamp(6px,calc(var(--sb-unit)*1.92),15px)] mb-[clamp(4px,calc(var(--sb-unit)*1.15),9px)] data-[orientation=horizontal]:h-0.5 data-[orientation=horizontal]:w-auto';

function closeMobileSidebar(): void {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <!-- White, borderless panel: in the mockup the sidebar and topbar are
         separated from the light-blue content by tone alone. -->
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        :side="isRtl ? 'right' : 'left'"
        class="group-data-[side=left]:border-r-0 group-data-[side=right]:border-l-0"
    >
        <SidebarHeader
            class="h-topbar bg-sidebar shrink-0 flex-row items-start p-0 ps-8 pt-[11px] group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:ps-0 group-data-[collapsible=icon]:pt-0"
        >
            <Link
                :href="homeHref"
                class="focus-visible:ring-brand-600/40 flex shrink-0 rounded-md focus-visible:ring-2 focus-visible:outline-none"
                @click="closeMobileSidebar"
            >
                <AppLogo class="group-data-[collapsible=icon]:hidden" />
                <AppLogo
                    variant="mark"
                    class="hidden size-10 group-data-[collapsible=icon]:block"
                />
            </Link>
        </SidebarHeader>

        <!-- Vertical rhythm is the mockup's at its 853px height (25px top gap,
             42px items, divider 15px/9px) and shrinks with shorter windows
             (--sb-unit = 1% of the height below the topbar), so the nav and
             the brand footer always fit: no scrollbar, no clipped labels. -->
        <SidebarContent
            :class="
                cn(
                    'gap-0 overflow-x-hidden pt-[clamp(10px,calc(var(--sb-unit)*3.2),25px)] [--sb-unit:calc((100svh_-_72px)/100)]',
                    nav.contentClass,
                )
            "
        >
            <nav :aria-label="$t('Main')" class="flex shrink-0 flex-col">
                <NavMain :items="nav.main.items" :class="nav.main.class" />
                <SidebarSeparator
                    :class="cn(separatorClass, nav.separatorClass)"
                />
                <template v-if="nav.journey">
                    <NavMain
                        :items="nav.journey.items"
                        :class="nav.journey.class"
                    />
                    <SidebarSeparator
                        :class="cn(separatorClass, nav.separatorClass)"
                    />
                </template>
                <NavMain
                    :items="nav.account.items"
                    :class="nav.account.class"
                />
            </nav>

            <!-- Brand footer: the client's palm-island artwork with "Hotel
                 People. Brighter Futures." (desgin/assets/palm-island-tagline-
                 original.png, cropped to the artwork on a transparent
                 background). Keep its approved 128px width on full-height
                 layouts, and scale it down with the sidebar rhythm on short
                 windows so the menu stays usable. -->
            <div
                aria-hidden="true"
                class="pointer-events-none relative min-h-0 flex-1 overflow-hidden select-none group-data-[collapsible=icon]:hidden"
            >
                <img
                    src="/decor/palm-island-tagline.png"
                    alt=""
                    width="540"
                    height="410"
                    draggable="false"
                    class="absolute start-4 bottom-[clamp(12px,calc(var(--sb-unit)*5.4),42px)] h-auto w-[clamp(40px,calc(var(--sb-unit)*41_-_192px),128px)] opacity-65"
                />
            </div>
        </SidebarContent>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
