<script setup lang="ts">
import PanelCard from '@/components/common/PanelCard.vue';
import type { DashboardAiPointSpend } from '@/types';

defineProps<{
    summary: DashboardAiPointSpend;
}>();

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

function formatUsd(amount: number): string {
    return usd.format(amount);
}

function formatPoints(points: number): string {
    return new Intl.NumberFormat('en-US').format(points);
}
</script>

<template>
    <PanelCard
        title="AI cost and points this month"
        title-id="dashboard-ai-point-spend-title"
        class="min-w-0"
        body-class="mt-2"
    >
        <div class="grid min-w-0 gap-2 md:grid-cols-2">
            <article
                class="border-line bg-brand-100/35 min-w-0 rounded-md border p-3.5"
            >
                <p
                    class="text-ink-slate text-xs font-semibold tracking-wide uppercase"
                >
                    Voice agent
                </p>
                <p
                    class="font-heading text-brand-800 mt-1 text-2xl leading-7 font-bold"
                >
                    {{ formatUsd(summary.voiceAgent.costUsd) }}
                </p>
                <p class="text-ink-slate mt-0.5 text-xs">
                    Estimated provider cost
                </p>
                <p class="text-ink-indigo mt-2 text-sm font-medium">
                    {{ formatPoints(summary.voiceAgent.points) }} points used
                </p>
                <p
                    v-if="!summary.voiceAgent.priceComplete"
                    class="text-sunset mt-1 text-xs"
                >
                    Some voice usage has no saved provider price.
                </p>
            </article>

            <article
                class="border-line bg-ai/10 min-w-0 rounded-md border p-3.5"
            >
                <p
                    class="text-ink-slate text-xs font-semibold tracking-wide uppercase"
                >
                    LLM
                </p>
                <p
                    class="font-heading text-brand-800 mt-1 text-2xl leading-7 font-bold"
                >
                    {{ formatUsd(summary.llm.costUsd) }}
                </p>
                <p class="text-ink-slate mt-0.5 text-xs">
                    Estimated provider cost
                </p>
                <p class="text-ink-indigo mt-2 text-sm font-medium">
                    {{ formatPoints(summary.llm.points) }} points used
                </p>
                <p
                    v-if="!summary.llm.priceComplete"
                    class="text-sunset mt-1 text-xs"
                >
                    Some LLM usage has no saved provider price.
                </p>
            </article>
        </div>
        <p class="text-ink-slate mt-2 text-xs leading-4">
            USD cost uses configured provider prices. Points show employee AI
            allowance usage.
        </p>
    </PanelCard>
</template>
