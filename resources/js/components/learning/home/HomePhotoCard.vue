<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { MediaRef } from '@/types';

/*
 * The receptionist photo (spec 0003 Part E). The seed asset `home-receptionist`
 * is cropped from photo_20 with the handwritten quote already in the picture,
 * so the quote is not drawn again; it stays available to assistive tech.
 * Measured on the mockup,
 * measured on desginphotos/employ/photo_20 at 1280×853: the frame spans
 * x 701–1268 / y 76–444 (567×368) with soft corners; the white Caveat
 * quote sits 19px in and 104px down, three lines about 140px wide,
 * tilted about 8°, with a hand-drawn underline. The image is the client's
 * seed asset `home-receptionist` resolved by the server; no placeholder.
 */
type Props = {
    photo: MediaRef | null;
    quote?: string | null;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { quote: null });
</script>

<template>
    <figure
        :class="
            cn(
                'bg-app-alt relative aspect-[567/368] w-full overflow-hidden rounded-lg',
                props.class,
            )
        "
    >
        <img
            v-if="photo"
            :src="photo.url"
            :alt="photo.alt ?? ''"
            decoding="async"
            draggable="false"
            class="size-full object-cover"
        />
        <figcaption v-if="quote" class="sr-only">{{ quote }}</figcaption>
    </figure>
</template>
