<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, Check } from '@lucide/vue';
import type { Component } from 'vue';
import SidePhotoCard from '@/components/learning/SidePhotoCard.vue';
import TipCard from '@/components/learning/TipCard.vue';
import { cn } from '@/lib/utils';
import type { MediaRef } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * The shared chrome around a practice activity (photo_8..14): Back to
 * Practice, the tinted icon chip with the numbered title and subtitle, the
 * department and lesson, a side photo and Tip on the left, the activity body
 * in the slot, and the Previous / dots / Check bar. The body owns the
 * answers; this frame only raises check / prev / select.
 */
type Props = {
    backUrl: string;
    icon: Component;
    tone?: string;
    number?: number;
    label: string;
    subtitle: string;
    department: string;
    lessonNumber: number;
    lessonCount: number;
    sidePhoto: MediaRef | null;
    tip: string | null;
    tipClass?: string;
    total: number;
    current: number;
    answered: number[];
    canCheck: boolean;
    hasResult?: boolean;
    checkLabel?: string;
};

const props = withDefaults(defineProps<Props>(), {
    tone: 'brand',
    number: undefined,
    hasResult: false,
    checkLabel: undefined,
    tipClass: undefined,
});

const emit = defineEmits<{ check: []; prev: []; select: [index: number] }>();

const chipBg: Record<string, string> = {
    brand: 'bg-brand-600',
    success: 'bg-success',
    sunset: 'bg-sunset',
    ai: 'bg-ai',
    blossom: 'bg-blossom',
    gold: 'bg-gold',
    aqua: 'bg-aqua',
    warning: 'bg-warning',
    danger: 'bg-danger',
    azure: 'bg-azure',
};

const outline =
    'border-line bg-app-alt text-ink hover:bg-brand-50 focus-visible:ring-brand-600/30 inline-flex min-h-[52px] items-center gap-3 rounded-md border px-5 text-[15px] font-semibold transition focus-visible:ring-3 focus-visible:outline-none';
const previous =
    'bg-line text-ink hover:bg-line-strong/60 focus-visible:ring-brand-600/30 inline-flex h-14 w-full min-w-0 items-center justify-center gap-2 rounded-lg px-3 text-sm font-semibold transition focus-visible:ring-3 focus-visible:outline-none md:w-[221px] md:justify-start md:gap-3 md:px-6 md:text-[17px]';
</script>

<template>
    <div
        class="mt-1 flex flex-col gap-2 md:mx-[7px] md:-mt-[10px] md:-mb-[6px]"
    >
        <div
            class="grid gap-4 md:grid-cols-[316px_minmax(0,1fr)] md:gap-[18px]"
        >
            <Link :href="backUrl" :class="outline">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ $t('Back to Practice') }}
            </Link>

            <div
                class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex min-w-0 items-start gap-5">
                    <span
                        :class="
                            cn(
                                'grid size-16 shrink-0 place-items-center rounded-full text-white sm:-ms-[67px] sm:-mt-2',
                                chipBg[tone] ?? chipBg.brand,
                            )
                        "
                    >
                        <component
                            :is="icon"
                            class="size-8"
                            aria-hidden="true"
                        />
                    </span>
                    <div class="min-w-0">
                        <h1
                            class="font-heading text-ink-royal text-[28px] leading-9 font-bold tracking-[-0.02em]"
                        >
                            {{ number ? `${number}. ` : '' }}{{ label }}
                        </h1>
                        <MeaningText
                            as="p"
                            :text="subtitle"
                            class="text-ink-slate mt-0.5 text-[17px] leading-6"
                        />
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3 md:pt-2">
                    <span
                        class="bg-brand-50 text-brand-700 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold"
                    >
                        <BellRing class="size-4" aria-hidden="true" />
                        {{ department }}
                    </span>
                    <span class="text-ink-slate text-sm font-semibold">
                        {{
                            $t('Lesson :current / :total', {
                                current: lessonNumber,
                                total: lessonCount,
                            })
                        }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-[18px] md:grid-cols-[316px_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-2">
                <SidePhotoCard
                    :image="sidePhoto"
                    class="h-56 md:h-[289px] md:-translate-y-[3px]"
                />
                <slot name="side" />
                <TipCard
                    v-if="tip"
                    :title="$t('Tip')"
                    :text="tip"
                    :class="tipClass"
                />
            </div>

            <div class="min-w-0">
                <slot />
            </div>
        </div>

        <div
            class="mt-3 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 md:-mx-[5px] md:flex md:justify-between"
        >
            <button type="button" :class="previous" @click="emit('prev')">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ $t('Previous') }}
            </button>

            <div class="flex items-center gap-2">
                <button
                    v-for="i in total"
                    :key="i"
                    type="button"
                    :aria-label="$t('Item :number', { number: i })"
                    :aria-current="i - 1 === current"
                    class="focus-visible:ring-brand-600/40 size-2.5 rounded-full focus-visible:ring-3 focus-visible:outline-none"
                    :class="
                        i - 1 === current
                            ? 'bg-brand-600'
                            : answered.includes(i - 1)
                              ? 'bg-brand-300'
                              : 'bg-brand-100'
                    "
                    @click="emit('select', i - 1)"
                />
            </div>

            <button
                type="button"
                :disabled="!canCheck && !hasResult"
                :class="
                    cn(
                        'shadow-btn inline-flex h-14 w-full min-w-0 items-center justify-center gap-1 rounded-lg px-3 text-sm font-semibold text-white transition focus-visible:ring-3 focus-visible:ring-black/10 focus-visible:outline-none active:scale-[.97] md:w-[281px] md:gap-2 md:px-6 md:text-[17px]',
                        canCheck || hasResult
                            ? 'bg-brand-600 hover:bg-brand-700'
                            : 'bg-line-strong cursor-not-allowed',
                    )
                "
                @click="emit('check')"
            >
                <Check class="size-4" aria-hidden="true" />
                {{ checkLabel ?? $t('Check') }}
            </button>
        </div>
    </div>
</template>
