<script setup lang="ts">
import type { Component } from 'vue';

/*
 * One section of Settings → Email: the card recipe (border + soft blue
 * shadow), a tinted icon chip, a title and a one-line description.
 */
type Props = {
    title: string;
    description?: string;
    icon: Component;
};

defineProps<Props>();
</script>

<template>
    <section
        class="border-line bg-surface shadow-card grid min-w-0 gap-4 rounded-lg border p-5"
    >
        <header class="flex min-w-0 items-start gap-3">
            <span
                class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
                aria-hidden="true"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div class="grid min-w-0 gap-0.5">
                <h3 class="font-heading text-brand-800 text-base font-semibold">
                    {{ title }}
                </h3>
                <p v-if="description" class="text-ink-muted text-sm">
                    {{ description }}
                </p>
            </div>
        </header>

        <slot />
    </section>
</template>
