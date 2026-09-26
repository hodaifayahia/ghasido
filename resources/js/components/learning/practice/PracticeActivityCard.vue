<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Headphones,
    Image as ImageIcon,
    Images,
    Keyboard,
    Link as LinkIcon,
    ListChecks,
    ListOrdered,
    MessageCircle,
    Mic,
    PenLine,
    Video,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';
import type { CourseTone, PracticeCard } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * One practice-hub card (photo_7): a tinted card in the activity's tone with
 * a numbered title, the description, a small preview of the first item and a
 * "Start" button. The Start fill is the one sanctioned non-brand button fill
 * (the mockup draws it that way); done cards show a check.
 */
type Props = {
    card: PracticeCard;
    index: number;
};

const props = defineProps<Props>();

const icons: Record<string, Component> = {
    Headphones,
    Image: ImageIcon,
    MessageCircle,
    Link: LinkIcon,
    Video,
    Keyboard,
    ListOrdered,
    Images,
    ListChecks,
    Mic,
    PenLine,
};

const icon = computed(() => icons[props.card.icon] ?? ListChecks);

type ToneStyle = { card: string; chip: string; title: string; start: string };

const tones: Record<CourseTone, ToneStyle> = {
    brand: {
        card: 'bg-brand-50',
        chip: 'bg-brand-100 text-brand-600',
        title: 'text-brand-700',
        start: 'bg-brand-600 hover:bg-brand-700',
    },
    success: {
        card: 'bg-success-tint',
        chip: 'bg-success/15 text-success',
        title: 'text-success-text',
        start: 'bg-success hover:brightness-95',
    },
    sunset: {
        card: 'bg-warning-tint',
        chip: 'bg-sunset/15 text-sunset',
        title: 'text-sunset',
        start: 'bg-sunset hover:brightness-95',
    },
    ai: {
        card: 'bg-ai-tint',
        chip: 'bg-ai/15 text-ai',
        title: 'text-ai',
        start: 'bg-ai hover:brightness-95',
    },
    blossom: {
        card: 'bg-blossom-tint',
        chip: 'bg-blossom/15 text-blossom',
        title: 'text-blossom',
        start: 'bg-blossom hover:brightness-95',
    },
    gold: {
        card: 'bg-gold-tint',
        chip: 'bg-gold/20 text-gold',
        title: 'text-warning-text',
        start: 'bg-gold hover:brightness-95',
    },
    aqua: {
        card: 'bg-aqua-tint',
        chip: 'bg-aqua/15 text-aqua',
        title: 'text-aqua',
        start: 'bg-aqua hover:brightness-95',
    },
    warning: {
        card: 'bg-warning-tint',
        chip: 'bg-warning/15 text-warning',
        title: 'text-warning-text',
        start: 'bg-warning hover:brightness-95',
    },
    danger: {
        card: 'bg-danger-tint',
        chip: 'bg-danger/15 text-danger',
        title: 'text-danger-text',
        start: 'bg-danger hover:brightness-95',
    },
    azure: {
        card: 'bg-azure-tint',
        chip: 'bg-azure/15 text-azure',
        title: 'text-azure',
        start: 'bg-azure hover:brightness-95',
    },
};

const style = computed(() => tones[props.card.tone]);
</script>

<template>
    <div :class="cn('relative flex flex-col rounded-xl p-5', style.card)">
        <div
            v-if="card.done"
            class="bg-success absolute end-4 top-4 grid size-6 place-items-center rounded-full text-white"
        >
            <Check class="size-4" aria-hidden="true" />
        </div>

        <div class="flex items-center gap-3">
            <span
                :class="
                    cn(
                        'grid size-[52px] shrink-0 place-items-center rounded-2xl',
                        style.chip,
                    )
                "
            >
                <component :is="icon" class="size-6" aria-hidden="true" />
            </span>
            <h3 :class="cn('font-heading text-lg font-bold', style.title)">
                {{ index + 1 }}. {{ card.label }}
            </h3>
        </div>

        <MeaningText
            as="p"
            :text="card.description"
            class="text-ink mt-2 text-sm leading-6"
        />

        <div class="my-3 flex min-h-16 items-center">
            <div
                v-if="card.preview.images.length > 0"
                class="flex flex-wrap gap-2"
            >
                <img
                    v-for="(url, i) in card.preview.images"
                    :key="i"
                    :src="url"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-14 rounded-md object-cover"
                />
            </div>
            <p
                v-else-if="card.preview.sentence"
                class="bg-surface/70 text-ink rounded-md px-3 py-2 text-sm"
            >
                {{ card.preview.sentence }}
            </p>
        </div>

        <Link
            :href="card.url"
            :class="
                cn(
                    'shadow-btn font-heading mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold text-white transition focus-visible:ring-3 focus-visible:ring-black/10 focus-visible:outline-none active:scale-[.97]',
                    style.start,
                )
            "
        >
            Start
            <ArrowRight class="size-4" aria-hidden="true" />
        </Link>
    </div>
</template>
