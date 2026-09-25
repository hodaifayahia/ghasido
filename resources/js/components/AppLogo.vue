<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
// 2026-09-25: renamed Guesvia -> GHASIDO at the user's request. The palm mark
// and tagline are the client's artwork; only the wordmark image is new.
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    /** `full` = palm mark + wordmark + tagline; `mark` = palm mark only. */
    variant?: 'full' | 'mark';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'full',
});
</script>

<template>
    <img
        v-if="variant === 'mark'"
        src="/brand/ghasido-mark.png"
        alt="GHASIDO"
        width="144"
        height="144"
        draggable="false"
        :class="cn('size-9 shrink-0 object-contain', props.class)"
    />
    <!-- The lockup, split into mark / wordmark / tagline so each piece sits
         where the approved mockup places it (mark 58px, text 18.5px to its
         right). The all-caps GHASIDO wordmark is as wide as the tagline and
         shorter than the old one, so the text block is centred on the mark. -->
    <span
        v-else
        role="img"
        aria-label="GHASIDO — English for Hotel Staff"
        :class="cn('flex shrink-0 items-center', props.class)"
    >
        <img
            src="/brand/ghasido-mark.png"
            alt=""
            width="144"
            height="144"
            draggable="false"
            class="size-[58px] shrink-0"
        />
        <span class="ms-[18.5px] flex flex-col items-start">
            <img
                src="/brand/ghasido-wordmark.png"
                alt=""
                width="432"
                height="69"
                draggable="false"
                class="h-auto w-[144px] max-w-none"
            />
            <img
                src="/brand/ghasido-tagline.png"
                alt=""
                width="429"
                height="47"
                draggable="false"
                class="mt-2 h-auto w-[144px] max-w-none"
            />
        </span>
    </span>
</template>
