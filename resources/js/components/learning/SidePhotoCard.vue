<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { MediaRef } from '@/types';

/*
 * A photo frame with an optional caption pill (spec 0003 H.2; photo_4 and
 * photo_5 draw the caption as a white rounded pill over the bottom-left of
 * the photo). Always the client's image from the seed manifest / CMS: with
 * no image the frame shows the app tint, never a placeholder graphic.
 */
type Props = {
    image: MediaRef | null;
    caption?: string | null;
    /** Rendered when the frame is decorative next to the same text. */
    alt?: string | null;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    caption: null,
    alt: undefined,
});
</script>

<template>
    <figure
        :class="
            cn(
                'bg-app-alt relative min-h-40 overflow-hidden rounded-md',
                props.class,
            )
        "
    >
        <img
            v-if="image"
            :src="image.url"
            :alt="alt ?? image.alt ?? ''"
            loading="lazy"
            decoding="async"
            draggable="false"
            class="size-full object-cover"
        />
        <figcaption
            v-if="caption"
            class="rounded-pill bg-surface/95 text-ink shadow-card absolute start-4 bottom-4 max-w-[calc(100%_-_2rem)] px-4 py-2 text-[15px] leading-5 font-medium"
        >
            {{ caption }}
        </figcaption>
    </figure>
</template>
