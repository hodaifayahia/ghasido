<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Bot,
    CircleQuestionMark,
    ClipboardCheck,
    LogOut,
    Settings,
    User,
} from '@lucide/vue';
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
import {
    aiScenarios,
    dashboard,
    departments,
    employees,
    hotels,
    lessonsContent,
    messagesReminders,
    reportsExport,
} from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { SidebarNavItem } from '@/types';

const { isMobile, setOpenMobile } = useSidebar();

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
    { title: 'Dashboard', href: dashboard(), icon: SolidHouseIcon },
    { title: 'Hotels', href: hotels(), icon: SolidBuildingIcon },
    { title: 'Departments', href: departments(), icon: SolidUsersGroupIcon },
    { title: 'Employees', href: employees(), icon: User, iconClass: solid },
    {
        title: 'Lessons & Content',
        href: lessonsContent(),
        icon: BookOpen,
        iconClass: outline,
    },
    {
        title: 'AI Scenarios',
        href: aiScenarios(),
        icon: Bot,
        iconClass: `${solid} [&>path:first-child]:fill-none [&>path:nth-last-child(-n+2)]:stroke-surface`,
    },
    {
        title: 'Pre-test & Post-test',
        icon: ClipboardCheck,
        iconClass: `${solid} [&>path:last-child]:fill-none [&>path:last-child]:stroke-surface`,
    },
    {
        title: 'Messages & Reminders',
        href: messagesReminders(),
        icon: SolidMailIcon,
        // The longest label: a touch tighter so it stays on one line in the
        // 200px sidebar, as in the mockup.
        labelClass: 'text-[12px] tracking-[-0.045em]',
    },
    {
        title: 'Reports & Export',
        href: reportsExport(),
        icon: SolidBarsIcon,
    },
];

const accountNavItems: SidebarNavItem[] = [
    {
        title: 'Settings',
        href: editProfile(),
        activeFor: [editSecurity(), editAppearance()],
        icon: Settings,
        iconClass: `${solid} [&>circle]:fill-surface [&>circle]:stroke-none`,
    },
    {
        title: 'Help',
        icon: CircleQuestionMark,
        iconClass: `${solid} [&>path]:fill-none [&>path]:stroke-surface`,
    },
    { title: 'Log out', icon: LogOut, action: 'logout', iconClass: outline },
];

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
        class="group-data-[side=left]:border-r-0"
    >
        <SidebarHeader
            class="h-topbar bg-sidebar shrink-0 flex-row items-start p-0 ps-8 pt-[11px] group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:ps-0 group-data-[collapsible=icon]:pt-0"
        >
            <Link
                :href="dashboard()"
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
            class="gap-0 overflow-x-hidden pt-[clamp(10px,calc(var(--sb-unit)*3.2),25px)] [--sb-unit:calc((100svh_-_72px)/100)]"
        >
            <nav aria-label="Main" class="flex shrink-0 flex-col">
                <NavMain :items="mainNavItems" />
                <SidebarSeparator
                    class="bg-ink-faint/15 mx-[22px] mt-[clamp(6px,calc(var(--sb-unit)*1.92),15px)] mb-[clamp(4px,calc(var(--sb-unit)*1.15),9px)] data-[orientation=horizontal]:h-0.5 data-[orientation=horizontal]:w-auto"
                />
                <NavMain :items="accountNavItems" class="gap-[5px]" />
            </nav>

            <!-- Brand footer: the client's palm-island artwork with "Hotel
                 People. Brighter Futures." (desgin/assets/palm-island-tagline-
                 original.png, cropped to the artwork on a transparent
                 background). It fills whatever height the nav leaves and
                 scales down inside it, so it can never push the nav into a
                 scroll or spill past the sidebar. -->
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
                    class="absolute start-4 bottom-[clamp(12px,calc(var(--sb-unit)*5.4),42px)] h-auto max-h-[calc(100%_-_clamp(12px,calc(var(--sb-unit)*5.4),42px)_-_12px)] w-auto max-w-[128px] opacity-65"
                />
            </div>
        </SidebarContent>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
