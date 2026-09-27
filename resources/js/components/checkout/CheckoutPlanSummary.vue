<script setup lang="ts">
import { BadgeCheck, Check, Sparkles, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import type { CheckoutPlan, LandingPageContent } from '@/types';
import { individualInclusions } from './individualPlan';
import { formatAmount } from './money';
import type { Currency } from './money';

/*
 * The plan being bought, kept in view beside the form. A hotel plan lists
 * its seats and shared AI points; an individual plan only its monthly AI
 * points — individuals never get seats (user request 2026-09-27).
 */
type Props = {
    content: LandingPageContent;
    plan: CheckoutPlan;
    currency: Currency;
};

const props = defineProps<Props>();

const isIndividual = computed(() => props.plan.audience === 'individual');
const amount = computed(() =>
    formatAmount(
        props.currency === 'USD' ? props.plan.priceUsd : props.plan.priceDzd,
        props.currency,
    ),
);
</script>

<template>
    <section
        class="bg-brand-900 text-surface shadow-pop relative overflow-hidden rounded-xl p-6"
        aria-labelledby="plan-summary-title"
    >
        <div
            class="bg-ai/20 pointer-events-none absolute -end-16 -top-20 size-52 rounded-full blur-3xl"
            aria-hidden="true"
        />
        <div class="relative">
            <div class="flex items-center justify-between gap-3">
                <p
                    id="plan-summary-title"
                    class="text-brand-200 text-[11px] font-bold tracking-[0.15em] uppercase"
                >
                    {{ content.checkout.summary_title }}
                </p>
                <span
                    class="bg-surface/10 text-brand-100 rounded-pill px-2.5 py-1 text-[11px] font-semibold"
                >
                    {{ isIndividual ? $t('Individual') : $t('Hotel') }}
                </span>
            </div>
            <div class="mt-4 flex items-center justify-between gap-4">
                <h2
                    class="font-heading text-surface min-w-0 text-[24px] font-semibold break-words"
                >
                    {{ plan.name }}
                </h2>
                <BadgeCheck
                    class="text-brand-200 size-6 shrink-0"
                    aria-hidden="true"
                />
            </div>
            <p class="mt-4 flex flex-wrap items-end gap-x-2 gap-y-1">
                <span
                    class="font-heading text-[34px] leading-none font-bold tracking-[-0.03em]"
                    dir="ltr"
                >
                    {{ amount }}
                </span>
                <span class="text-brand-200 pb-0.5 text-[12px]">
                    {{ currency }} / {{ content.pricing.monthly_label }}
                </span>
            </p>

            <div class="border-surface/15 mt-6 space-y-3 border-t pt-5">
                <div v-if="!isIndividual" class="flex items-center gap-3">
                    <UsersRound
                        class="text-brand-200 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="text-[13px]">
                        <strong class="font-semibold">{{
                            plan.employeeLimit
                        }}</strong>
                        {{ content.pricing.employees_label }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Sparkles
                        class="text-brand-200 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="text-[13px]">
                        <strong class="font-semibold">{{
                            plan.pointsPool.toLocaleString()
                        }}</strong>
                        {{
                            isIndividual
                                ? $t('AI points per month')
                                : content.pricing.ai_points_label
                        }}
                    </p>
                </div>
            </div>

            <ul class="mt-5 space-y-2.5">
                <template v-if="isIndividual">
                    <li
                        v-for="item in individualInclusions"
                        :key="item"
                        class="text-brand-100 flex items-start gap-2 text-[12.5px] leading-5"
                    >
                        <Check
                            class="text-success mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ $t(item) }}
                    </li>
                </template>
                <template v-else>
                    <li
                        v-for="item in content.pricing.inclusions"
                        :key="item"
                        class="text-brand-100 flex items-start gap-2 text-[12.5px] leading-5"
                    >
                        <Check
                            class="text-success mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ item }}
                    </li>
                </template>
            </ul>
        </div>
    </section>
</template>
