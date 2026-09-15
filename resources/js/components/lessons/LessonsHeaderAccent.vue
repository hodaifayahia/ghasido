<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { cn } from '@/lib/utils';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const headerPhotoCrop = {
    x: 1148,
    y: 0,
    width: 132,
    height: 128,
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
            :crop="headerPhotoCrop"
            alt=""
            decorative
            class="absolute end-0 -top-[52px] w-[124px] opacity-95"
        />
        <ScriptAccent class="absolute end-[18px] top-[7px]" />
    </div>
</template>
