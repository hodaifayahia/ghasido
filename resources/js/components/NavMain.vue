<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { Link, router } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import type { SidebarNavItem } from '@/types';

type Props = {
    items: SidebarNavItem[];
    /** Merged into the menu list — use it to set the item pitch (gap). */
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile } = useSidebar();

function isActive(item: SidebarNavItem): boolean {
    const hrefs = item.href ? [item.href, ...(item.activeFor ?? [])] : [];

    return hrefs.some((href) => isCurrentUrl(href));
}

function closeMobileSidebar(): void {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}

function handleLogout(): void {
    router.flushAll();
}

/*
 * Measured from the approved Admin Dashboard mockup (1280px): 42px items on a
 * ~44.6px pitch, 10px radius, 26px glyph 14px in, label starting 20px after
 * it (x 73) in 13px indigo. Labels stay on one line like the mockup; the
 * collapsed rail clips back to the icon.
 */
const buttonClass = cn(
    'text-ink-indigo h-11 gap-5 overflow-visible rounded-md ps-3.5 pe-1 text-[13px] leading-none font-medium tracking-[-0.02em] md:h-[clamp(32px,calc(var(--sb-unit)*5.38),42px)]',
    'ease-brand transition-[width,height,padding,background-color] duration-150',
    'hover:bg-brand-50 hover:text-ink-indigo active:bg-brand-50 active:text-ink-indigo',
    'focus-visible:ring-brand-600/40 focus-visible:ring-2',
    'data-[active=true]:bg-brand-100/70 data-[active=true]:text-brand-600 data-[active=true]:font-semibold',
    '[&>svg]:text-brand-900 data-[active=true]:[&>svg]:text-brand-600 [&>svg]:size-[26px]',
    '[&>span:last-child]:overflow-visible! [&>span:last-child]:whitespace-nowrap!',
    'group-data-[collapsible=icon]:size-11! group-data-[collapsible=icon]:overflow-hidden group-data-[collapsible=icon]:p-[9px]!',
);
</script>

<template>
    <SidebarGroup
        class="px-[13px] py-0 group-data-[collapsible=icon]:px-[14px]"
    >
        <SidebarMenu :class="cn('gap-[2.5px]', props.class)">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    v-if="item.action === 'logout'"
                    as-child
                    :tooltip="item.title"
                    :class="buttonClass"
                >
                    <Link
                        :href="logout()"
                        as="button"
                        data-test="sidebar-logout-button"
                        @click="handleLogout"
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            :class="item.iconClass"
                            aria-hidden="true"
                        />
                        <span :class="item.labelClass">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuButton
                    v-else-if="item.href"
                    as-child
                    :is-active="isActive(item)"
                    :tooltip="item.title"
                    :class="buttonClass"
                >
                    <Link
                        :href="item.href"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        @click="closeMobileSidebar"
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            :class="item.iconClass"
                            aria-hidden="true"
                        />
                        <span :class="item.labelClass">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuButton
                    v-else
                    type="button"
                    :tooltip="item.title"
                    :class="buttonClass"
                    @click="notifyComingSoon(item.title)"
                >
                    <component
                        :is="item.icon"
                        v-if="item.icon"
                        :class="item.iconClass"
                        aria-hidden="true"
                    />
                    <span :class="item.labelClass">{{ item.title }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
