<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <ScriptAccent :class="props.class" />
</template>
