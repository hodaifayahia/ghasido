<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { AudioLines, Bot, Coins, Wallet } from '@lucide/vue';
import { computed, watch } from 'vue';
import StatCard from '@/components/common/StatCard.vue';
import type { StatTone } from '@/components/common/StatCard.vue';
import OwnerAccountCard from '@/components/owner/OwnerAccountCard.vue';
import OwnerPriceTable from '@/components/owner/OwnerPriceTable.vue';
import { formatCount, formatUsd } from '@/components/owner/format';
import PageHeader from '@/components/shell/PageHeader.vue';
import type { ApiAccountCard, OwnerConsolePayload } from '@/types';

/*
 * The owner console (spec 0007): the paid API accounts GHASIDO runs on,
 * their credit, keys and prices. Pages compose; the cards style.
 */
type Props = OwnerConsolePayload;

const props = defineProps<Props>();

const qwen = computed(() =>
    props.accounts.find((card) => card.account === 'qwen'),
);
const deepgram = computed(() =>
    props.accounts.find((card) => card.account === 'deepgram'),
);

function creditTone(
    card: ApiAccountCard | undefined,
    tone: StatTone,
): StatTone {
    return card && ['low', 'exhausted', 'paused'].includes(card.state)
        ? 'warning'
        : tone;
}

function creditValue(card: ApiAccountCard | undefined): number {
    if (!card) {
        return 0;
    }

    return Math.round(
        card.limitedByUsd
            ? Math.max(0, card.remaining.usd ?? 0)
            : card.spent.usd,
    );
}

function creditDetail(card: ApiAccountCard | undefined): string {
    if (!card) {
        return '';
    }

    if (card.state === 'paused') {
        return 'Paused';
    }

    return card.limitedByUsd
        ? `${formatUsd(Math.max(0, card.remaining.usd ?? 0))} of ${formatUsd(card.credit.usd)}`
        : `No limit set · ${formatUsd(card.spent.usd)} spent`;
}

const unpriced = computed(() =>
    props.accounts.flatMap((card) => card.unpricedModels),
);

const totalSpent = computed(() =>
    props.accounts.reduce((sum, card) => sum + card.spent.usd, 0),
);

// Poll while a connection test is queued or running (PERF-04).
const checking = computed(() =>
    props.accounts.some((card) =>
        card.checks.some(
            (item) =>
                item.state?.status === 'pending' ||
                item.state?.status === 'running',
        ),
    ),
);

const { start, stop } = usePoll(
    2000,
    { only: ['accounts'] },
    { autoStart: false },
);

watch(
    checking,
    (busy) => {
        if (busy) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Owner console" />

    <div class="flex min-w-0 flex-col gap-5">
        <PageHeader
            title="API accounts"
            description="Keys, credit and prices for the paid AI services GHASIDO runs on."
        />

        <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :value="creditValue(qwen)"
                :label="
                    qwen?.limitedByUsd
                        ? 'Qwen credit left ($)'
                        : 'Qwen spent ($)'
                "
                :detail="creditDetail(qwen)"
                :tone="creditTone(qwen, 'ai')"
            >
                <template #icon
                    ><Bot class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="
                    Math.round(
                        (qwen?.limitedByTokens
                            ? Math.max(0, qwen.remaining.tokens ?? 0)
                            : (qwen?.spent.tokens ?? 0)) / 1000,
                    )
                "
                unit="k"
                :label="
                    qwen?.limitedByTokens
                        ? 'Qwen tokens left'
                        : 'Qwen tokens used'
                "
                :detail="
                    qwen?.limitedByTokens
                        ? `${formatCount(Math.max(0, qwen.remaining.tokens ?? 0))} of ${formatCount(qwen.credit.tokens)}`
                        : 'No token limit set'
                "
                :tone="creditTone(qwen, 'brand')"
            >
                <template #icon
                    ><Coins class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="creditValue(deepgram)"
                :label="
                    deepgram?.limitedByUsd
                        ? 'Deepgram credit left ($)'
                        : 'Deepgram spent ($)'
                "
                :detail="creditDetail(deepgram)"
                :tone="creditTone(deepgram, 'azure')"
            >
                <template #icon
                    ><AudioLines class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="Math.round(totalSpent)"
                label="Spent ($)"
                :detail="`${formatUsd(totalSpent)} across both accounts`"
                tone="success"
            >
                <template #icon
                    ><Wallet class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
        </div>

        <div class="grid min-w-0 items-start gap-4 xl:grid-cols-2">
            <OwnerAccountCard
                v-for="card in accounts"
                :key="card.account"
                :card="card"
                :deepgram-balance="
                    card.account === 'deepgram' ? deepgramBalance : null
                "
            />
        </div>

        <OwnerPriceTable :prices="prices" :units="units" :unpriced="unpriced" />
    </div>
</template>
