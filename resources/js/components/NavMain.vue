<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCan } from '@/composables/useCan';
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
const { can } = useCan();

const page = usePage();
const userRole = computed(
    (): string | null => page.props.auth.user?.role ?? null,
);

/*
 * An entry the user has no permission for is not rendered, and a role-specific
 * entry is only rendered for its roles. This is presentation only: every route
 * behind these links is authorized again on the server, so the filter tidies
 * the sidebar and never guards it (spec 0001, AC-6, invariant 4). Entries with
 * no `permission` and no `roles` always show, and a Super Admin holds every
 * permission, so the approved chrome renders exactly as the mockup draws it.
 */
const visibleItems = computed((): SidebarNavItem[] =>
    props.items.filter(
        (item) =>
            (!item.permission || can(item.permission)) &&
            (!item.roles ||
                (userRole.value !== null &&
                    item.roles.includes(userRole.value))),
    ),
);

function isActive(item: SidebarNavItem): boolean {
    const hrefs = item.href ? [item.href, ...(item.activeFor ?? [])] : [];

    return hrefs.some((href) => isCurrentUrl(href));
}

// The children a user may see, and whether any of them is the current page:
// a collapsible parent (e.g. "My Training") is marked active and starts open
// when one of its sub-items is active.
function visibleChildren(item: SidebarNavItem): SidebarNavItem[] {
    return (item.children ?? []).filter(
        (child) => !child.permission || can(child.permission),
    );
}

function isGroupActive(item: SidebarNavItem): boolean {
    return visibleChildren(item).some((child) => isActive(child));
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
 * Page links load their page while the pointer rests on them (75ms), so the
 * click shows it at once; lib/pagePrefetch.ts fetches the page's code too
 * and drops these copies after any change. Behaviour only: no visual change.
 */
const PREFETCH_CACHE = '10s';

/*
 * Measured from the approved Admin Dashboard mockup (1280px): 42px items on a
 * ~44.6px pitch, 10px radius, 26px glyph 14px in, label starting 20px after
 * it (x 73) in 13px indigo. Labels stay on one line like the mockup; the
 * collapsed rail clips back to the icon.
 */
const buttonClass = cn(
    'text-ink-indigo flex h-11 w-full min-w-0 justify-start gap-5 overflow-visible rounded-md ps-3.5 pe-1 text-[13px] leading-none font-medium tracking-[-0.02em] md:h-[clamp(32px,calc(var(--sb-unit)*5.38),42px)]',
    'ease-brand transition-[width,height,padding,background-color] duration-150',
    'hover:bg-brand-50 hover:text-ink-indigo active:bg-brand-50 active:text-ink-indigo',
    'focus-visible:ring-brand-600/40 focus-visible:ring-2',
    'data-[active=true]:bg-brand-100/70 data-[active=true]:text-brand-600 data-[active=true]:font-semibold',
    '[&>svg]:text-brand-900 data-[active=true]:[&>svg]:text-brand-600 [&>svg]:size-[26px]',
    '[&>span:last-child]:overflow-visible! [&>span:last-child]:whitespace-nowrap!',
    'group-data-[collapsible=icon]:size-11! group-data-[collapsible=icon]:overflow-hidden group-data-[collapsible=icon]:p-[9px]!',
);

// Sub-items of a collapsible group (a manager's "My Training"): the same
// indigo label and brand-active treatment as a top-level item, one step
// smaller and indented under the parent. Hidden on the collapsed icon rail.
const subButtonClass = cn(
    'text-ink-indigo h-9 w-full min-w-0 justify-start gap-3 rounded-md ps-2.5 pe-1 text-[13px] leading-none font-medium tracking-[-0.02em]',
    'ease-brand transition-[width,height,padding,background-color] duration-150',
    'hover:bg-brand-50 hover:text-ink-indigo active:bg-brand-50 active:text-ink-indigo',
    'focus-visible:ring-brand-600/40 focus-visible:ring-2',
    'data-[active=true]:bg-brand-100/70 data-[active=true]:text-brand-600 data-[active=true]:font-semibold',
    '[&>svg]:text-brand-900 data-[active=true]:[&>svg]:text-brand-600 [&>svg]:size-[20px]',
    '[&>span:last-child]:overflow-visible! [&>span:last-child]:whitespace-nowrap!',
);
</script>

<template>
    <SidebarGroup
        class="px-[13px] py-0 group-data-[collapsible=icon]:px-[14px]"
    >
        <SidebarMenu :class="cn('gap-[2.5px]', props.class)">
            <SidebarMenuItem
                v-for="item in visibleItems"
                :key="item.title"
                class="w-full min-w-0"
            >
                <Collapsible
                    v-if="item.children && item.children.length > 0"
                    as-child
                    :default-open="isGroupActive(item)"
                    class="group/collapsible w-full"
                >
                    <div class="w-full min-w-0">
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                type="button"
                                :is-active="isGroupActive(item)"
                                :tooltip="$t(item.title)"
                                :class="buttonClass"
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    :class="item.iconClass"
                                    aria-hidden="true"
                                />
                                <span :class="item.labelClass">{{
                                    $t(item.title)
                                }}</span>
                                <ChevronRight
                                    class="ms-auto size-4! transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                    aria-hidden="true"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent
                            class="group-data-[collapsible=icon]:hidden"
                        >
                            <SidebarMenuSub
                                class="mx-3.5 gap-[2.5px] border-l-0 px-0"
                            >
                                <SidebarMenuSubItem
                                    v-for="child in visibleChildren(item)"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        v-if="child.href"
                                        as-child
                                        :is-active="isActive(child)"
                                        :class="subButtonClass"
                                    >
                                        <Link
                                            :href="child.href"
                                            prefetch
                                            :cache-for="PREFETCH_CACHE"
                                            :aria-current="
                                                isActive(child)
                                                    ? 'page'
                                                    : undefined
                                            "
                                            @click="closeMobileSidebar"
                                        >
                                            <component
                                                :is="child.icon"
                                                v-if="child.icon"
                                                :class="child.iconClass"
                                                aria-hidden="true"
                                            />
                                            <span :class="child.labelClass">{{
                                                $t(child.title)
                                            }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                    <SidebarMenuSubButton
                                        v-else
                                        as="button"
                                        type="button"
                                        :class="subButtonClass"
                                        @click="
                                            notifyComingSoon($t(child.title))
                                        "
                                    >
                                        <component
                                            :is="child.icon"
                                            v-if="child.icon"
                                            :class="child.iconClass"
                                            aria-hidden="true"
                                        />
                                        <span :class="child.labelClass">{{
                                            $t(child.title)
                                        }}</span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </div>
                </Collapsible>

                <SidebarMenuButton
                    v-else-if="item.action === 'logout'"
                    as-child
                    :tooltip="$t(item.title)"
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
                        <span :class="item.labelClass">{{
                            $t(item.title)
                        }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuButton
                    v-else-if="item.href"
                    as-child
                    :is-active="isActive(item)"
                    :tooltip="$t(item.title)"
                    :class="buttonClass"
                >
                    <Link
                        :href="item.href"
                        prefetch
                        :cache-for="PREFETCH_CACHE"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        @click="closeMobileSidebar"
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            :class="item.iconClass"
                            aria-hidden="true"
                        />
                        <span :class="item.labelClass">{{
                            $t(item.title)
                        }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuButton
                    v-else
                    type="button"
                    :tooltip="$t(item.title)"
                    :class="buttonClass"
                    @click="notifyComingSoon($t(item.title))"
                >
                    <component
                        :is="item.icon"
                        v-if="item.icon"
                        :class="item.iconClass"
                        aria-hidden="true"
                    />
                    <span :class="item.labelClass">{{ $t(item.title) }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
