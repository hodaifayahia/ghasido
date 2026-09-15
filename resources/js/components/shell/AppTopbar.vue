<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, Globe } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Props = {
    class?: HTMLAttributes['class'];
    topbarTaglineSrc?: string;
};

const props = defineProps<Props>();

// Roles are not modelled yet (ROLE-01); every signed-in user is the admin.
const ROLE_LABEL = 'System Administrator';

const page = usePage();
const user = computed(() => page.props.auth.user);
const unread = computed(() => page.props.notifications.unread);

const { getInitials } = useInitials();
const { isMobile, state } = useSidebar();

const firstName = computed(
    () => user.value.name.trim().split(/\s+/u)[0] ?? user.value.name,
);
const initial = computed(() => getInitials(firstName.value));
const hasAvatar = computed(() => Boolean(user.value.avatar));

const unreadBadge = computed(() =>
    unread.value > 9 ? '9+' : String(unread.value),
);
const bellLabel = computed(() =>
    unread.value > 0
        ? `Notifications, ${unread.value} unread`
        : 'Notifications, none unread',
);

const iconButtonClass =
    'rounded-md transition-colors duration-150 ease-brand hover:bg-brand-50 focus-visible:ring-2 focus-visible:ring-brand-600/40 focus-visible:outline-none data-[state=open]:bg-brand-50';
</script>

<template>
    <header
        :class="
            cn(
                'relative h-topbar bg-surface sticky top-0 z-9 flex shrink-0 items-center gap-1.5 ps-2 pe-3 pt-[3px] md:ps-4 md:pe-5 xl:ps-6 xl:pe-[22px]',
                props.class,
            )
        "
    >
        <!-- Start: the sidebar lives off-canvas below md and can collapse to a
             rail on tablets, so the trigger (and, on phones, the mark) shows
             here. On a desktop with the sidebar open the logo sits in the
             sidebar header and this side stays empty, as in the mockup. -->
        <SidebarTrigger
            :class="
                cn(
                    'text-brand-800 hover:bg-brand-50 hover:text-brand-800 focus-visible:ring-brand-600/40 size-11 focus-visible:ring-2 [&_svg:not([class*=\'size-\'])]:size-5',
                    state === 'expanded' && !isMobile && 'xl:hidden',
                )
            "
        />
        <Link
            v-if="isMobile"
            :href="dashboard()"
            class="focus-visible:ring-brand-600/40 flex shrink-0 items-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
        >
            <AppLogo variant="mark" class="size-10" />
        </Link>

        <img
            v-if="props.topbarTaglineSrc"
            :src="props.topbarTaglineSrc"
            alt=""
            aria-hidden="true"
            width="513"
            height="81"
            draggable="false"
            class="pointer-events-none absolute start-1/2 top-[11px] hidden w-[314px] -translate-x-1/2 select-none xl:block"
        />

        <div class="ms-auto flex min-w-0 items-center">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        :aria-label="bellLabel"
                        :class="
                            cn(
                                'text-brand-800/85 flex size-11 shrink-0 items-center justify-center',
                                iconButtonClass,
                            )
                        "
                    >
                        <span class="relative flex">
                            <Bell
                                class="size-6 fill-current"
                                aria-hidden="true"
                            />
                            <span
                                v-if="unread > 0"
                                aria-hidden="true"
                                class="rounded-pill bg-danger ring-surface absolute -end-[4px] -top-[4px] flex h-[11px] min-w-[11px] items-center justify-center px-[2px] text-[7.5px] leading-none font-bold text-white ring-1"
                            >
                                {{ unreadBadge }}
                            </span>
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    class="shadow-pop w-72 rounded-lg p-0"
                >
                    <DropdownMenuLabel
                        class="flex items-center justify-between gap-2 px-4 py-3"
                    >
                        <span
                            class="font-heading text-brand-800 text-sm font-semibold"
                        >
                            Notifications
                        </span>
                        <span
                            v-if="unread > 0"
                            class="rounded-pill bg-danger-tint text-danger-text px-2 py-0.5 text-xs font-semibold"
                        >
                            {{ unread }} unread
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator class="mx-0 my-0" />
                    <p class="text-body-sm text-ink-muted px-4 pt-3 pb-2">
                        Your notification centre is coming soon.
                    </p>
                    <div class="px-2 pb-2">
                        <DropdownMenuItem
                            class="text-brand-700 cursor-pointer rounded-sm px-2 py-2 font-medium"
                            @select="notifyComingSoon('Notifications')"
                        >
                            View all notifications
                        </DropdownMenuItem>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-test="topbar-user-menu"
                        :class="
                            cn(
                                'ms-1 flex min-w-0 items-center gap-[15px] p-1 text-start md:ms-[15px]',
                                iconButtonClass,
                            )
                        "
                    >
                        <Avatar class="size-[34px]">
                            <AvatarImage
                                v-if="hasAvatar"
                                :src="user.avatar ?? ''"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-brand-800/85 font-heading text-[15px] font-semibold text-white"
                            >
                                {{ initial }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="sr-only md:hidden">Account menu</span>
                        <span class="hidden min-w-0 flex-col md:flex">
                            <span
                                class="font-heading text-ink-indigo truncate text-[13.5px] leading-5 font-semibold tracking-[-0.02em]"
                            >
                                Welcome, {{ firstName }}!
                            </span>
                            <span
                                class="text-ink-slate mt-px truncate text-[12.5px] leading-[18px] tracking-[-0.01em]"
                            >
                                {{ ROLE_LABEL }}
                            </span>
                        </span>
                        <ChevronDown
                            class="text-ink-indigo -ms-[5px] -mt-2 hidden size-6 shrink-0 stroke-[2.5] md:block"
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    class="min-w-56 rounded-lg"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        aria-label="Language: English"
                        class="border-line bg-surface shadow-card ease-brand hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 data-[state=open]:bg-brand-50 ms-2 flex h-11 shrink-0 items-center rounded-md border ps-2.5 pe-1.5 transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none md:ms-6 md:h-[46px] md:w-[111px] md:ps-[17px] md:pe-[11px]"
                    >
                        <Globe
                            class="text-brand-700 size-[22px] shrink-0"
                            aria-hidden="true"
                        />
                        <span
                            class="text-ink-indigo ms-2 text-base leading-none font-semibold"
                        >
                            EN
                        </span>
                        <ChevronDown
                            class="text-ink-slate ms-auto size-6 shrink-0 stroke-[2.25]"
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    class="min-w-44 rounded-md"
                >
                    <DropdownMenuLabel class="text-ink-muted text-xs">
                        Language
                    </DropdownMenuLabel>
                    <DropdownMenuCheckboxItem :model-value="true">
                        English
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuItem disabled class="justify-between ps-8">
                        <span lang="ar" dir="rtl">العربية</span>
                        <span
                            class="rounded-pill bg-tint-grid text-ink-muted px-1.5 py-0.5 text-[10px] leading-none font-semibold uppercase"
                        >
                            Soon
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
