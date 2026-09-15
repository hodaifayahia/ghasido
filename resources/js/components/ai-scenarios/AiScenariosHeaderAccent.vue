<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { cn } from '@/lib/utils';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const photoCrop = {
    x: 1104,
    y: 0,
    width: 176,
    height: 154,
};

const scriptCrop = {
    x: 941,
    y: 71,
    width: 191,
    height: 109,
};
</script>

<template>
    <div
        :class="
            cn(
                'pointer-events-none relative hidden h-[118px] w-[226px] select-none md:block',
                props.class,
            )
        "
    >
        <LessonsMockupCrop
            :crop="photoCrop"
            src="/decor/ai-scenarios-mockup.jpg"
            alt=""
            decorative
            class="absolute end-0 -top-[54px] w-[162px] opacity-95"
        />
        <LessonsMockupCrop
            :crop="scriptCrop"
            src="/decor/ai-scenarios-mockup.jpg"
            alt=""
            decorative
            class="absolute end-[51px] top-[2px] w-[154px] bg-transparent"
        />
    </div>
</template>
