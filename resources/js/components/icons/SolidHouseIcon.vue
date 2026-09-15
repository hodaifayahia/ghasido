<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { useId } from 'vue';

// Solid house with a doorway cut out, as drawn in the mockup's sidebar. Lucide's
// House draws its door beneath the outline, so filling it hides the door.
const maskId = `solid-house-${useId()}`;
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
                x="9.6"
                y="13.2"
                width="4.8"
                height="8.8"
                rx="1.2"
                fill="#000"
            />
        </mask>
        <path
            :mask="`url(#${maskId})`"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linejoin="round"
            d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
        />
    </svg>
</template>
