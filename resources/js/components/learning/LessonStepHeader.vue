<script setup lang="ts">
import { ConciergeBell } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The heading row of every lesson step (spec 0003 H.2), measured on
 * desginphotos/employ/photo_1 at 1280×853: "1. Situation (Intro)" 28px
 * Poppins Bold, cap top y 209, at x 34; on the right the department chip
 * (166×44 at x 938 / y 202: 22px bell at x 958, "Reception" 18px at x 1001)
 * and "Lesson 1 / 8" (18px, x 1135–1234). photo_7 adds an 18px subtitle
 * under the title.
 */
type Props = {
    /** The step number printed before the heading ("1. Situation (Intro)"). */
    number?: number | null;
    heading: string;
    subtitle?: string | null;
    department: string;
    lessonNumber: number;
    lessonCount: number;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <header
        :class="
            cn(
                'flex flex-wrap items-start justify-between gap-x-6 gap-y-3',
                props.class,
            )
        "
    >
        <div class="min-w-0">
            <h1
                class="font-heading text-ink-night pt-1 text-[28px] leading-10 font-bold tracking-[-0.02em]"
            >
                <template v-if="number">{{ number }}. </template>{{ heading }}
            </h1>
            <p v-if="subtitle" class="text-ink-graphite mt-1 text-lg leading-7">
                {{ subtitle }}
            </p>
        </div>

        <!-- The 31px space is a gap, not a margin, so on a very narrow
             screen (the admin's phone preview frame) "Lesson 1 / 8" wraps
             under the chip instead of running off the edge; unchanged on
             one line. -->
        <div
            class="flex min-h-11 max-w-full shrink-0 flex-wrap items-center gap-x-[31px] gap-y-2 md:pe-[14px]"
        >
            <span
                class="bg-tint-grid text-ink flex h-11 items-center rounded-md ps-5 pe-[22px] text-[17px] leading-none font-semibold"
            >
                <ConciergeBell
                    class="me-[21px] size-[22px] shrink-0 stroke-[2]"
                    aria-hidden="true"
                />
                {{ department }}
            </span>
            <span class="text-ink text-lg leading-none font-medium">
                Lesson {{ lessonNumber }} / {{ lessonCount }}
            </span>
        </div>
    </header>
</template>
