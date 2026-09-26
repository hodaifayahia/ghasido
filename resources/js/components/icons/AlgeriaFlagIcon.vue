<script setup lang="ts">
import { useId } from 'vue';

/*
 * The Algeria flag as a round 24px badge, for the "Algeria (DZD)" pricing
 * switch in the client's pricing mockup (2026-09-26). Colours are the
 * flag-dz tokens in app.css; the crescent is cut out with a mask so both
 * halves of the flag show through it.
 */
const clipId = `dz-flag-clip-${useId()}`;
const maskId = `dz-flag-crescent-${useId()}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        aria-hidden="true"
    >
        <clipPath :id="clipId">
            <circle cx="12" cy="12" r="12" />
        </clipPath>
        <mask :id="maskId">
            <rect width="24" height="24" fill="#fff" />
            <circle cx="13.9" cy="12" r="4.4" fill="#000" />
        </mask>
        <g :clip-path="`url(#${clipId})`">
            <rect width="12" height="24" class="fill-flag-dz-green" />
            <rect x="12" width="12" height="24" class="fill-surface" />
            <circle
                cx="12.4"
                cy="12"
                r="5.4"
                class="fill-flag-dz-red"
                :mask="`url(#${maskId})`"
            />
            <path
                d="m15.4 9.7.55 1.7h1.8l-1.45 1.05.55 1.7-1.45-1.05-1.45 1.05.55-1.7-1.45-1.05h1.8z"
                class="fill-flag-dz-red"
            />
        </g>
    </svg>
</template>
