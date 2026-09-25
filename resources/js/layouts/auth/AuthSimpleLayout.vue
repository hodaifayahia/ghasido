<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    'Department-based lessons',
    'AI guest conversation practice',
    'Progress and assessment reports',
] as const;
</script>

<template>
    <div class="bg-grad-page min-h-svh px-4 py-3 sm:px-6 sm:py-4 lg:px-8">
        <div
            class="mx-auto grid min-h-[calc(100svh-1.5rem)] w-full max-w-[1180px] items-center gap-4 lg:min-h-[calc(100svh-2rem)] lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] xl:gap-6"
        >
            <section
                class="border-line bg-surface shadow-pop relative hidden min-w-0 flex-col justify-between overflow-hidden rounded-xl border p-6 lg:flex xl:p-8"
            >
                <img
                    src="/decor/better-communication-header.png"
                    alt=""
                    class="pointer-events-none absolute end-5 top-5 w-34 opacity-75 select-none"
                />

                <Link :href="home()" class="relative z-10 inline-flex">
                    <AppLogo />
                </Link>

                <div class="relative mt-8">
                    <p class="text-label text-brand-700">
                        English for hotel teams
                    </p>
                    <h2
                        class="font-heading text-ink-royal mt-3 max-w-[14ch] text-3xl leading-tight font-bold"
                    >
                        Build confidence for every guest interaction.
                    </h2>
                    <p class="text-body text-ink-slate mt-4 max-w-[54ch]">
                        Focused lessons, realistic practice and clear progress
                        for every hotel department.
                    </p>

                    <ul class="mt-6 grid gap-2.5">
                        <li
                            v-for="highlight in highlights"
                            :key="highlight"
                            class="border-line bg-app-alt text-ink-indigo rounded-lg border px-4 py-2.5 text-sm font-medium"
                        >
                            {{ highlight }}
                        </li>
                    </ul>
                </div>

                <img
                    src="/decor/palm-island-tagline.png"
                    alt=""
                    class="pointer-events-none mt-7 w-32 opacity-70 select-none"
                />
            </section>

            <section
                class="border-line bg-surface shadow-pop relative mx-auto w-full max-w-[560px] min-w-0 overflow-hidden rounded-xl border p-4 sm:p-5 lg:max-w-none lg:p-6 xl:p-7"
            >
                <div class="bg-grad-brand absolute inset-x-0 top-0 h-1" />

                <Link
                    :href="home()"
                    class="mb-4 inline-flex max-w-full lg:hidden"
                >
                    <AppLogo class="origin-left scale-[0.82]" />
                </Link>

                <div class="relative">
                    <p class="text-label text-brand-700">Secure access</p>
                    <h1
                        v-if="title"
                        class="font-heading text-ink-royal mt-2 text-2xl leading-tight font-bold sm:text-[28px]"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-body text-ink-slate mt-2 max-w-md"
                    >
                        {{ description }}
                    </p>
                </div>

                <div class="relative mt-4 sm:mt-5">
                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>
