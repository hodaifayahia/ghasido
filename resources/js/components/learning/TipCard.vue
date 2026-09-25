<script setup lang="ts">
import { Lightbulb } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The bulb tip box (spec 0003 H.2): brand-50 fill, 10px radius, a 24px
 * brand-600 bulb, optional "Tip" title in brand-700 semibold and the text
 * in 13px ink-slate with its line breaks kept (desginphotos/employ/
 * photo_2, bottom right: 350×83 at x 880 / y 622).
 */
type Props = {
    title?: string | null;
    text: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { title: null });
</script>

<template>
    <aside
        :class="
            cn('bg-brand-50 flex items-start gap-3 rounded-md p-4', props.class)
        "
    >
        <Lightbulb
            class="text-brand-600 mt-0.5 size-6 shrink-0 stroke-[2]"
            aria-hidden="true"
        />
        <div class="min-w-0">
            <p
                v-if="title"
                class="font-heading text-brand-700 text-sm leading-5 font-semibold"
            >
                {{ title }}
            </p>
            <p
                class="text-ink-slate text-[13px] leading-[21px] whitespace-pre-line"
            >
                {{ text }}
            </p>
        </div>
    </aside>
</template>
