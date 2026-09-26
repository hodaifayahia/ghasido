<script setup lang="ts">
import { CircleAlert, Wallet } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import { formatCount, formatUsd } from '@/components/owner/format';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { AiCreditAccount, ApiAccountState } from '@/types';

/*
 * The AI credit the platform owner gave the Super Admin (spec 0007, D11),
 * per service: dollars left of what she was credited and, for a pack, the
 * units left. Read-only; recharges happen on the owner console. No client
 * mockup: the PanelCard + ProgressBar recipes of the dashboard.
 */
type Props = {
    accounts: AiCreditAccount[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
const { t } = useI18n();

const stateLabel: Record<ApiAccountState, string> = {
    unlimited: tk('No limit set'),
    active: tk('Active'),
    low: tk('Running low'),
    exhausted: tk('Out of credit'),
    paused: tk('Paused'),
};

const stateTone: Record<ApiAccountState, string> = {
    unlimited: 'bg-brand-50 text-brand-700',
    active: 'bg-success-tint text-success-text',
    low: 'bg-warning-tint text-warning-text',
    exhausted: 'bg-danger-tint text-danger-text',
    paused: 'bg-danger-tint text-danger-text',
};

function unitsLeft(meter: AiCreditAccount['meters'][number]): string {
    if (meter.meter === 'seconds') {
        return t(':left of :granted minutes left', {
            left: formatCount(Math.floor(meter.left / 60)),
            granted: formatCount(Math.floor(meter.granted / 60)),
        });
    }

    const counts = {
        left: formatCount(meter.left),
        granted: formatCount(meter.granted),
    };

    return meter.meter === 'tokens'
        ? t(':left of :granted tokens left', counts)
        : t(':left of :granted characters left', counts);
}
</script>

<template>
    <PanelCard
        :title="$t('AI credit')"
        title-id="ai-credit-title"
        :class="props.class"
        body-class="mt-2"
    >
        <template #icon>
            <span
                class="bg-azure/20 text-brand-600 grid size-8 shrink-0 place-items-center rounded-xl"
            >
                <Wallet class="size-4" aria-hidden="true" />
            </span>
        </template>

        <div class="grid min-w-0 gap-2 md:grid-cols-2">
            <article
                v-for="item in accounts"
                :key="item.account"
                class="border-line bg-brand-100/35 grid min-w-0 content-start gap-1.5 rounded-md border p-3.5"
                :data-test="`ai-credit-${item.account}`"
            >
                <div class="flex min-w-0 items-center justify-between gap-2">
                    <p
                        class="text-ink-slate truncate text-xs font-semibold tracking-wide uppercase"
                    >
                        {{ item.service }}
                    </p>
                    <span
                        :class="
                            cn(
                                'text-pill rounded-pill inline-flex h-6 shrink-0 items-center px-2.5',
                                stateTone[item.state],
                            )
                        "
                    >
                        {{ $t(stateLabel[item.state]) }}
                    </span>
                </div>

                <template v-if="item.mode !== 'none'">
                    <p
                        v-if="item.creditUsd > 0"
                        class="font-heading text-brand-800 text-2xl leading-7 font-bold"
                    >
                        {{ formatUsd(item.remainingUsd ?? 0) }}
                        <span
                            class="text-ink-slate font-sans text-xs font-normal"
                        >
                            {{
                                $t('left of :amount', {
                                    amount: formatUsd(item.creditUsd),
                                })
                            }}
                        </span>
                    </p>
                    <ProgressBar
                        :value="(item.shareLeft ?? 0) * 100"
                        :tone="
                            (item.shareLeft ?? 0) < 0.2 ? 'warning' : 'brand'
                        "
                        :label="
                            $t(':service credit left', {
                                service: item.service,
                            })
                        "
                    />
                    <p
                        v-for="meter in item.meters"
                        :key="meter.meter"
                        class="text-ink-indigo text-xs font-medium"
                    >
                        {{ unitsLeft(meter) }}
                    </p>
                </template>
                <p v-else class="text-ink-slate text-xs">
                    {{
                        $t(
                            'No limit set: the platform owner has not added a credit for this service yet.',
                        )
                    }}
                </p>

                <p
                    v-if="item.state === 'exhausted' || item.state === 'paused'"
                    class="text-danger-text flex gap-1.5 text-xs"
                >
                    <CircleAlert
                        class="mt-px size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{
                        $t(
                            'AI features that use this service are paused. Ask the platform owner to recharge.',
                        )
                    }}</span>
                </p>
                <p
                    v-else-if="item.state === 'low'"
                    class="text-warning-text flex gap-1.5 text-xs"
                >
                    <CircleAlert
                        class="mt-px size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{
                        $t(
                            'Running low. Ask the platform owner to recharge soon.',
                        )
                    }}</span>
                </p>
            </article>
        </div>
    </PanelCard>
</template>
