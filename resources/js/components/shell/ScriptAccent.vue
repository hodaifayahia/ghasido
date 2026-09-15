<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <!-- The client's "Better Communication Brighter Careers" handwriting
         (desgin/assets/better-communication-original.png), with each line
         laid out as the approved mockup draws it. Decoration only: never
         announced, never hit-tested, hidden below md. Offsets place it at
         x 1124 / y 87 on the 1280px mockup, anchored to the header's end. -->
    <img
        src="/decor/better-communication-header.png"
        alt=""
        aria-hidden="true"
        width="408"
        height="253"
        draggable="false"
        :class="
            cn(
                'pointer-events-none relative -end-1 -top-[5px] hidden h-auto w-[136px] max-w-none opacity-75 select-none md:block',
                props.class,
            )
        "
    />
</template>
