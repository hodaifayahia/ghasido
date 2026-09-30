<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The closing message of a finished test (`motivational_message`). The text
 * comes translated from the server and depends on completion, never on the
 * score, so it also shows when results are hidden (TEST-04).
 */
type Props = {
    message: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <div
        :class="
            cn(
                'bg-brand-50 text-ink flex w-full items-start gap-3 rounded-lg p-4 text-start',
                props.class,
            )
        "
        data-test="test-motivation"
    >
        <span
            class="bg-surface text-brand-600 grid size-9 shrink-0 place-items-center rounded-xl"
        >
            <Sparkles class="size-5" aria-hidden="true" />
        </span>
        <p class="text-[14.5px] leading-relaxed" dir="auto">{{ message }}</p>
    </div>
</template>
