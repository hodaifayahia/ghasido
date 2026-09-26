<script setup lang="ts">
import { onMounted, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { ProgressCourse } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * One course's completion on My Progress (PROG-02, PROG-05): a title, the
 * "n of N lessons" line and an 8px progress bar that fills from zero over
 * 700ms on mount (desgin/10-design-system.md §10.4).
 */
type Props = {
    course: ProgressCourse;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const width = ref(0);

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        width.value = props.course.percent;

        return;
    }

    requestAnimationFrame(() => {
        width.value = props.course.percent;
    });
});
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-5',
                props.class,
            )
        "
        :aria-label="course.title"
    >
        <div class="flex items-baseline justify-between gap-3">
            <MeaningText
                as="h2"
                :text="course.title"
                class="font-heading text-ink-night truncate text-lg leading-6 font-semibold"
            />
            <span class="text-brand-700 font-heading text-lg font-bold">
                {{ course.percent }}%
            </span>
        </div>
        <div
            class="bg-tint-track rounded-pill h-2 overflow-hidden"
            role="progressbar"
            :aria-valuenow="course.percent"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`${course.title} progress`"
        >
            <div
                :class="
                    cn(
                        'ease-brand rounded-pill h-full transition-[width] duration-700 motion-reduce:transition-none',
                        course.percent >= 100 ? 'bg-success' : 'bg-brand-600',
                    )
                "
                :style="{ width: `${width}%` }"
            />
        </div>
        <p class="text-ink-slate text-sm">
            {{ course.lessonsCompleted }} of {{ course.lessonsTotal }} lessons
            completed
        </p>
    </section>
</template>
