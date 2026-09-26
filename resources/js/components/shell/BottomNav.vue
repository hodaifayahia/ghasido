<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Star, TrendingUp, UserRound } from '@lucide/vue';
import type { Component } from 'vue';
import SolidHouseIcon from '@/components/icons/SolidHouseIcon.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { home, lessons, phrasebook, progress } from '@/routes/learn';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

/*
 * The employee's phone navigation (spec 0003 H.4, RESP-01; desgin/
 * 11-components.md §11.10): a 64px bottom tab bar with five tabs, the active
 * one in brand-600 under a 3px top indicator. Hidden from md up, where the
 * sidebar (or the lesson top bar) takes over.
 */
type Tab = {
    title: string;
    href: NavItem['href'];
    icon: Component;
    exact?: boolean;
};

const tabs: Tab[] = [
    { title: tk('Home'), href: home(), icon: SolidHouseIcon, exact: true },
    { title: tk('Lessons'), href: lessons(), icon: BookOpen },
    { title: tk('Phrasebook'), href: phrasebook(), icon: Star },
    { title: tk('Progress'), href: progress(), icon: TrendingUp },
    { title: tk('Profile'), href: editProfile(), icon: UserRound },
];

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isActive(tab: Tab): boolean {
    return tab.exact ? isCurrentUrl(tab.href) : isCurrentOrParentUrl(tab.href);
}
</script>

<template>
    <nav
        :aria-label="$t('Primary')"
        class="bg-surface border-line h-bottomnav fixed inset-x-0 bottom-0 z-20 flex border-t md:hidden"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.title"
            :href="tab.href"
            :aria-current="isActive(tab) ? 'page' : undefined"
            :class="
                cn(
                    'text-ink-slate focus-visible:ring-brand-600/40 relative flex min-w-11 flex-1 flex-col items-center justify-center gap-1 text-[11px] leading-none font-medium focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset',
                    isActive(tab) && 'text-brand-600',
                )
            "
        >
            <span
                v-if="isActive(tab)"
                aria-hidden="true"
                class="bg-brand-600 absolute inset-x-5 top-0 h-[3px] rounded-b-sm"
            />
            <component
                :is="tab.icon"
                class="size-6"
                :class="
                    tab.title === 'Phrasebook' &&
                    isActive(tab) &&
                    'fill-current'
                "
                aria-hidden="true"
            />
            {{ $t(tab.title) }}
        </Link>
    </nav>
</template>
