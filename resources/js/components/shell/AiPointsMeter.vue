<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Frown } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const balance = computed(() => page.props.aiPointBalance);
const depleted = computed(() => (balance.value?.remaining ?? 0) <= 0);
const guidance = computed(() =>
    balance.value?.role === 'manager'
        ? 'No AI points remain for your hotel. Ask your platform admin to add points after payment.'
        : 'No AI points remain. Ask your hotel manager to add points.',
);

function compact(points: number): string {
    return new Intl.NumberFormat('en-US', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(points);
}

const progressColor = computed(() => {
    if (depleted.value) return 'bg-danger';
    if ((balance.value?.percent ?? 100) <= 20) return 'bg-sunset';

    return 'bg-brand-600';
});
</script>

<template>
    <div
        v-if="balance"
        class="me-1 w-[100px] min-w-0 sm:me-3 sm:w-36"
        :title="
            depleted
                ? guidance
                : `${balance.remaining.toLocaleString()} of ${balance.total.toLocaleString()} AI points left this month.`
        "
    >
        <div class="flex min-w-0 items-center justify-between gap-1">
            <span
                class="text-ink-slate truncate text-[9px] leading-3 font-semibold sm:text-[10px]"
            >
                {{ balance.role === 'manager' ? 'Hotel AI' : 'AI points' }}
            </span>
            <span
                v-if="!depleted"
                class="text-ink-indigo shrink-0 text-[9px] leading-3 font-semibold tabular-nums sm:text-[10px]"
            >
                {{ compact(balance.remaining) }} left
            </span>
            <span
                v-else
                class="text-danger-text flex min-w-0 shrink-0 items-center gap-0.5 text-[9px] leading-3 font-semibold sm:text-[10px]"
                :aria-label="guidance"
            >
                <Frown class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate"
                    >Ask
                    {{ balance.role === 'manager' ? 'admin' : 'manager' }}</span
                >
            </span>
        </div>
        <div
            class="bg-brand-100 mt-1 h-1.5 overflow-hidden rounded-full sm:h-2"
            role="progressbar"
            aria-label="AI points remaining this month"
            :aria-valuemin="0"
            :aria-valuemax="Math.max(balance.total, 1)"
            :aria-valuenow="balance.remaining"
            :aria-valuetext="`${balance.remaining.toLocaleString()} of ${balance.total.toLocaleString()} points remain this month`"
        >
            <div
                class="h-full rounded-full transition-[width] duration-300 motion-reduce:transition-none"
                :class="progressColor"
                :style="{ width: `${balance.percent}%` }"
            />
        </div>
    </div>
</template>
