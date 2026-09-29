<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { tk } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { edit as editAiModels } from '@/routes/ai-models';
import { index as aiUsage } from '@/routes/ai-usage';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editMailSettings } from '@/routes/mail-settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const page = usePage();
const isWideSettingsPage = computed(
    () =>
        page.component === 'settings/LandingPage' ||
        page.component === 'settings/AiUsage',
);

// "AI models" and "Email" are the Super Admin's tabs only; the route itself is a 403 for
// everyone else, so hiding it here is presentation (API-04, ROLE-01).
const sidebarNavItems = computed((): NavItem[] => [
    {
        title: tk('Profile'),
        href: editProfile(),
    },
    {
        title: tk('Security'),
        href: editSecurity(),
    },
    {
        title: tk('Appearance'),
        href: editAppearance(),
    },
    ...(page.props.auth.user?.role === 'super_admin'
        ? [
              { title: tk('AI models'), href: editAiModels() },
              { title: tk('AI usage'), href: aiUsage() },
              { title: tk('Email'), href: editMailSettings() },
              { title: tk('Landing page'), href: '/settings/landing-page' },
          ]
        : []),
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="$t('Settings')"
            :description="$t('Manage your profile and account settings')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    :aria-label="$t('Settings')"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ $t(item.title) }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div
                :class="
                    isWideSettingsPage
                        ? 'min-w-0 flex-1'
                        : 'flex-1 md:max-w-2xl'
                "
            >
                <section
                    :class="
                        isWideSettingsPage
                            ? 'min-w-0 space-y-12'
                            : 'max-w-xl space-y-12'
                    "
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
