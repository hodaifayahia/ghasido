<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { useId } from 'vue';

// Solid envelope with the flap drawn as a cut-out (mockup sidebar "Messages").
const maskId = `solid-mail-${useId()}`;
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
            <path
                d="m2.5 6.5 9.5 6.5 9.5-6.5"
                fill="none"
                stroke="#000"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </mask>
        <rect
            :mask="`url(#${maskId})`"
            x="1.5"
            y="4"
            width="21"
            height="16"
            rx="2.2"
        />
    </svg>
</template>
