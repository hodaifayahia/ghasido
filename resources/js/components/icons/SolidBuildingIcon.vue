<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { useId } from 'vue';

// Solid building with window and door cut-outs (mockup sidebar "Hotels").
const maskId = `solid-building-${useId()}`;
const windows = [7.6, 13.4].flatMap((x) =>
    [5.2, 9.2, 13.2].map((y) => ({ x, y })),
);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="currentColor"
    >
        <mask :id="maskId">
            <rect width="24" height="24" fill="#fff" />
            <rect
                v-for="w in windows"
                :key="`${w.x}-${w.y}`"
                :x="w.x"
                :y="w.y"
                width="3"
                height="2.4"
                rx="0.5"
                fill="#000"
            />
            <rect
                x="10.2"
                y="17.2"
                width="3.6"
                height="5"
                rx="0.6"
                fill="#000"
            />
        </mask>
        <rect
            :mask="`url(#${maskId})`"
            x="4.5"
            y="2"
            width="15"
            height="20"
            rx="2"
        />
    </svg>
</template>
