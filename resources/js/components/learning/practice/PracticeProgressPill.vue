<script setup lang="ts">
import { Trophy } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import ProgressRing from '@/components/learning/ProgressRing.vue';
import { cn } from '@/lib/utils';

/*
 * The practice progress pill (photo_7): a ring with "n / total", the
 * "activities completed" line, an encouraging motto and a gold trophy chip.
 */
type Props = {
    completed: number;
    total: number;
    motto: string | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const percent = computed(() =>
    props.total > 0 ? Math.round((props.completed / props.total) * 100) : 0,
);
</script>

<template>
    <div
        :class="
            cn(
                'border-line bg-surface shadow-card flex items-center gap-4 rounded-lg border p-4',
                props.class,
            )
        "
    >
        <div class="relative grid shrink-0 place-items-center">
            <ProgressRing :value="percent" :size="68" :stroke="8" />
            <span
                class="text-ink-royal font-heading absolute text-sm font-bold"
            >
                {{ completed }}/{{ total }}
            </span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-ink font-semibold">
                {{ $t('activities completed') }}
            </p>
            <p v-if="motto" class="text-ink-slate mt-0.5 text-sm leading-5">
                {{ motto }}
            </p>
        </div>
        <span
            class="bg-gold-tint text-gold grid size-11 shrink-0 place-items-center rounded-xl"
        >
            <Trophy class="size-6" aria-hidden="true" />
        </span>
    </div>
</template>
