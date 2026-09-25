<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

/*
 * The empty state of a learner list (AGENTS.md §4 "every list ships
 * skeleton, empty, error"): an icon chip, one sentence and one action.
 */
type Props = {
    icon: Component;
    text: string;
    action?: { label: string; href: NavItem['href'] } | null;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { action: null });
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col items-center gap-4 rounded-lg border px-6 py-12 text-center',
                props.class,
            )
        "
    >
        <span
            class="bg-brand-50 text-brand-600 grid size-14 shrink-0 place-items-center rounded-xl"
        >
            <component :is="icon" class="size-7" aria-hidden="true" />
        </span>
        <p class="text-ink-slate max-w-md text-base leading-7">{{ text }}</p>
        <Link
            v-if="action"
            :href="action.href"
            class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 items-center rounded-md px-5 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none"
        >
            {{ action.label }}
        </Link>
    </section>
</template>
