<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    AudioLines,
    Bot,
    CircleAlert,
    KeyRound,
    Pause,
    Play,
    PlugZap,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import type { ProgressTone } from '@/components/data/ProgressBar.vue';
import InputError from '@/components/InputError.vue';
import AiCheckResult from '@/components/settings/AiCheckResult.vue';
import { cn } from '@/lib/utils';
import { balance, check, pause } from '@/routes/owner/accounts';
import {
    destroy as destroyKey,
    update as updateKey,
} from '@/routes/owner/accounts/key';
import { store as storeTopup } from '@/routes/owner/accounts/topups';
import type {
    ApiAccountCard,
    ApiAccountMeter,
    ApiAccountState,
    ApiAccountTopup,
    DeepgramBalance,
} from '@/types';
import { formatCount, formatDate, formatDateTime, formatUsd } from './format';

/*
 * One paid API account on the owner console (spec 0007): credit left,
 * recharge, key (masked only), connection tests, pause, spend by feature
 * and the recharge history. Built from the PanelCard, ProgressBar and
 * AiCheckResult recipes; no client mockup.
 */
type Props = {
    card: ApiAccountCard;
    /** Deepgram's own balance, read on request; Deepgram only. */
    deepgramBalance?: DeepgramBalance | null;
};

const props = withDefaults(defineProps<Props>(), { deepgramBalance: null });

const id = computed(() => `account-${props.card.account}`);
const editingKey = ref(false);

const stateLabel: Record<ApiAccountState, string> = {
    unlimited: 'No limit set',
    active: 'Active',
    low: 'Low credit',
    exhausted: 'Out of credit',
    paused: 'Paused',
};

const stateTone: Record<ApiAccountState, string> = {
    unlimited: 'bg-brand-50 text-brand-700',
    active: 'bg-success-tint text-success-text',
    low: 'bg-warning-tint text-warning-text',
    exhausted: 'bg-danger-tint text-danger-text',
    paused: 'bg-danger-tint text-danger-text',
};

// Bars turn amber below 20% left, when the low-credit email goes out.
function tone(share: number | null): ProgressTone {
    return (share ?? 0) < 0.2 ? 'warning' : 'brand';
}

// Seconds are entered and shown as minutes (spec 0007, D10).
function units(meter: ApiAccountMeter, value: number): string {
    return meter.meter === 'seconds'
        ? `${formatCount(Math.floor(value / 60))} min`
        : formatCount(value);
}

function meterText(meter: ApiAccountMeter): string {
    if (!meter.limited) {
        return `${units(meter, meter.used)} used · not limited`;
    }

    const left = meter.granted - meter.used;

    // Calls already running when a meter ran out can overspend a little;
    // the figure stays at zero and the overspend is said in words.
    return left < 0
        ? `0 left of ${units(meter, meter.granted)} · ${units(meter, -left)} over`
        : `${units(meter, left)} left of ${units(meter, meter.granted)}`;
}

function meterShare(meter: ApiAccountMeter): number {
    return meter.granted > 0
        ? Math.max(0, (meter.granted - meter.used) / meter.granted)
        : 0;
}

const historyHeads: Record<ApiAccountMeter['meter'], string> = {
    tokens: 'Tokens',
    characters: 'Characters',
    seconds: 'Minutes',
};

function historyValue(
    topup: ApiAccountTopup,
    meter: ApiAccountMeter['meter'],
): string {
    const value =
        meter === 'seconds'
            ? Math.round(topup.seconds / 60)
            : meter === 'tokens'
              ? topup.tokens
              : topup.characters;

    return value === 0 ? '—' : formatCount(value);
}

const amountsGrid = computed(() =>
    props.card.meters.length > 1 ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
);

const unitWords = computed(() =>
    props.card.meters.map((meter) => meter.label.toLowerCase()).join(' and '),
);

const keySource = computed((): string => {
    switch (props.card.key.source) {
        case 'owner':
            return props.card.key.updatedAt
                ? `Set here on ${formatDate(props.card.key.updatedAt)}`
                : 'Set here';
        case 'env':
            return 'From the server .env file';
        default:
            return 'No key set';
    }
});

const checking = computed(() =>
    props.card.checks.some(
        (item) =>
            item.state?.status === 'pending' ||
            item.state?.status === 'running',
    ),
);

const fieldClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full min-w-0 rounded-sm border px-2.5 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
const labelClass = 'text-ink/85 text-[12px] font-medium';
const primaryButton =
    'bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-md px-4 text-[13px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60';
const outlineButton =
    'border-line bg-surface text-brand-700 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-md border px-3.5 text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60';
const dangerButton =
    'border-danger/45 bg-surface text-danger-text hover:bg-danger-tint focus-visible:ring-danger/20 inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-md border px-3.5 text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60';
const sectionTitle = 'font-heading text-ink-indigo text-[14px] font-semibold';
const head = 'text-ink/90 px-2 text-start text-[12px] font-medium';
const cell = 'text-ink/80 px-2 py-1.5 text-[12.5px]';
</script>

<template>
    <PanelCard :title="card.label" :title-id="id" body-class="grid gap-4">
        <template #icon>
            <span
                :class="
                    cn(
                        'grid size-9 shrink-0 place-items-center rounded-xl',
                        card.account === 'qwen'
                            ? 'bg-ai-tint text-ai'
                            : 'bg-aqua-tint text-aqua',
                    )
                "
            >
                <Bot
                    v-if="card.account === 'qwen'"
                    class="size-5"
                    aria-hidden="true"
                />
                <AudioLines v-else class="size-5" aria-hidden="true" />
            </span>
        </template>
        <template #actions>
            <span
                :class="
                    cn(
                        'text-pill rounded-pill inline-flex h-6 shrink-0 items-center px-2.5',
                        stateTone[card.state],
                    )
                "
                :data-test="`${card.account}-state`"
            >
                {{ stateLabel[card.state] }}
            </span>
        </template>

        <p class="text-ink-slate text-[13px] leading-5">
            <span v-if="card.vendor !== card.label" class="text-ink font-medium"
                >{{ card.vendor }}.</span
            >
            {{ card.usedFor }}
        </p>

        <!-- Her balance and your cost (D10, D11) -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid content-start gap-1.5">
                <p :class="labelClass">Her balance</p>
                <template v-if="card.mode !== 'none'">
                    <p
                        class="font-heading text-brand-800 text-[26px] leading-8 font-bold"
                        :data-test="`${card.account}-client-balance`"
                    >
                        {{ formatUsd(card.client.remainingUsd ?? 0) }}
                    </p>
                    <ProgressBar
                        :value="(card.client.shareLeft ?? 0) * 100"
                        :tone="tone(card.client.shareLeft)"
                        :label="`${card.label} balance left`"
                    />
                    <p class="text-ink-slate text-[12px]">
                        of {{ formatUsd(card.client.creditUsd) }} credited. This
                        is what the Super Admin sees.
                    </p>
                </template>
                <template v-else>
                    <p
                        class="font-heading text-brand-800 text-[26px] leading-8 font-bold"
                    >
                        No limit
                    </p>
                    <p class="text-ink-slate text-[12px]">
                        The app keeps calling {{ card.label }} until your first
                        recharge starts the count.
                    </p>
                </template>
            </div>

            <div class="grid content-start gap-1.5">
                <p :class="labelClass">Your cost at provider prices</p>
                <p
                    class="font-heading text-ink-indigo text-[26px] leading-8 font-bold"
                >
                    {{ formatUsd(card.costUsd) }}
                </p>
                <p class="text-ink-slate text-[12px]">
                    {{ formatCount(card.calls) }} calls
                    {{
                        card.since
                            ? `since ${formatDate(card.since)}`
                            : 'so far'
                    }}. Only you see this.
                </p>
            </div>
        </div>

        <ul class="grid gap-3" :aria-label="`${card.label} usage by unit`">
            <li
                v-for="meter in card.meters"
                :key="meter.meter"
                class="grid gap-1"
                :data-test="`${card.account}-meter-${meter.meter}`"
            >
                <p
                    class="flex min-w-0 flex-wrap items-baseline justify-between gap-x-3 text-[12.5px]"
                >
                    <span class="text-ink font-medium">{{ meter.label }}</span>
                    <span class="text-ink-slate">{{ meterText(meter) }}</span>
                </p>
                <ProgressBar
                    v-if="meter.limited"
                    :value="meterShare(meter) * 100"
                    :tone="tone(meterShare(meter))"
                    :label="`${meter.label} left`"
                />
                <p class="text-ink-faint text-[11.5px]">{{ meter.covers }}</p>
            </li>
        </ul>

        <p
            v-if="card.unpricedModels.length > 0"
            class="bg-warning-tint text-warning-text flex gap-2 rounded-md px-3 py-2 text-[12.5px]"
            :data-test="`${card.account}-unpriced`"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                No price yet for
                {{ card.unpricedModels.map((row) => row.model).join(', ') }}:
                your cost for them shows as $0 until you add one under Prices.
            </span>
        </p>

        <!-- Recharge: a pack of dollars and units (D4, D10) -->
        <Form
            v-bind="storeTopup.form({ account: card.account })"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing }"
            class="border-line grid gap-2 border-t pt-4"
        >
            <h3 :class="sectionTitle">Recharge</h3>
            <div :class="cn('grid gap-2', amountsGrid)">
                <div class="grid content-start gap-1">
                    <label :for="`${id}-usd`" :class="labelClass"
                        >Dollars she sees</label
                    >
                    <input
                        :id="`${id}-usd`"
                        name="usd"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="200"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.usd" />
                </div>
                <div
                    v-for="meter in card.meters"
                    :key="meter.meter"
                    class="grid content-start gap-1"
                >
                    <label :for="`${id}-${meter.field}`" :class="labelClass">{{
                        meter.label
                    }}</label>
                    <input
                        :id="`${id}-${meter.field}`"
                        :name="meter.field"
                        type="number"
                        step="1"
                        inputmode="numeric"
                        :placeholder="
                            meter.meter === 'tokens'
                                ? '250000'
                                : meter.meter === 'seconds'
                                  ? '300'
                                  : '500000'
                        "
                        :class="fieldClass"
                    />
                    <InputError :message="errors[meter.field]" />
                </div>
            </div>
            <div
                class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
            >
                <div class="grid content-start gap-1">
                    <label :for="`${id}-note`" :class="labelClass"
                        >Note (optional)</label
                    >
                    <input
                        :id="`${id}-note`"
                        name="note"
                        type="text"
                        maxlength="255"
                        placeholder="Invoice or month"
                        :class="fieldClass"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="processing"
                    :class="primaryButton"
                    :data-test="`${card.account}-recharge-button`"
                >
                    <Wallet class="size-4" aria-hidden="true" />
                    Recharge
                </button>
            </div>
            <InputError :message="errors.note" />
            <p class="text-ink-faint text-[12px] leading-5">
                She sees the dollars; the {{ unitWords }} are the limit. Her
                dollars go down as the units are used, and AI pauses when any
                limited unit runs out. Dollars with no units are spent at your
                prices instead. A negative amount corrects a mistake.
            </p>
        </Form>

        <!-- API key (D2) -->
        <section
            class="border-line grid gap-2 border-t pt-4"
            :aria-labelledby="`${id}-key`"
        >
            <h3 :id="`${id}-key`" :class="sectionTitle">API key</h3>
            <p class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                <KeyRound
                    class="text-brand-700 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span
                    class="text-ink font-mono text-[13px]"
                    :data-test="`${card.account}-key-masked`"
                    >{{ card.key.masked ?? 'None' }}</span
                >
                <span class="text-ink-slate text-[12.5px]">{{
                    keySource
                }}</span>
            </p>

            <Form
                v-if="editingKey"
                v-bind="updateKey.form({ account: card.account })"
                :options="{ preserveScroll: true }"
                reset-on-success
                v-slot="{ errors, processing }"
                class="grid gap-1.5"
                @success="editingKey = false"
            >
                <label :for="`${id}-new-key`" :class="labelClass"
                    >New {{ card.label }} key</label
                >
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        :id="`${id}-new-key`"
                        name="key"
                        type="password"
                        autocomplete="off"
                        spellcheck="false"
                        required
                        placeholder="Paste the key"
                        :class="cn(fieldClass, 'font-mono sm:flex-1')"
                    />
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="processing"
                            :class="primaryButton"
                            :data-test="`${card.account}-save-key-button`"
                        >
                            Save key
                        </button>
                        <button
                            type="button"
                            :class="outlineButton"
                            @click="editingKey = false"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
                <InputError :message="errors.key" />
            </Form>

            <div v-else class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    :class="outlineButton"
                    @click="editingKey = true"
                >
                    {{ card.key.source === 'none' ? 'Add key' : 'Replace key' }}
                </button>
                <Form
                    v-if="card.key.source === 'owner'"
                    v-bind="destroyKey.form({ account: card.account })"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex h-10 items-center rounded-md px-2 text-[13px] font-semibold hover:underline focus-visible:ring-3 focus-visible:outline-none"
                    >
                        Use the .env key instead
                    </button>
                </Form>
            </div>
            <p class="text-ink-faint text-[12px]">
                Stored encrypted on the server and used from the next AI call.
                Only the last four characters are ever shown.
            </p>
        </section>

        <!-- Connection -->
        <section
            class="border-line grid gap-2 border-t pt-4"
            :aria-labelledby="`${id}-connection`"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 :id="`${id}-connection`" :class="sectionTitle">
                    Connection
                </h3>
                <div class="flex flex-wrap gap-2">
                    <Form
                        v-if="card.account === 'deepgram'"
                        v-bind="balance.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            :class="outlineButton"
                            data-test="deepgram-balance-button"
                        >
                            <Wallet class="size-4" aria-hidden="true" />
                            {{
                                processing
                                    ? 'Reading…'
                                    : 'Read Deepgram balance'
                            }}
                        </button>
                    </Form>
                    <Form
                        v-bind="check.form({ account: card.account })"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing || checking"
                            :class="outlineButton"
                            :data-test="`${card.account}-test-button`"
                        >
                            <PlugZap class="size-4" aria-hidden="true" />
                            Test
                        </button>
                    </Form>
                </div>
            </div>

            <div
                v-if="card.account === 'deepgram' && deepgramBalance"
                role="status"
                class="border-line bg-app-alt rounded-md border px-3 py-2 text-[12.5px]"
            >
                <p
                    v-if="deepgramBalance.ok"
                    class="text-ink"
                    data-test="deepgram-balance"
                >
                    <span class="font-semibold">
                        {{
                            deepgramBalance.balances
                                .map((item) =>
                                    item.units === 'USD'
                                        ? formatUsd(item.amount)
                                        : `${formatCount(item.amount)} ${item.units}`,
                                )
                                .join(' + ') || 'No balance'
                        }}
                    </span>
                    left on the Deepgram account
                    <template v-if="deepgramBalance.project"
                        >“{{ deepgramBalance.project }}”</template
                    >
                    <span class="text-ink-faint">
                        · read
                        {{ formatDateTime(deepgramBalance.fetchedAt) }}</span
                    >
                </p>
                <p v-else class="text-danger-text flex gap-2">
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{ deepgramBalance.error }}</span>
                </p>
            </div>

            <ul class="grid gap-2.5">
                <li
                    v-for="item in card.checks"
                    :key="item.capability"
                    class="grid gap-0.5"
                >
                    <p class="text-ink/85 text-[12px] font-medium">
                        {{ item.label }}
                    </p>
                    <AiCheckResult :check="item.state" />
                </li>
            </ul>
            <p class="text-ink-faint text-[12px]">
                A test makes one small real call, metered like any other.
            </p>
        </section>

        <!-- Pause (D6, D7) -->
        <section
            class="border-line flex flex-wrap items-center justify-between gap-3 border-t pt-4"
            :aria-labelledby="`${id}-pause`"
        >
            <div class="min-w-0 flex-1">
                <h3 :id="`${id}-pause`" :class="sectionTitle">
                    {{ card.paused ? 'Paused' : 'Running' }}
                </h3>
                <p class="text-ink-slate text-[12.5px]">
                    {{
                        card.paused
                            ? `The app makes no calls to ${card.label} until you resume.`
                            : `Pause to stop every AI call that uses ${card.label} at once, whatever the credit.`
                    }}
                </p>
            </div>
            <Form
                v-bind="pause.form({ account: card.account })"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    :class="card.paused ? primaryButton : dangerButton"
                    :data-test="`${card.account}-pause-button`"
                >
                    <Play
                        v-if="card.paused"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <Pause v-else class="size-4" aria-hidden="true" />
                    {{ card.paused ? 'Resume' : 'Pause' }}
                </button>
            </Form>
        </section>

        <!-- Spend by feature -->
        <section
            v-if="card.byFeature.length > 0"
            class="border-line grid gap-2 border-t pt-4"
            :aria-labelledby="`${id}-features`"
        >
            <h3 :id="`${id}-features`" :class="sectionTitle">
                Spend by feature{{
                    card.since ? ' since the first recharge' : ''
                }}
            </h3>
            <table class="w-full table-fixed border-collapse">
                <thead class="bg-app-alt">
                    <tr class="h-[26px]">
                        <th scope="col" :class="head">Feature</th>
                        <th scope="col" :class="cn(head, 'w-20 text-end')">
                            Calls
                        </th>
                        <th scope="col" :class="cn(head, 'w-24 text-end')">
                            Cost
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in card.byFeature"
                        :key="row.feature"
                        class="border-line border-t"
                    >
                        <td :class="cn(cell, 'truncate')">{{ row.label }}</td>
                        <td :class="cn(cell, 'text-end')">
                            {{ formatCount(row.calls) }}
                        </td>
                        <td :class="cn(cell, 'text-end')">
                            {{ formatUsd(row.cost) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Recharge history (D4) -->
        <section
            class="border-line grid gap-2 border-t pt-4"
            :aria-labelledby="`${id}-history`"
        >
            <h3 :id="`${id}-history`" :class="sectionTitle">Recharges</h3>
            <p
                v-if="card.topups.length === 0"
                class="text-ink-slate text-[13px]"
            >
                No recharge yet.
            </p>
            <table v-else class="w-full border-collapse sm:table-fixed">
                <thead class="bg-app-alt">
                    <tr class="h-[26px]">
                        <th scope="col" :class="cn(head, 'sm:w-24')">Date</th>
                        <th scope="col" :class="cn(head, 'text-end sm:w-24')">
                            Dollars
                        </th>
                        <th
                            v-for="meter in card.meters"
                            :key="meter.meter"
                            scope="col"
                            :class="cn(head, 'text-end sm:w-24')"
                        >
                            {{ historyHeads[meter.meter] }}
                        </th>
                        <th scope="col" :class="head">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="topup in card.topups"
                        :key="topup.id"
                        class="border-line border-t"
                    >
                        <td :class="cell">
                            {{ formatDate(topup.createdAt) }}
                        </td>
                        <td :class="cn(cell, 'text-end')">
                            {{ topup.usd === 0 ? '—' : formatUsd(topup.usd) }}
                        </td>
                        <td
                            v-for="meter in card.meters"
                            :key="meter.meter"
                            :class="cn(cell, 'text-end')"
                        >
                            {{ historyValue(topup, meter.meter) }}
                        </td>
                        <td
                            :class="cn(cell, 'break-words sm:truncate')"
                            :title="topup.note ?? ''"
                        >
                            {{ topup.note ?? '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </PanelCard>
</template>
