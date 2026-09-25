<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StepTracker from '@/components/learning/StepTracker.vue';
import BottomNav from '@/components/shell/BottomNav.vue';
import LessonFooter from '@/components/shell/LessonFooter.vue';
import LessonTopNav from '@/components/shell/LessonTopNav.vue';
import { Toaster } from '@/components/ui/sonner';
import type { LessonStepNav } from '@/types';

/*
 * The lesson runner shell (spec 0003 H.1; desginphotos/employ/photo_1 … 19):
 * a white page (the mockups' page background is white, not the app tint)
 * with the 72px top bar, the 109px step-tracker band, the content between
 * x 32 and x 1248 at 1280px, and the footer line. Pages carry `steps`; the
 * tracker reads it from the page props so every step and activity page
 * shows the same band without repeating it.
 */
type Props = {
    /** Draw the palm sketch left of the footer's right-hand line. */
    palm?: boolean;
};

defineProps<Props>();

const page = usePage();
const steps = computed(
    (): LessonStepNav[] =>
        (page.props.steps as LessonStepNav[] | undefined) ?? [],
);
</script>

<template>
    <div class="bg-surface flex min-h-svh flex-col">
        <LessonTopNav />
        <StepTracker v-if="steps.length > 0" :steps="steps" />

        <main
            class="pb-bottomnav mx-auto flex w-full max-w-[1280px] flex-1 flex-col px-4 pt-4 md:px-8 md:pt-[21px] md:pb-0"
        >
            <slot />
        </main>

        <LessonFooter :palm="palm" class="hidden md:flex" />
        <BottomNav />
        <Toaster />
    </div>
</template>
