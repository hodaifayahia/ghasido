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
import type { ApiAccountCard, ApiAccountState, DeepgramBalance } from '@/types';
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

function share(left: number | null, total: number): number {
    return total > 0 && left !== null ? (left / total) * 100 : 0;
}

// Each bar by its own unit: tokens running out does not colour the
// dollar bar.
function barTone(left: number | null, total: number): ProgressTone {
    return share(left, total) < 10 ? 'warning' : 'brand';
}

// Calls already running when the credit ran out can overspend a little;
// the figure stays at zero and the overspend is said in words.
function over(left: number | null): number {
    return left !== null && left < 0 ? -left : 0;
}

const unlimited = computed(
    () => !props.card.limitedByUsd && !props.card.limitedByTokens,
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

        <!-- Credit (D4–D6) -->
        <div
            :class="
                cn(
                    'grid gap-4',
                    card.limitedByUsd &&
                        card.limitedByTokens &&
                        'sm:grid-cols-2',
                )
            "
        >
            <div v-if="card.limitedByUsd" class="grid gap-1.5">
                <p :class="labelClass">Dollar credit left</p>
                <p
                    class="font-heading text-brand-800 text-[26px] leading-8 font-bold"
                >
                    {{ formatUsd(Math.max(0, card.remaining.usd ?? 0)) }}
                </p>
                <ProgressBar
                    :value="share(card.remaining.usd, card.credit.usd)"
                    :tone="barTone(card.remaining.usd, card.credit.usd)"
                    :label="`${card.label} dollar credit left`"
                />
                <p class="text-ink-slate text-[12px]">
                    of {{ formatUsd(card.credit.usd) }} ·
                    {{ formatUsd(card.spent.usd) }} spent<template
                        v-if="over(card.remaining.usd) > 0"
                    >
                        ·
                        {{ formatUsd(over(card.remaining.usd)) }} over</template
                    >
                </p>
            </div>

            <div v-if="card.limitedByTokens" class="grid gap-1.5">
                <p :class="labelClass">Tokens left</p>
                <p
                    class="font-heading text-brand-800 text-[26px] leading-8 font-bold"
                >
                    {{ formatCount(Math.max(0, card.remaining.tokens ?? 0)) }}
                </p>
                <ProgressBar
                    :value="share(card.remaining.tokens, card.credit.tokens)"
                    :tone="barTone(card.remaining.tokens, card.credit.tokens)"
                    :label="`${card.label} tokens left`"
                />
                <p class="text-ink-slate text-[12px]">
                    of {{ formatCount(card.credit.tokens) }} ·
                    {{ formatCount(card.spent.tokens) }} used<template
                        v-if="over(card.remaining.tokens) > 0"
                    >
                        ·
                        {{ formatCount(over(card.remaining.tokens)) }}
                        over</template
                    >
                </p>
            </div>

            <div v-if="unlimited" class="grid gap-1">
                <p :class="labelClass">Spent so far</p>
                <p
                    class="font-heading text-brand-800 text-[26px] leading-8 font-bold"
                >
                    {{ formatUsd(card.spent.usd) }}
                </p>
                <p class="text-ink-slate text-[12.5px]">
                    {{ formatCount(card.calls) }} calls. No limit is set: the
                    app keeps calling {{ card.label }} until you add a first
                    recharge, which starts the count.
                </p>
            </div>
        </div>

        <p v-if="card.since" class="text-ink-faint -mt-2 text-[12px]">
            Counting since {{ formatDate(card.since) }} ·
            {{ formatCount(card.calls) }} calls
        </p>

        <p
            v-if="card.unpricedModels.length > 0"
            class="bg-warning-tint text-warning-text flex gap-2 rounded-md px-3 py-2 text-[12.5px]"
            :data-test="`${card.account}-unpriced`"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                No price yet for
                {{ card.unpricedModels.map((row) => row.model).join(', ') }}:
                their usage counts as $0 until you add one under Prices.
            </span>
        </p>

        <!-- Recharge (D4) -->
        <Form
            v-bind="storeTopup.form({ account: card.account })"
            :options="{ preserveScroll: true }"
            reset-on-success
            v-slot="{ errors, processing }"
            class="border-line grid gap-2 border-t pt-4"
        >
            <h3 :class="sectionTitle">Recharge</h3>
            <div
                :class="
                    cn(
                        'grid gap-2 sm:items-end',
                        card.tracksTokens
                            ? 'sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.3fr)_auto]'
                            : 'sm:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)_auto]',
                    )
                "
            >
                <div class="grid gap-1">
                    <label :for="`${id}-usd`" :class="labelClass"
                        >Dollars</label
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
                </div>
                <div v-if="card.tracksTokens" class="grid gap-1">
                    <label :for="`${id}-tokens`" :class="labelClass"
                        >Tokens</label
                    >
                    <input
                        :id="`${id}-tokens`"
                        name="tokens"
                        type="number"
                        step="1000"
                        inputmode="numeric"
                        placeholder="250000"
                        :class="fieldClass"
                    />
                </div>
                <div class="grid gap-1">
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
            <InputError :message="errors.usd" />
            <InputError :message="errors.tokens" />
            <InputError :message="errors.note" />
            <p class="text-ink-faint text-[12px]">
                Adds to the credit.
                <template v-if="card.tracksTokens"
                    >Fill dollars, tokens or both: the app stops when either
                    runs out.</template
                >
                A negative amount corrects a mistake.
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
            <table v-else class="w-full table-fixed border-collapse">
                <thead class="bg-app-alt">
                    <tr class="h-[26px]">
                        <th scope="col" :class="cn(head, 'w-24')">Date</th>
                        <th scope="col" :class="cn(head, 'w-24 text-end')">
                            Dollars
                        </th>
                        <th
                            v-if="card.tracksTokens"
                            scope="col"
                            :class="cn(head, 'w-24 text-end')"
                        >
                            Tokens
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
                            v-if="card.tracksTokens"
                            :class="cn(cell, 'text-end')"
                        >
                            {{
                                topup.tokens === 0
                                    ? '—'
                                    : formatCount(topup.tokens)
                            }}
                        </td>
                        <td
                            :class="cn(cell, 'truncate')"
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
