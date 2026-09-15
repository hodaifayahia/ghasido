<script setup lang="ts">
import { computed } from 'vue';
import type { CSSProperties, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { LessonMockupCrop } from '@/types';

type Props = {
    crop: LessonMockupCrop;
    alt: string;
    src?: string;
    decorative?: boolean;
    class?: HTMLAttributes['class'];
    imageClass?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    decorative: false,
    src: '/decor/lessons-content-mockup.jpg',
});

const wrapperStyle = computed<CSSProperties>(() => ({
    aspectRatio: `${props.crop.width} / ${props.crop.height}`,
}));

const imageStyle = computed<CSSProperties>(() => ({
    width: `${(1280 / props.crop.width) * 100}%`,
    height: `${(853 / props.crop.height) * 100}%`,
    left: `-${(props.crop.x / props.crop.width) * 100}%`,
    top: `-${(props.crop.y / props.crop.height) * 100}%`,
}));
</script>

<template>
    <div
        :class="cn('bg-brand-50 relative overflow-hidden', props.class)"
        :style="wrapperStyle"
    >
        <img
            :src="props.src"
            :alt="props.decorative ? '' : props.alt"
            :aria-hidden="props.decorative || undefined"
            width="1280"
            height="853"
            draggable="false"
            :class="
                cn(
                    'pointer-events-none absolute max-w-none select-none',
                    props.imageClass,
                )
            "
            :style="imageStyle"
        />
    </div>
</template>
