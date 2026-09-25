<script setup lang="ts">
// Shared “Better Communication, Brighter Careers” accent requested for the
// dashboard page headers, using the client's transparent brand artwork.
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <!-- The supplied “Better Communication, Brighter Careers” lettering is
         the shared dashboard page-header accent. -->
    <img
        src="/decor/better-communication.png"
        alt=""
        aria-hidden="true"
        width="612"
        height="272"
        draggable="false"
        :class="
            cn(
                'pointer-events-none relative -end-1 -top-[5px] hidden h-auto w-[136px] max-w-none opacity-75 select-none md:block',
                props.class,
            )
        "
    />
</template>
