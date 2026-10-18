<script setup lang="ts">
import { Clock, Hand, MessageCircle, Sprout } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { EssentialsIcon } from '@/types';

/*
 * The 44px chip of an English Essentials topic (client request
 * 2026-10-18): "Aa" for letters, "123" for numbers, a Lucide glyph for the
 * rest, in the brand tint like the design system's icon chips.
 */
type Props = {
    icon: EssentialsIcon;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const glyphs: Partial<Record<EssentialsIcon, Component>> = {
    time: Clock,
    greetings: Hand,
    clarification: MessageCircle,
    sprout: Sprout,
};

const marks: Partial<Record<EssentialsIcon, string>> = {
    letters: 'Aa',
    numbers: '123',
};
</script>

<template>
    <span
        :class="
            cn(
                'bg-brand-50 text-brand-700 grid size-11 shrink-0 place-items-center rounded-xl',
                props.class,
            )
        "
        aria-hidden="true"
    >
        <span
            v-if="marks[icon]"
            class="font-heading text-[15px] leading-none font-bold tracking-[-0.02em]"
        >
            {{ marks[icon] }}
        </span>
        <component :is="glyphs[icon] ?? Sprout" v-else class="size-[22px]" />
    </span>
</template>
