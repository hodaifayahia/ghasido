<script setup lang="ts">
import PanelCard from '@/components/common/PanelCard.vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { DashboardAiPointSpend, DashboardAiSpendTile } from '@/types';

const props = defineProps<{
    summary: DashboardAiPointSpend;
}>();

/*
 * One tile per kind of metered usage, so every AI call the platform pays
 * for shows up here: the live voice agent, text (LLM), speech (lesson
 * audio, transcription, pronunciation) and images (API-03, AIL-04).
 */
type TileKey = keyof DashboardAiPointSpend;

const tiles: {
    key: TileKey;
    label: string;
    missingPrice: string;
    tone: string;
}[] = [
    {
        key: 'voiceAgent',
        label: tk('Voice agent'),
        missingPrice: tk('Some voice usage has no saved provider price.'),
        tone: 'bg-brand-100/35',
    },
    {
        key: 'llm',
        label: tk('LLM'),
        missingPrice: tk('Some LLM usage has no saved provider price.'),
        tone: 'bg-ai/10',
    },
    {
        key: 'speech',
        label: tk('Speech & listening'),
        missingPrice: tk('Some speech usage has no saved provider price.'),
        tone: 'bg-aqua-tint',
    },
    {
        key: 'images',
        label: tk('Images'),
        missingPrice: tk('Some image usage has no saved provider price.'),
        tone: 'bg-gold-tint',
    },
];

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

function tile(key: TileKey): DashboardAiSpendTile {
    return props.summary[key];
}
</script>

<template>
    <PanelCard
        :title="$t('AI cost and points this month')"
        title-id="dashboard-ai-point-spend-title"
        class="min-w-0"
        body-class="mt-2"
    >
        <div class="grid min-w-0 gap-2 md:grid-cols-2">
            <article
                v-for="item in tiles"
                :key="item.key"
                :class="
                    cn('border-line min-w-0 rounded-md border p-3.5', item.tone)
                "
                :data-test="`ai-spend-${item.key}`"
            >
                <p
                    class="text-ink-slate text-xs font-semibold tracking-wide uppercase"
                >
                    {{ $t(item.label) }}
                </p>
                <p
                    class="font-heading text-brand-800 mt-1 text-2xl leading-7 font-bold"
                >
                    {{ formatUsd(tile(item.key).costUsd) }}
                </p>
                <p class="text-ink-slate mt-0.5 text-xs">
                    {{ $t('Estimated provider cost') }}
                </p>
                <p class="text-ink-indigo mt-2 text-sm font-medium">
                    {{
                        $t(':points points used', {
                            points: formatPoints(tile(item.key).points),
                        })
                    }}
                </p>
                <p
                    v-if="!tile(item.key).priceComplete"
                    class="text-sunset mt-1 text-xs"
                >
                    {{ $t(item.missingPrice) }}
                </p>
            </article>
        </div>
        <p class="text-ink-slate mt-2 text-xs leading-4">
            {{
                $t(
                    'USD cost uses configured provider prices. Points show employee AI allowance usage.',
                )
            }}
        </p>
    </PanelCard>
</template>
