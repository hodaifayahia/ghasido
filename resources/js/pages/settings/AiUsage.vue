<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Activity, Coins, Cpu, Wallet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AiCreditPanel from '@/components/common/AiCreditPanel.vue';
import PanelCard from '@/components/common/PanelCard.vue';
import StatCard from '@/components/common/StatCard.vue';
import Heading from '@/components/Heading.vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as aiUsageIndex } from '@/routes/ai-usage';
import type { AiCreditAccount, AiUsageReport } from '@/types';

/*
 * Settings → AI usage (API-03, AIL-04; spec 0005 §4.3; Super Admin only):
 * what the platform's AI calls cost, by feature, model, hotel and day.
 * The prices behind the figures are the platform owner's, set on the owner
 * console (spec 0007, D8); this page only reads them. No client mockup:
 * built from the admin stat-card, panel and table recipes.
 */
type Props = {
    report: AiUsageReport;
    periods: number[];
    hotels: { id: number; name: string }[];
    /** The AI credit the platform owner gave her (spec 0007, D11). */
    aiCredit: AiCreditAccount[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('AI usage'), href: aiUsageIndex() }],
    },
});

const hotel = ref<string>(
    props.report.hotel === null ? '' : String(props.report.hotel),
);

function filter(period: number, hotelId: string): void {
    router.get(
        aiUsageIndex.url({
            query: { period, ...(hotelId === '' ? {} : { hotel: hotelId }) },
        }),
        {},
        { preserveState: true, preserveScroll: true, only: ['report'] },
    );
}

watch(hotel, (value) => filter(props.report.period, value));

const number = new Intl.NumberFormat('en-GB');
const whole = new Intl.NumberFormat('en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const small = new Intl.NumberFormat('en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});
// Two decimals for everyday amounts; fractions of a unit keep four, so a
// cheap model's cost is not shown as 0.00.
const money = {
    format: (value: number): string =>
        value >= 1 || value === 0 ? whole.format(value) : small.format(value),
};

const maxDaily = computed(() =>
    Math.max(0.0001, ...props.report.daily.map((day) => day.cost)),
);
const maxDailyCalls = computed(() =>
    Math.max(1, ...props.report.daily.map((day) => day.calls)),
);
const chartByCost = computed(() => props.report.totals.cost > 0);

const fieldClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-9 w-full rounded-sm border px-2.5 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
const cell = 'text-ink/80 px-2 py-1.5 text-[12.5px]';
const head = 'text-ink/90 px-2 text-start text-[12px] font-medium';
</script>

<template>
    <Head :title="$t('AI usage')" />
    <h1 class="sr-only">{{ $t('AI usage') }}</h1>

    <div class="flex min-w-0 flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('AI usage')"
            :description="
                $t(
                    'What the platform\'s AI calls cost, by feature, model, hotel and day, at the platform owner\'s prices.',
                )
            "
        />

        <AiCreditPanel :accounts="aiCredit" />

        <div class="flex flex-wrap items-center gap-3">
            <div
                class="border-line bg-surface inline-flex rounded-md border p-0.5"
                role="group"
                :aria-label="$t('Period')"
            >
                <button
                    v-for="period in periods"
                    :key="period"
                    type="button"
                    :aria-pressed="report.period === period"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600/15 h-8 rounded-sm px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none',
                            report.period === period
                                ? 'bg-brand-600 text-white'
                                : 'text-brand-700 hover:bg-brand-50',
                        )
                    "
                    @click="filter(period, hotel)"
                >
                    {{ $tc(':count day|:count days', period) }}
                </button>
            </div>
            <label for="usage-hotel" class="sr-only">{{ $t('Hotel') }}</label>
            <select
                id="usage-hotel"
                v-model="hotel"
                :class="cn(fieldClass, 'w-auto min-w-48')"
            >
                <option value="">{{ $t('All hotels and platform') }}</option>
                <option
                    v-for="item in hotels"
                    :key="item.id"
                    :value="String(item.id)"
                >
                    {{ item.name }}
                </option>
            </select>
        </div>

        <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :value="report.totals.calls"
                :label="$t('AI calls')"
                :detail="$tc('Last :count day|Last :count days', report.period)"
                tone="brand"
            >
                <template #icon
                    ><Activity class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="
                    Math.round(
                        (report.totals.promptTokens +
                            report.totals.completionTokens) /
                            1000,
                    )
                "
                unit="k"
                :label="$t('Tokens and characters')"
                :detail="
                    $t(':in in · :out out', {
                        in: number.format(report.totals.promptTokens),
                        out: number.format(report.totals.completionTokens),
                    })
                "
                tone="azure"
            >
                <template #icon
                    ><Cpu class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="Math.round(report.totals.cost)"
                :label="$t('Estimated cost')"
                :detail="
                    report.totals.estimated
                        ? $t(':cost · partly at current prices', {
                              cost: money.format(report.totals.cost),
                          })
                        : money.format(report.totals.cost)
                "
                tone="warning"
            >
                <template #icon
                    ><Wallet class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
            <StatCard
                :value="report.totals.points"
                :label="$t('Learner AI points spent')"
                :detail="$t('From hotels\' monthly allowances')"
                tone="ai"
            >
                <template #icon
                    ><Coins class="size-6" aria-hidden="true"
                /></template>
            </StatCard>
        </div>

        <p
            v-if="report.unpricedModels.length > 0"
            class="bg-warning-tint text-warning-text rounded-md px-3 py-2 text-[12.5px]"
            data-test="unpriced-models"
        >
            {{
                $t(
                    'No price yet for :models: their calls count as free until the platform owner adds one.',
                    { models: report.unpricedModels.join(', ') },
                )
            }}
        </p>

        <PanelCard :title="$t('Cost per day')" title-id="usage-daily">
            <p
                v-if="report.totals.calls === 0"
                class="text-ink-slate text-[13px]"
            >
                {{ $t('No AI calls in this period.') }}
            </p>
            <div
                v-else
                class="flex h-40 items-end gap-[3px]"
                role="img"
                :aria-label="
                    chartByCost
                        ? $t('Cost per day over the last :count days', {
                              count: report.period,
                          })
                        : $t('Calls per day over the last :count days', {
                              count: report.period,
                          })
                "
            >
                <div
                    v-for="day in report.daily"
                    :key="day.date"
                    class="bg-brand-400 hover:bg-brand-600 min-w-0 flex-1 rounded-t-[3px] transition-colors"
                    :style="{
                        height: `${Math.max(2, (chartByCost ? day.cost / maxDaily : day.calls / maxDailyCalls) * 100)}%`,
                    }"
                    :title="
                        $t(':date: :calls calls · :cost', {
                            date: day.label,
                            calls: day.calls,
                            cost: money.format(day.cost),
                        })
                    "
                />
            </div>
            <div
                v-if="report.totals.calls > 0"
                class="text-ink-slate mt-1.5 flex justify-between text-[11px]"
            >
                <span>{{ report.daily[0]?.label }}</span>
                <span>{{ report.daily[report.daily.length - 1]?.label }}</span>
            </div>
        </PanelCard>

        <div class="grid min-w-0 gap-4 xl:grid-cols-2">
            <PanelCard :title="$t('By feature')" title-id="usage-feature">
                <table class="w-full table-fixed border-collapse">
                    <thead class="bg-app-alt">
                        <tr class="h-[26px]">
                            <th scope="col" :class="head">
                                {{ $t('Feature') }}
                            </th>
                            <th scope="col" :class="cn(head, 'w-20 text-end')">
                                {{ $t('Calls') }}
                            </th>
                            <th scope="col" :class="cn(head, 'w-24 text-end')">
                                {{ $t('Cost') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.byFeature"
                            :key="row.feature"
                            class="border-line border-t"
                        >
                            <td :class="cn(cell, 'truncate')">
                                {{ row.label }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ number.format(row.calls) }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ money.format(row.cost) }}
                            </td>
                        </tr>
                        <tr v-if="report.byFeature.length === 0">
                            <td colspan="3" :class="cell">
                                {{ $t('No calls yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </PanelCard>

            <PanelCard
                :title="report.hotel === null ? $t('By hotel') : $t('By model')"
                title-id="usage-hotel-model"
            >
                <table
                    v-if="report.hotel === null"
                    class="w-full table-fixed border-collapse"
                >
                    <thead class="bg-app-alt">
                        <tr class="h-[26px]">
                            <th scope="col" :class="head">{{ $t('Hotel') }}</th>
                            <th scope="col" :class="cn(head, 'w-20 text-end')">
                                {{ $t('Calls') }}
                            </th>
                            <th scope="col" :class="cn(head, 'w-24 text-end')">
                                {{ $t('Cost') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.byHotel"
                            :key="row.hotelId ?? 'platform'"
                            class="border-line border-t"
                        >
                            <td :class="cn(cell, 'truncate')">
                                {{ row.hotel }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ number.format(row.calls) }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ money.format(row.cost) }}
                            </td>
                        </tr>
                        <tr v-if="report.byHotel.length === 0">
                            <td colspan="3" :class="cell">
                                {{ $t('No calls yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table v-else class="w-full table-fixed border-collapse">
                    <thead class="bg-app-alt">
                        <tr class="h-[26px]">
                            <th scope="col" :class="head">{{ $t('Model') }}</th>
                            <th scope="col" :class="cn(head, 'w-20 text-end')">
                                {{ $t('Calls') }}
                            </th>
                            <th scope="col" :class="cn(head, 'w-24 text-end')">
                                {{ $t('Cost') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.byModel"
                            :key="`${row.provider}-${row.model}`"
                            class="border-line border-t"
                        >
                            <td
                                :class="cn(cell, 'truncate')"
                                :title="row.provider"
                            >
                                {{ row.model }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ number.format(row.calls) }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ money.format(row.cost) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </PanelCard>
        </div>

        <PanelCard :title="$t('By model')" title-id="usage-model">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse">
                    <thead class="bg-app-alt">
                        <tr class="h-[26px]">
                            <th scope="col" :class="head">{{ $t('Model') }}</th>
                            <th scope="col" :class="head">
                                {{ $t('Provider') }}
                            </th>
                            <th scope="col" :class="cn(head, 'text-end')">
                                {{ $t('Calls') }}
                            </th>
                            <th scope="col" :class="cn(head, 'text-end')">
                                {{ $t('In') }}
                            </th>
                            <th scope="col" :class="cn(head, 'text-end')">
                                {{ $t('Out') }}
                            </th>
                            <th scope="col" :class="cn(head, 'text-end')">
                                {{ $t('Cost') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.byModel"
                            :key="`${row.provider}-${row.model}`"
                            class="border-line border-t"
                        >
                            <td :class="cn(cell, 'text-ink font-medium')">
                                {{ row.model }}
                            </td>
                            <td :class="cell">{{ row.provider }}</td>
                            <td :class="cn(cell, 'text-end')">
                                {{ number.format(row.calls) }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ number.format(row.promptTokens) }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ number.format(row.completionTokens) }}
                            </td>
                            <td :class="cn(cell, 'text-end')">
                                {{ money.format(row.cost) }}
                            </td>
                        </tr>
                        <tr v-if="report.byModel.length === 0">
                            <td colspan="6" :class="cell">
                                {{ $t('No calls yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </PanelCard>

        <!-- Who used the points and where (client request 2026-10-02). -->
        <PanelCard :title="$t('By account')" title-id="usage-by-account">
            <table class="w-full table-fixed border-collapse">
                <thead class="bg-app-alt">
                    <tr class="h-[26px]">
                        <th scope="col" :class="head">{{ $t('Account') }}</th>
                        <th
                            scope="col"
                            :class="cn(head, 'hidden md:table-cell')"
                        >
                            {{ $t('Where') }}
                        </th>
                        <th scope="col" :class="cn(head, 'w-24 text-end')">
                            {{ $t('Points') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in report.byUser ?? []"
                        :key="row.userId"
                        class="border-line border-t align-top"
                    >
                        <td :class="cn(cell, 'min-w-0')">
                            <span
                                class="text-ink block truncate font-semibold"
                                >{{ row.name }}</span
                            >
                            <span
                                class="text-ink-slate block truncate text-[11px]"
                                >{{
                                    [row.username, row.hotel]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}</span
                            >
                            <span
                                class="text-ink-slate block text-[11px] md:hidden"
                                >{{
                                    row.where
                                        .map(
                                            (place) =>
                                                `${place.label}: ${number.format(place.points)}`,
                                        )
                                        .join(' · ')
                                }}</span
                            >
                        </td>
                        <td :class="cn(cell, 'hidden md:table-cell')">
                            <ul class="grid gap-0.5">
                                <li
                                    v-for="place in row.where"
                                    :key="place.label"
                                    class="text-ink-slate text-[12px]"
                                >
                                    {{ place.label }}:
                                    <span class="text-ink font-semibold">{{
                                        number.format(place.points)
                                    }}</span>
                                    {{
                                        $tc(
                                            ':count call|:count calls',
                                            place.calls,
                                        )
                                    }}
                                </li>
                            </ul>
                        </td>
                        <td :class="cn(cell, 'text-end font-semibold')">
                            {{ number.format(row.points) }}
                        </td>
                    </tr>
                    <tr v-if="(report.byUser ?? []).length === 0">
                        <td colspan="3" :class="cell">
                            {{ $t('No points used in this period.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </PanelCard>
    </div>
</template>
