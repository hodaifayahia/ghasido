<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Menu, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import LanguageToggle from '@/components/landing/LanguageToggle.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { contact, dashboard, login } from '@/routes';
import type { LandingPageContent } from '@/types';

/*
 * The public site's header, shared by the landing page and Contact Us
 * (client decision 2026-09-26). On the landing page the section links are
 * in-page anchors; elsewhere they lead back to the landing page's sections.
 */
type Props = {
    content: LandingPageContent;
    anchorBase?: string;
    active?: 'home' | 'contact';
};

withDefaults(defineProps<Props>(), { anchorBase: '', active: 'home' });

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const signInHref = computed(() =>
    signedIn.value ? dashboard().url : login().url,
);
const mobileMenuOpen = ref(false);
</script>

<template>
    <header
        class="border-line bg-surface/95 sticky top-0 z-40 border-b backdrop-blur-md"
        @keydown.esc="mobileMenuOpen = false"
    >
        <div
            class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-5 py-2.5 sm:gap-4 sm:px-8 lg:px-10"
        >
            <Link href="/" :aria-label="$t('GHASIDO home')" class="shrink-0">
                <img
                    src="/brand/ghasido-logo.png"
                    :alt="$t('GHASIDO — English for hotel staff')"
                    width="600"
                    height="180"
                    class="h-10 w-auto object-contain sm:h-13"
                />
            </Link>

            <nav
                class="text-ink-indigo hidden items-center gap-4 text-[12px] font-semibold lg:flex xl:gap-7"
                :aria-label="$t('Main navigation')"
            >
                <a
                    :href="`${anchorBase}#ai`"
                    class="hover:text-brand-600 focus-visible:ring-brand-600 rounded-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >{{ content.navigation.ai_practice }}</a
                >
                <a
                    :href="`${anchorBase}#about`"
                    class="hover:text-brand-600 focus-visible:ring-brand-600 rounded-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >{{ content.navigation.about }}</a
                >
                <a
                    :href="`${anchorBase}#roles`"
                    class="hover:text-brand-600 focus-visible:ring-brand-600 rounded-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >{{ content.navigation.roles }}</a
                >
                <a
                    :href="`${anchorBase}#platform`"
                    class="hover:text-brand-600 focus-visible:ring-brand-600 rounded-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >{{ content.navigation.platform }}</a
                >
                <a
                    :href="`${anchorBase}#pricing`"
                    class="hover:text-brand-600 focus-visible:ring-brand-600 rounded-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >{{ content.navigation.pricing }}</a
                >
                <Link
                    :href="contact()"
                    :class="
                        cn(
                            'hover:text-brand-600 focus-visible:ring-brand-600 rounded-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none',
                            active === 'contact' && 'text-brand-600',
                        )
                    "
                    :aria-current="active === 'contact' ? 'page' : undefined"
                    >{{ content.navigation.contact }}</Link
                >
            </nav>

            <div class="flex shrink-0 items-center gap-1 sm:gap-3">
                <LanguageToggle compact class="hidden sm:inline-flex" />
                <InstallAppButton variant="nav" class="hidden sm:inline-flex" />
                <Link
                    :href="signInHref"
                    class="text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600 hidden min-h-11 items-center rounded-md px-3 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none sm:inline-flex"
                >
                    {{
                        signedIn
                            ? content.navigation.open_dashboard
                            : content.navigation.login
                    }}
                </Link>
                <Button
                    as-child
                    class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 h-11 rounded-md px-2 text-[12px] font-semibold sm:px-5 sm:text-[13px]"
                >
                    <a :href="`${anchorBase}#pricing`">
                        {{ content.navigation.get_started }}
                        <ArrowRight class="size-4" />
                    </a>
                </Button>
                <button
                    type="button"
                    class="border-line text-brand-800 hover:bg-brand-50 focus-visible:ring-brand-600 inline-flex size-11 items-center justify-center rounded-md border transition-colors focus-visible:ring-2 focus-visible:outline-none lg:hidden"
                    :aria-label="$t('Toggle navigation')"
                    aria-controls="mobile-landing-navigation"
                    :aria-expanded="mobileMenuOpen"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>
        </div>

        <nav
            v-if="mobileMenuOpen"
            id="mobile-landing-navigation"
            class="border-line bg-surface shadow-hover absolute inset-x-0 top-full border-b px-5 py-4 lg:hidden"
            :aria-label="$t('Mobile navigation')"
        >
            <div class="mx-auto grid max-w-7xl gap-1">
                <a
                    v-for="item in [
                        {
                            href: anchorBase + '#ai',
                            label: content.navigation.ai_practice,
                        },
                        {
                            href: anchorBase + '#about',
                            label: content.navigation.about,
                        },
                        {
                            href: anchorBase + '#roles',
                            label: content.navigation.roles,
                        },
                        {
                            href: anchorBase + '#platform',
                            label: content.navigation.platform,
                        },
                        {
                            href: anchorBase + '#pricing',
                            label: content.navigation.pricing,
                        },
                    ]"
                    :key="item.href"
                    :href="item.href"
                    class="text-ink-indigo hover:bg-brand-50 focus-visible:ring-brand-600 flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                    @click="mobileMenuOpen = false"
                >
                    {{ item.label }}
                </a>
                <Link
                    :href="contact()"
                    class="text-ink-indigo hover:bg-brand-50 focus-visible:ring-brand-600 flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                    @click="mobileMenuOpen = false"
                >
                    {{ content.navigation.contact }}
                </Link>
                <Link
                    :href="signInHref"
                    class="text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600 mt-1 flex min-h-11 items-center rounded-md px-3 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none"
                >
                    {{
                        signedIn
                            ? content.navigation.open_dashboard
                            : content.navigation.login
                    }}
                </Link>
                <LanguageToggle class="justify-start sm:hidden" />
                <InstallAppButton
                    variant="menu"
                    @opened="mobileMenuOpen = false"
                />
            </div>
        </nav>
    </header>
</template>
