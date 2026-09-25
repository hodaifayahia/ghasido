<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Toaster } from '@/components/ui/sonner';
import { useInitials } from '@/composables/useInitials';
import { dashboard, logout } from '@/routes/owner';

/*
 * The platform owner's console shell (spec 0007). The owner is not an app
 * user, so there is no app sidebar: the approved topbar's height, logo and
 * account typography, then the page on the app background. No client
 * mockup: built from the approved chrome's values (AGENTS.md §0.4).
 */
const page = usePage();
const owner = computed(() => page.props.owner ?? { name: '', email: '' });
const { getInitials } = useInitials();
</script>

<template>
    <div class="bg-app flex min-h-svh flex-col">
        <header
            class="h-topbar bg-surface sticky top-0 z-9 flex shrink-0 items-center gap-3 ps-3 pe-3 md:ps-6 md:pe-[22px]"
        >
            <Link
                :href="dashboard()"
                class="focus-visible:ring-brand-600/40 flex shrink-0 items-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
            >
                <AppLogo variant="mark" class="size-10 md:hidden" />
                <AppLogo class="hidden md:flex" />
            </Link>

            <span
                class="border-line text-label text-brand-700 ms-1 hidden border-s ps-4 sm:inline"
            >
                Owner console
            </span>

            <div class="ms-auto flex min-w-0 items-center gap-2 md:gap-4">
                <div class="flex min-w-0 items-center gap-[15px] p-1">
                    <Avatar class="size-[34px]">
                        <AvatarFallback
                            class="bg-brand-800/85 font-heading text-[15px] font-semibold text-white"
                        >
                            {{ getInitials(owner.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="hidden min-w-0 flex-col md:flex">
                        <span
                            class="font-heading text-ink-indigo truncate text-[13.5px] leading-5 font-semibold tracking-[-0.02em]"
                        >
                            {{ owner.name }}
                        </span>
                        <span
                            class="text-ink-slate mt-px truncate text-[12.5px] leading-[18px] tracking-[-0.01em]"
                        >
                            Platform owner
                        </span>
                    </span>
                </div>

                <Link
                    :href="logout()"
                    as="button"
                    data-test="owner-logout-button"
                    class="border-line bg-surface text-brand-700 shadow-card hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-10 min-w-11 items-center justify-center gap-2 rounded-md border px-3 text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                >
                    <LogOut class="size-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Sign out</span>
                    <span class="sr-only sm:hidden">Sign out</span>
                </Link>
            </div>
        </header>

        <main
            class="max-w-content mx-auto w-full min-w-0 flex-1 px-4 pt-5 pb-10 md:px-6"
        >
            <slot />
        </main>

        <Toaster />
    </div>
</template>
