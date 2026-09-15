<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { cn } from '@/lib/utils';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const accentCrop = {
    x: 1088,
    y: 0,
    width: 192,
    height: 126,
};
</script>

<template>
    <div
        :class="
            cn(
                'pointer-events-none relative hidden h-[118px] w-[212px] select-none md:block',
                props.class,
            )
        "
    >
        <LessonsMockupCrop
            :crop="accentCrop"
            src="/decor/reports-export-mockup.jpg"
            alt=""
            decorative
            class="absolute end-0 -top-[53px] w-[176px] bg-transparent opacity-95"
        />
    </div>
</template>
