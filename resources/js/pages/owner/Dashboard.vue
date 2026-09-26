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
import { useI18n } from '@/composables/useI18n';
import type { ApiAccountCard, OwnerConsolePayload } from '@/types';

/*
 * The owner console (spec 0007): the paid API accounts GHASIDO runs on,
 * their credit, keys and prices. Pages compose; the cards style.
 */
type Props = OwnerConsolePayload;

const props = defineProps<Props>();
const { t } = useI18n();

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

// Her balance as she sees it (spec 0007, D11), or the cost while no
// limit is set.
function balanceValue(card: ApiAccountCard | undefined): number {
    if (!card) {
        return 0;
    }

    return Math.round(
        card.mode === 'none' ? card.costUsd : (card.client.remainingUsd ?? 0),
    );
}

function balanceLabel(card: ApiAccountCard | undefined): string {
    const name = card?.label ?? '';

    return card?.mode === 'none'
        ? t(':name: no limit, cost ($)', { name })
        : t('Her :name balance ($)', { name });
}

function balanceDetail(card: ApiAccountCard | undefined): string {
    if (!card) {
        return '';
    }

    if (card.state === 'paused') {
        return t('Paused');
    }

    return card.mode === 'none'
        ? t('Recharge to set a limit')
        : t(':remaining of :credit', {
              remaining: formatUsd(card.client.remainingUsd ?? 0),
              credit: formatUsd(card.client.creditUsd),
          });
}

const tokens = computed(() =>
    qwen.value?.meters.find((meter) => meter.meter === 'tokens'),
);

const tokensLeft = computed(() =>
    tokens.value ? Math.max(0, tokens.value.granted - tokens.value.used) : 0,
);

const unpriced = computed(() =>
    props.accounts.flatMap((card) => card.unpricedModels),
);

const totalCost = computed(() =>
    props.accounts.reduce((sum, card) => sum + card.costUsd, 0),
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
    <Head :title="$t('Owner console')" />

    <div class="flex min-w-0 flex-col gap-5">
        <PageHeader
            :title="$t('API accounts')"
            :description="
                $t(
                    'Keys, credit and prices for the paid AI services GHASIDO runs on.',
                )
            "
        />

        <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :value="balanceValue(qwen)"
                :label="balanceLabel(qwen)"
                :detail="balanceDetail(qwen)"
                :tone="creditTone(qwen, 'ai')"
            >
                <template #icon
                    ><Bot class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="
                    Math.round(
                        (tokens?.limited ? tokensLeft : (tokens?.used ?? 0)) /
                            1000,
                    )
                "
                unit="k"
                :label="
                    tokens?.limited
                        ? $t('Qwen tokens left')
                        : $t('Qwen tokens used')
                "
                :detail="
                    tokens?.limited
                        ? $t(':remaining of :credit', {
                              remaining: formatCount(tokensLeft),
                              credit: formatCount(tokens.granted),
                          })
                        : $t('No token limit set')
                "
                :tone="creditTone(qwen, 'brand')"
            >
                <template #icon
                    ><Coins class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="balanceValue(deepgram)"
                :label="balanceLabel(deepgram)"
                :detail="balanceDetail(deepgram)"
                :tone="creditTone(deepgram, 'azure')"
            >
                <template #icon
                    ><AudioLines class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="Math.round(totalCost)"
                :label="$t('Your cost ($)')"
                :detail="
                    $t(':cost at provider prices', {
                        cost: formatUsd(totalCost),
                    })
                "
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
