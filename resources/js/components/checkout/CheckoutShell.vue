<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import { home } from '@/routes';
import type { LandingPageContent } from '@/types';

/*
 * The public checkout's frame: a slim header with the logo and one way
 * back, the soft brand backdrop, and the landing footer. Shared by the
 * checkout and the "Payment submitted" page.
 */
type Props = {
    content: LandingPageContent;
    backHref: string;
    backLabel: string;
};

defineProps<Props>();
</script>

<template>
    <div class="bg-app text-ink flex min-h-svh flex-col overflow-x-clip">
        <header class="border-line bg-surface border-b">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-8 lg:px-10"
            >
                <Link
                    :href="home()"
                    :aria-label="$t('GHASIDO home')"
                    class="focus-visible:ring-brand-600/30 rounded-md focus-visible:ring-3 focus-visible:outline-none"
                >
                    <img
                        src="/brand/ghasido-logo.png"
                        alt="GHASIDO"
                        width="600"
                        height="180"
                        class="h-10 w-auto object-contain sm:h-13"
                    />
                </Link>
                <Link
                    :href="backHref"
                    class="text-ink-indigo hover:text-brand-600 focus-visible:ring-brand-600/30 inline-flex min-h-11 items-center gap-2 rounded-md px-2 text-[13px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none"
                >
                    <ArrowLeft class="size-4 shrink-0 rtl:rotate-180" />
                    {{ backLabel }}
                </Link>
            </div>
        </header>

        <main class="relative isolate flex-1 overflow-hidden">
            <div
                class="bg-brand-100/70 pointer-events-none absolute -end-24 -top-48 -z-10 size-[32rem] rounded-full blur-3xl"
                aria-hidden="true"
            />
            <div
                class="bg-aqua-tint/80 pointer-events-none absolute -start-40 top-1/2 -z-10 size-96 rounded-full blur-3xl"
                aria-hidden="true"
            />
            <slot />
        </main>

        <LandingFooter :content="content" anchor-base="/" />
    </div>
</template>
