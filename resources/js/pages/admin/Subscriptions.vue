<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    Pencil,
    Plus,
    Sparkles,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import PanelCard from '@/components/common/PanelCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { dashboard, subscriptions } from '@/routes';
import { hotelPlan } from '@/routes/subscriptions';
import { update as updatePlan } from '@/routes/subscriptions/plans';
import { tk } from '@/lib/i18n';

type Plan = {
    id: number;
    name: string;
    slug: string;
    /** Hotel plans sell seats; individual plans sell one learner's monthly AI points (2026-09-27). */
    audience: 'hotel' | 'individual';
    employeeLimit: number;
    priceDzd: number;
    priceUsd: number;
    extraPointsPriceDzd: number;
    extraPointsPriceUsd: number;
    extraSeatPriceDzd: number;
    extraSeatPriceUsd: number;
    pointsPerEmployee: number;
    bonusPointsPerEmployee: number;
    voicePointsPer10Minutes: number;
    aiActionPoints: number;
    isActive: boolean;
    hotelCount: number;
    /** Individual subscribers on this plan. */
    subscriberCount: number;
    pointPool: number;
};

type HotelRow = {
    id: number;
    name: string;
    city: string;
    planId: number;
    planName: string | null;
    usedEmployees: number;
    employeeLimit: number;
};

type PaymentMethod = {
    id: number;
    name: string;
    recipientName: string | null;
    accountReference: string | null;
    instructions: string | null;
    sortOrder: number;
    isActive: boolean;
};

type Props = {
    plans: Plan[];
    activePlans: Pick<
        Plan,
        'id' | 'name' | 'employeeLimit' | 'priceDzd' | 'priceUsd'
    >[];
    hotels: HotelRow[];
    paymentMethods: PaymentMethod[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Subscriptions'), href: subscriptions() },
        ],
    },
});

const tab = ref<'plans' | 'hotels' | 'payments'>('plans');
const editing = ref<Plan | null>(null);
const editorOpen = ref(false);
const form = useForm({
    name: '',
    employee_limit: 1,
    price_dzd: 0,
    price_usd: 0,
    extra_points_price_dzd: 0,
    extra_points_price_usd: 0,
    extra_seat_price_dzd: 0,
    extra_seat_price_usd: 0,
    points_per_employee: 2000,
    bonus_points_per_employee: 1000,
    voice_points_per_10_minutes: 100,
    ai_action_points: 50,
    is_active: true,
});
const selectedPlan = reactive<Record<number, number>>(
    Object.fromEntries(props.hotels.map((hotel) => [hotel.id, hotel.planId])),
);
const savingHotel = ref<number | null>(null);
const editingPaymentMethod = ref<PaymentMethod | null>(null);
const paymentMethodEditorOpen = ref(false);
const paymentMethodForm = useForm({
    name: '',
    recipient_name: '',
    account_reference: '',
    instructions: '',
    sort_order: 1,
    is_active: false,
});
const pointTopUpHotel = ref<HotelRow | null>(null);
const pointTopUpOpen = ref(false);
const pointTopUpForm = useForm({
    hotel_id: 0,
    points: 5000,
    currency: 'DZD' as 'DZD' | 'USD',
    amount_dzd: 0,
    amount_usd: 0,
    payment_method_id: null as number | null,
    payment_reference: '',
    payment_received: false,
});

const activeHotelCount = computed(() => props.hotels.length);
const hotelPlans = computed(() =>
    props.plans.filter((plan) => plan.audience !== 'individual'),
);
const individualPlans = computed(() =>
    props.plans.filter((plan) => plan.audience === 'individual'),
);
/** An individual plan has no seats and no shared pool, only AI points. */
const editingIndividual = computed(
    () => editing.value?.audience === 'individual',
);
const activePaymentMethods = computed(() =>
    props.paymentMethods.filter((method) => method.isActive),
);

function formatDzd(value: number): string {
    return `${new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 0 }).format(value)} DZD`;
}

function formatUsd(value: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(value);
}

function editPlan(plan: Plan): void {
    editing.value = plan;
    form.name = plan.name;
    form.employee_limit = plan.employeeLimit;
    form.price_dzd = plan.priceDzd;
    form.price_usd = plan.priceUsd;
    form.extra_points_price_dzd = plan.extraPointsPriceDzd;
    form.extra_points_price_usd = plan.extraPointsPriceUsd;
    form.extra_seat_price_dzd = plan.extraSeatPriceDzd;
    form.extra_seat_price_usd = plan.extraSeatPriceUsd;
    form.points_per_employee = plan.pointsPerEmployee;
    form.bonus_points_per_employee = plan.bonusPointsPerEmployee;
    form.voice_points_per_10_minutes = plan.voicePointsPer10Minutes;
    form.ai_action_points = plan.aiActionPoints;
    form.is_active = plan.isActive;
    form.clearErrors();
    editorOpen.value = true;
}

function savePlan(): void {
    if (!editing.value) return;

    form.patch(updatePlan(editing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
        },
    });
}

function saveHotelPlan(hotel: HotelRow): void {
    savingHotel.value = hotel.id;
    router.put(
        hotelPlan().url,
        { hotel_id: hotel.id, plan_id: selectedPlan[hotel.id] },
        {
            preserveScroll: true,
            onFinish: () => {
                savingHotel.value = null;
            },
        },
    );
}

function addPaidPoints(hotel: HotelRow): void {
    pointTopUpHotel.value = hotel;
    pointTopUpForm.hotel_id = hotel.id;
    pointTopUpForm.points = 5000;
    pointTopUpForm.currency = 'DZD';
    fillSuggestedAmount();
    pointTopUpForm.payment_method_id =
        activePaymentMethods.value[0]?.id ?? null;
    pointTopUpForm.payment_reference = '';
    pointTopUpForm.payment_received = false;
    pointTopUpForm.clearErrors();
    pointTopUpOpen.value = true;
}

/** The hotel's plan, whose extra-points prices suggest the amount due. */
const pointTopUpPlan = computed(() =>
    props.plans.find((plan) => plan.id === pointTopUpHotel.value?.planId),
);

/** Price of the points being added, from the plan's per-1,000 price. */
const suggestedAmount = computed(() => {
    const plan = pointTopUpPlan.value;
    if (!plan) return 0;
    const packs = (Number(pointTopUpForm.points) || 0) / 1000;

    return pointTopUpForm.currency === 'USD'
        ? Math.round(packs * plan.extraPointsPriceUsd * 100) / 100
        : Math.round(packs * plan.extraPointsPriceDzd);
});

function fillSuggestedAmount(): void {
    if (pointTopUpForm.currency === 'USD') {
        pointTopUpForm.amount_usd = suggestedAmount.value;
    } else {
        pointTopUpForm.amount_dzd = suggestedAmount.value;
    }
}

function savePaidPoints(): void {
    pointTopUpForm.post('/subscriptions/ai-point-topups', {
        preserveScroll: true,
        onSuccess: () => {
            pointTopUpOpen.value = false;
            pointTopUpHotel.value = null;
        },
    });
}

function editPaymentMethod(method: PaymentMethod): void {
    editingPaymentMethod.value = method;
    paymentMethodForm.name = method.name;
    paymentMethodForm.recipient_name = method.recipientName ?? '';
    paymentMethodForm.account_reference = method.accountReference ?? '';
    paymentMethodForm.instructions = method.instructions ?? '';
    paymentMethodForm.sort_order = method.sortOrder;
    paymentMethodForm.is_active = method.isActive;
    paymentMethodForm.clearErrors();
    paymentMethodEditorOpen.value = true;
}

function addPaymentMethod(): void {
    editingPaymentMethod.value = null;
    paymentMethodForm.reset();
    paymentMethodForm.sort_order =
        Math.max(0, ...props.paymentMethods.map((method) => method.sortOrder)) +
        1;
    paymentMethodForm.clearErrors();
    paymentMethodEditorOpen.value = true;
}

function savePaymentMethod(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            paymentMethodEditorOpen.value = false;
        },
    };

    if (editingPaymentMethod.value) {
        paymentMethodForm.patch(
            `/subscriptions/payment-methods/${editingPaymentMethod.value.id}`,
            options,
        );

        return;
    }

    paymentMethodForm.post('/subscriptions/payment-methods', options);
}
</script>

<template>
    <Head :title="$t('Subscriptions')" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            :title="$t('Subscriptions')"
            :description="
                $t(
                    'Set hotel seat limits, DZD and USD prices, and AI point rates.',
                )
            "
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div
                class="bg-surface border-line shadow-card inline-flex rounded-md border p-1"
                role="tablist"
                :aria-label="$t('Subscription management')"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === 'plans'"
                    class="rounded px-3 py-2 text-[12px] font-semibold transition-colors"
                    :class="
                        tab === 'plans'
                            ? 'bg-brand-100/70 text-brand-700'
                            : 'text-ink-slate hover:bg-brand-50'
                    "
                    @click="tab = 'plans'"
                >
                    {{ $t('Plans') }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === 'hotels'"
                    class="rounded px-3 py-2 text-[12px] font-semibold transition-colors"
                    :class="
                        tab === 'hotels'
                            ? 'bg-brand-100/70 text-brand-700'
                            : 'text-ink-slate hover:bg-brand-50'
                    "
                    @click="tab = 'hotels'"
                >
                    {{ $t('Hotel subscriptions') }}
                    <span class="text-ink-muted ms-1">{{
                        activeHotelCount
                    }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === 'payments'"
                    class="rounded px-3 py-2 text-[12px] font-semibold transition-colors"
                    :class="
                        tab === 'payments'
                            ? 'bg-brand-100/70 text-brand-700'
                            : 'text-ink-slate hover:bg-brand-50'
                    "
                    @click="tab = 'payments'"
                >
                    {{ $t('Payment methods') }}
                </button>
            </div>
            <p class="text-ink-muted text-[11.5px]">
                {{ $t('Changes are recorded in the audit log.') }}
            </p>
        </div>

        <section
            v-if="tab === 'plans'"
            class="grid min-w-0 gap-5"
            role="tabpanel"
            :aria-label="$t('Plans')"
        >
            <section
                class="grid min-w-0 gap-2.5"
                aria-labelledby="hotel-plans-heading"
            >
                <header class="flex min-w-0 items-center gap-2.5">
                    <span
                        class="bg-brand-100 text-brand-700 grid size-8 shrink-0 place-items-center rounded-full"
                    >
                        <Building2 class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2
                            id="hotel-plans-heading"
                            class="font-heading text-brand-800 text-base font-semibold"
                        >
                            {{ $t('Hotel plans') }}
                            <span class="text-ink-muted ms-1 text-[12px]">{{
                                hotelPlans.length
                            }}</span>
                        </h2>
                        <p class="text-ink-slate text-[12px]">
                            {{
                                $t(
                                    'Sized by employee seats, with a shared monthly AI point pool.',
                                )
                            }}
                        </p>
                    </div>
                </header>
                <div class="grid min-w-0 gap-3 lg:grid-cols-3">
                    <PanelCard
                        v-for="plan in hotelPlans"
                        :key="plan.id"
                        :title="plan.name"
                        :title-id="`plan-${plan.id}-heading`"
                        class="min-h-[220px]"
                        body-class="flex flex-col"
                    >
                        <template #icon>
                            <span
                                class="bg-brand-100 text-brand-700 grid size-8 place-items-center rounded-full"
                            >
                                <CreditCard class="size-4" aria-hidden="true" />
                            </span>
                        </template>
                        <template #actions>
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                :class="
                                    plan.isActive
                                        ? 'bg-success/10 text-success'
                                        : 'bg-ink-faint/20 text-ink-slate'
                                "
                            >
                                {{
                                    plan.isActive
                                        ? $t('Available')
                                        : $t('Inactive')
                                }}
                            </span>
                        </template>

                        <div class="flex items-baseline gap-1.5">
                            <p
                                class="font-heading text-brand-900 text-[24px] leading-7 font-bold"
                            >
                                {{ formatDzd(plan.priceDzd) }}
                            </p>
                            <span class="text-ink-muted text-[11px]">{{
                                $t('/ month')
                            }}</span>
                        </div>
                        <p
                            class="text-ink-slate mt-1 text-[12px] font-semibold"
                        >
                            {{
                                $t('International: :price', {
                                    price: formatUsd(plan.priceUsd),
                                })
                            }}
                            <span class="text-ink-muted font-normal">{{
                                $t('/ month')
                            }}</span>
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="border-line/80 rounded-md border p-2.5">
                                <div
                                    class="text-ink-slate flex items-center gap-1.5"
                                >
                                    <Users
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span class="text-[10px] font-medium">{{
                                        $t('Employee seats')
                                    }}</span>
                                </div>
                                <p
                                    class="font-heading text-brand-800 mt-1 text-[18px] font-semibold"
                                >
                                    {{ plan.employeeLimit }}
                                </p>
                            </div>
                            <div class="border-line/80 rounded-md border p-2.5">
                                <div
                                    class="text-ink-slate flex items-center gap-1.5"
                                >
                                    <Sparkles
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span class="text-[10px] font-medium">{{
                                        $t('Monthly AI pool')
                                    }}</span>
                                </div>
                                <p
                                    class="font-heading text-brand-800 mt-1 text-[18px] font-semibold"
                                >
                                    {{ plan.pointPool.toLocaleString() }}
                                </p>
                            </div>
                        </div>
                        <dl
                            class="text-ink-slate mt-3 grid gap-1 text-[11px] leading-4"
                        >
                            <div class="flex justify-between gap-2">
                                <dt>{{ $t('Extra 1,000 AI points') }}</dt>
                                <dd
                                    class="text-ink-indigo text-end font-semibold"
                                >
                                    {{ formatDzd(plan.extraPointsPriceDzd) }} ·
                                    {{ formatUsd(plan.extraPointsPriceUsd) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>{{ $t('Extra seat / month') }}</dt>
                                <dd
                                    class="text-ink-indigo text-end font-semibold"
                                >
                                    {{ formatDzd(plan.extraSeatPriceDzd) }} ·
                                    {{ formatUsd(plan.extraSeatPriceUsd) }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-ink-muted mt-2 text-[11px] leading-4">
                            {{
                                $t(
                                    ':hotels hotels · :base base points + :shared shared points per seat',
                                    {
                                        hotels: plan.hotelCount,
                                        base: plan.pointsPerEmployee.toLocaleString(),
                                        shared: plan.bonusPointsPerEmployee.toLocaleString(),
                                    },
                                )
                            }}
                        </p>
                        <div class="mt-auto pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="border-line text-brand-700 h-9 w-full gap-2 text-[12px]"
                                @click="editPlan(plan)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                                {{ $t('Customize plan') }}
                            </Button>
                        </div>
                    </PanelCard>
                </div>
            </section>

            <section
                class="grid min-w-0 gap-2.5"
                aria-labelledby="individual-plans-heading"
                data-test="individual-plans"
            >
                <header class="flex min-w-0 items-center gap-2.5">
                    <span
                        class="bg-ai-tint text-ai grid size-8 shrink-0 place-items-center rounded-full"
                    >
                        <UserRound class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2
                            id="individual-plans-heading"
                            class="font-heading text-brand-800 text-base font-semibold"
                        >
                            {{ $t('Individual plans') }}
                            <span class="text-ink-muted ms-1 text-[12px]">{{
                                individualPlans.length
                            }}</span>
                        </h2>
                        <p class="text-ink-slate text-[12px]">
                            {{
                                $t(
                                    'One learner without a hotel, with their own AI points every month. No seats.',
                                )
                            }}
                        </p>
                    </div>
                </header>
                <div
                    v-if="individualPlans.length === 0"
                    class="border-line bg-surface text-ink-slate rounded-lg border border-dashed px-4 py-8 text-center text-[13px]"
                >
                    {{ $t('No individual plans yet.') }}
                </div>
                <div v-else class="grid min-w-0 gap-3 lg:grid-cols-3">
                    <PanelCard
                        v-for="plan in individualPlans"
                        :key="plan.id"
                        :title="plan.name"
                        :title-id="`plan-${plan.id}-heading`"
                        class="min-h-[220px]"
                        body-class="flex flex-col"
                        :data-test="`individual-plan-${plan.id}`"
                    >
                        <template #icon>
                            <span
                                class="bg-ai-tint text-ai grid size-8 place-items-center rounded-full"
                            >
                                <UserRound class="size-4" aria-hidden="true" />
                            </span>
                        </template>
                        <template #actions>
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                :class="
                                    plan.isActive
                                        ? 'bg-success/10 text-success'
                                        : 'bg-ink-faint/20 text-ink-slate'
                                "
                            >
                                {{
                                    plan.isActive
                                        ? $t('Available')
                                        : $t('Inactive')
                                }}
                            </span>
                        </template>

                        <div class="flex items-baseline gap-1.5">
                            <p
                                class="font-heading text-brand-900 text-[24px] leading-7 font-bold"
                            >
                                {{ formatDzd(plan.priceDzd) }}
                            </p>
                            <span class="text-ink-muted text-[11px]">{{
                                $t('/ month')
                            }}</span>
                        </div>
                        <p
                            class="text-ink-slate mt-1 text-[12px] font-semibold"
                        >
                            {{
                                $t('International: :price', {
                                    price: formatUsd(plan.priceUsd),
                                })
                            }}
                            <span class="text-ink-muted font-normal">{{
                                $t('/ month')
                            }}</span>
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="border-line/80 rounded-md border p-2.5">
                                <div
                                    class="text-ink-slate flex items-center gap-1.5"
                                >
                                    <Sparkles
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span class="text-[10px] font-medium">{{
                                        $t('AI points per month')
                                    }}</span>
                                </div>
                                <p
                                    class="font-heading text-brand-800 mt-1 text-[18px] font-semibold"
                                >
                                    {{
                                        plan.pointsPerEmployee.toLocaleString()
                                    }}
                                </p>
                            </div>
                            <div class="border-line/80 rounded-md border p-2.5">
                                <div
                                    class="text-ink-slate flex items-center gap-1.5"
                                >
                                    <Users
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span class="text-[10px] font-medium">{{
                                        $t('Subscribers')
                                    }}</span>
                                </div>
                                <p
                                    class="font-heading text-brand-800 mt-1 text-[18px] font-semibold"
                                >
                                    {{ plan.subscriberCount.toLocaleString() }}
                                </p>
                            </div>
                        </div>
                        <dl
                            class="text-ink-slate mt-3 grid gap-1 text-[11px] leading-4"
                        >
                            <div class="flex justify-between gap-2">
                                <dt>{{ $t('Points per AI action') }}</dt>
                                <dd
                                    class="text-ink-indigo text-end font-semibold"
                                >
                                    {{ plan.aiActionPoints.toLocaleString() }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>{{ $t('Voice points per 10 minutes') }}</dt>
                                <dd
                                    class="text-ink-indigo text-end font-semibold"
                                >
                                    {{
                                        plan.voicePointsPer10Minutes.toLocaleString()
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-auto pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="border-line text-brand-700 h-9 w-full gap-2 text-[12px]"
                                @click="editPlan(plan)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                                {{ $t('Customize plan') }}
                            </Button>
                        </div>
                    </PanelCard>
                </div>
            </section>
        </section>

        <PanelCard
            v-else-if="tab === 'hotels'"
            :title="$t('Hotels and their current plans')"
            title-id="hotel-subscriptions-heading"
            class="min-w-0"
            body-class="mt-2"
        >
            <template #icon>
                <span
                    class="bg-brand-100 text-brand-700 grid size-8 place-items-center rounded-full"
                >
                    <Building2 class="size-4" aria-hidden="true" />
                </span>
            </template>
            <div
                v-if="hotels.length === 0"
                class="border-line rounded-md border border-dashed px-4 py-10 text-center"
            >
                <p class="text-ink-slate text-[13px]">
                    {{ $t('No hotels are available.') }}
                </p>
            </div>
            <div
                v-else
                class="border-line/80 max-h-[62vh] overflow-auto rounded-md border"
            >
                <table class="w-full min-w-[820px] border-collapse text-start">
                    <thead class="bg-app-alt sticky top-0 z-10">
                        <tr
                            class="text-ink-slate text-[10.5px] tracking-wide uppercase"
                        >
                            <th class="px-3 py-2.5 text-start font-semibold">
                                {{ $t('Hotel') }}
                            </th>
                            <th class="px-3 py-2.5 text-start font-semibold">
                                {{ $t('Employees') }}
                            </th>
                            <th class="px-3 py-2.5 text-start font-semibold">
                                {{ $t('Subscription plan') }}
                            </th>
                            <th class="px-3 py-2.5 text-end font-semibold">
                                {{ $t('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-line/80 bg-surface divide-y">
                        <tr
                            v-for="hotel in hotels"
                            :key="hotel.id"
                            class="text-[12px]"
                        >
                            <td class="px-3 py-2.5">
                                <p class="text-brand-900 font-semibold">
                                    {{ hotel.name }}
                                </p>
                                <p class="text-ink-muted mt-0.5 text-[10.5px]">
                                    {{ hotel.city }}
                                </p>
                            </td>
                            <td class="px-3 py-2.5">
                                <p class="text-ink-slate">
                                    {{
                                        $t(':used / :total seats', {
                                            used: hotel.usedEmployees,
                                            total: hotel.employeeLimit,
                                        })
                                    }}
                                </p>
                                <p
                                    v-if="
                                        hotel.usedEmployees >
                                        hotel.employeeLimit
                                    "
                                    class="text-danger-text mt-0.5 text-[10px] font-semibold"
                                >
                                    {{
                                        $t('Over plan by :count seats', {
                                            count:
                                                hotel.usedEmployees -
                                                hotel.employeeLimit,
                                        })
                                    }}
                                </p>
                            </td>
                            <td class="px-3 py-2.5">
                                <select
                                    v-model.number="selectedPlan[hotel.id]"
                                    :aria-label="
                                        $t('Plan for :name', {
                                            name: hotel.name,
                                        })
                                    "
                                    class="border-line bg-surface text-ink-indigo focus-visible:ring-brand-600/40 h-9 min-w-40 rounded-md border px-2 text-[12px] outline-none focus-visible:ring-2"
                                >
                                    <option
                                        v-for="plan in activePlans"
                                        :key="plan.id"
                                        :value="plan.id"
                                    >
                                        {{ plan.name }} ·
                                        {{ formatDzd(plan.priceDzd) }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2.5 text-end">
                                <div class="flex flex-col items-end gap-1.5">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="border-line text-brand-700 h-8 gap-1.5 text-[10px]"
                                        @click="addPaidPoints(hotel)"
                                    >
                                        <Plus
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        {{ $t('Record payment + points') }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="border-line text-brand-700 h-9 min-w-20 text-[11px]"
                                        :disabled="
                                            savingHotel === hotel.id ||
                                            selectedPlan[hotel.id] ===
                                                hotel.planId
                                        "
                                        @click="saveHotelPlan(hotel)"
                                    >
                                        {{
                                            savingHotel === hotel.id
                                                ? $t('Saving…')
                                                : $t('Save plan')
                                        }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </PanelCard>

        <PanelCard
            v-else
            :title="$t('Payment methods')"
            title-id="subscription-payment-methods-heading"
            class="min-w-0"
            body-class="mt-2"
        >
            <template #icon>
                <span
                    class="bg-brand-100 text-brand-700 grid size-8 place-items-center rounded-full"
                >
                    <CreditCard class="size-4" aria-hidden="true" />
                </span>
            </template>
            <template #actions>
                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 h-9 gap-1.5 px-3 text-[11px]"
                    @click="addPaymentMethod"
                >
                    <Plus class="size-3.5" aria-hidden="true" />
                    {{ $t('Add method') }}
                </Button>
            </template>
            <p class="text-ink-slate mb-4 text-[12px] leading-5">
                {{
                    $t(
                        'Add local transfer, wallet, card or other payment instructions. Active methods with account details appear on the public landing page.',
                    )
                }}
            </p>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="method in paymentMethods"
                    :key="method.id"
                    class="border-line/80 bg-app/60 flex min-w-0 flex-col rounded-md border p-3"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3
                                class="text-brand-900 truncate text-[13px] font-semibold"
                            >
                                {{ method.name }}
                            </h3>
                            <p
                                v-if="method.accountReference"
                                class="text-ink-slate mt-1 text-[11px] break-all"
                            >
                                {{ method.accountReference }}
                            </p>
                            <p v-else class="text-ink-muted mt-1 text-[11px]">
                                {{ $t('Account details not set') }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-1 text-[9px] font-semibold"
                            :class="
                                method.isActive
                                    ? 'bg-success/10 text-success'
                                    : 'bg-ink-faint/20 text-ink-slate'
                            "
                        >
                            {{ method.isActive ? $t('Visible') : $t('Hidden') }}
                        </span>
                    </div>
                    <p
                        v-if="method.recipientName"
                        class="text-ink-slate mt-2 text-[10.5px]"
                    >
                        {{
                            $t('Recipient: :name', {
                                name: method.recipientName,
                            })
                        }}
                    </p>
                    <p
                        v-if="method.instructions"
                        class="text-ink-muted mt-1 line-clamp-2 text-[10.5px] leading-4"
                    >
                        {{ method.instructions }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 mt-3 h-8 w-full gap-1.5 text-[11px]"
                        @click="editPaymentMethod(method)"
                    >
                        <Pencil class="size-3" aria-hidden="true" />
                        {{ $t('Customize method') }}
                    </Button>
                </article>
            </div>
        </PanelCard>
    </div>

    <HotelsModal
        v-model:open="editorOpen"
        :title="
            $t('Customize :name plan', {
                name: editing?.name ?? $t('subscription'),
            })
        "
        :description="
            editingIndividual
                ? $t(
                      'The monthly price in DZD and USD, the AI points each subscriber gets every month and the AI point costs. Existing subscribers keep their current allowance.',
                  )
                : $t(
                      'Seat limits, DZD and USD prices (monthly, extra AI points and extra seats), and AI point costs apply to hotels on this plan. Existing employee allocations stay as they are.',
                  )
        "
        class="sm:max-w-[620px]"
    >
        <form class="mt-2 grid gap-3" @submit.prevent="savePlan">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Plan name')
                    }}</span>
                    <input
                        v-model="form.name"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.name" />
                </label>
                <label v-if="!editingIndividual" class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Maximum employees')
                    }}</span>
                    <input
                        v-model.number="form.employee_limit"
                        type="number"
                        min="1"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.employee_limit" />
                </label>
                <label
                    class="grid gap-1.5"
                    :class="editingIndividual ? 'sm:col-span-2' : ''"
                >
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        editingIndividual
                            ? $t('AI points per month')
                            : $t('Base AI points per employee')
                    }}</span>
                    <input
                        v-model.number="form.points_per_employee"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.points_per_employee" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Monthly price (DZD)')
                    }}</span>
                    <input
                        v-model.number="form.price_dzd"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.price_dzd" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Monthly price, international (USD)')
                    }}</span>
                    <input
                        v-model.number="form.price_usd"
                        type="number"
                        min="0"
                        step="0.01"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.price_usd" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('1,000 extra AI points (DZD)')
                    }}</span>
                    <input
                        v-model.number="form.extra_points_price_dzd"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.extra_points_price_dzd" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('1,000 extra AI points (USD)')
                    }}</span>
                    <input
                        v-model.number="form.extra_points_price_usd"
                        type="number"
                        min="0"
                        step="0.01"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.extra_points_price_usd" />
                </label>
                <label v-if="!editingIndividual" class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Extra seat per month (DZD)')
                    }}</span>
                    <input
                        v-model.number="form.extra_seat_price_dzd"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.extra_seat_price_dzd" />
                </label>
                <label v-if="!editingIndividual" class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Extra seat per month (USD)')
                    }}</span>
                    <input
                        v-model.number="form.extra_seat_price_usd"
                        type="number"
                        min="0"
                        step="0.01"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.extra_seat_price_usd" />
                </label>
                <label v-if="!editingIndividual" class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Additional shared points per seat')
                    }}</span>
                    <input
                        v-model.number="form.bonus_points_per_employee"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="form.errors.bonus_points_per_employee"
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Voice points per 10 minutes')
                    }}</span>
                    <input
                        v-model.number="form.voice_points_per_10_minutes"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="form.errors.voice_points_per_10_minutes"
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Points per other AI action')
                    }}</span>
                    <input
                        v-model.number="form.ai_action_points"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.ai_action_points" />
                </label>
            </div>
            <label
                class="text-ink-slate flex items-center gap-2 text-[12px] font-medium"
            >
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="accent-brand-600 border-line size-4 rounded"
                />
                {{
                    editingIndividual
                        ? $t('Available for new individual subscribers')
                        : $t('Available for new hotel subscriptions')
                }}
            </label>
            <InputError :message="form.errors.is_active" />
            <div class="mt-1 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-10 px-4 text-[12px]"
                    @click="editorOpen = false"
                    >{{ $t('Cancel') }}</Button
                >
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 px-4 text-[12px]"
                    :disabled="form.processing"
                >
                    {{ form.processing ? $t('Saving…') : $t('Save plan') }}
                </Button>
            </div>
        </form>
    </HotelsModal>

    <HotelsModal
        v-model:open="pointTopUpOpen"
        :title="$t('Record payment and add AI points')"
        :description="
            pointTopUpHotel
                ? $t(
                      'For :name. Added points are available to this hotel for the current month.',
                      { name: pointTopUpHotel.name },
                  )
                : $t('Add paid AI points to a hotel.')
        "
        class="sm:max-w-[560px]"
    >
        <form class="mt-2 grid gap-3" @submit.prevent="savePaidPoints">
            <div class="grid gap-3 sm:grid-cols-2">
                <fieldset class="grid gap-1.5 sm:col-span-2">
                    <legend
                        class="text-ink-slate mb-1.5 text-[11px] font-semibold"
                    >
                        {{ $t('Customer pays in') }}
                    </legend>
                    <div
                        class="border-line bg-surface inline-flex h-10 rounded-md border p-1"
                        data-test="topup-currency"
                    >
                        <button
                            v-for="currency in ['DZD', 'USD'] as const"
                            :key="currency"
                            type="button"
                            class="focus-visible:ring-brand-600/15 flex-1 rounded px-3 text-[12px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none"
                            :class="
                                pointTopUpForm.currency === currency
                                    ? 'bg-brand-100/70 text-brand-700'
                                    : 'text-ink-slate hover:bg-brand-50'
                            "
                            :aria-pressed="pointTopUpForm.currency === currency"
                            @click="
                                pointTopUpForm.currency = currency;
                                fillSuggestedAmount();
                            "
                        >
                            {{
                                currency === 'DZD'
                                    ? $t('DZD (Algeria)')
                                    : $t('USD (international)')
                            }}
                        </button>
                    </div>
                    <InputError :message="pointTopUpForm.errors.currency" />
                </fieldset>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Points to add this month')
                    }}</span>
                    <input
                        v-model.number="pointTopUpForm.points"
                        type="number"
                        @change="fillSuggestedAmount"
                        min="1"
                        max="100000000"
                        required
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="pointTopUpForm.errors.points" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Payment received (:currency)', {
                            currency: pointTopUpForm.currency,
                        })
                    }}</span>
                    <input
                        v-if="pointTopUpForm.currency === 'DZD'"
                        v-model.number="pointTopUpForm.amount_dzd"
                        type="number"
                        min="1"
                        max="1000000000"
                        required
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <input
                        v-else
                        v-model.number="pointTopUpForm.amount_usd"
                        type="number"
                        min="0.01"
                        step="0.01"
                        max="100000000"
                        required
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <span
                        v-if="pointTopUpPlan && suggestedAmount > 0"
                        class="text-ink-muted text-[10.5px]"
                    >
                        {{
                            $t('Plan price for these points: :price', {
                                price:
                                    pointTopUpForm.currency === 'USD'
                                        ? formatUsd(suggestedAmount)
                                        : formatDzd(suggestedAmount),
                            })
                        }}
                    </span>
                    <InputError
                        :message="
                            pointTopUpForm.errors.amount_dzd ??
                            pointTopUpForm.errors.amount_usd
                        "
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Payment method')
                    }}</span>
                    <select
                        v-model.number="pointTopUpForm.payment_method_id"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    >
                        <option :value="null">
                            {{ $t('Manual / other') }}
                        </option>
                        <option
                            v-for="method in activePaymentMethods"
                            :key="method.id"
                            :value="method.id"
                        >
                            {{ method.name }}
                        </option>
                    </select>
                    <InputError
                        :message="pointTopUpForm.errors.payment_method_id"
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Transaction / receipt reference')
                    }}</span>
                    <input
                        v-model="pointTopUpForm.payment_reference"
                        maxlength="120"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="pointTopUpForm.errors.payment_reference"
                    />
                </label>
            </div>
            <label
                class="text-ink-slate flex items-start gap-2 text-[12px] font-medium"
            >
                <input
                    v-model="pointTopUpForm.payment_received"
                    type="checkbox"
                    required
                    class="accent-brand-600 border-line mt-0.5 size-4 shrink-0 rounded"
                />
                {{ $t("I confirm the hotel's payment has been received.") }}
            </label>
            <InputError :message="pointTopUpForm.errors.payment_received" />
            <div class="mt-1 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-10 px-4 text-[12px]"
                    @click="pointTopUpOpen = false"
                    >{{ $t('Cancel') }}</Button
                >
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 px-4 text-[12px]"
                    :disabled="pointTopUpForm.processing"
                >
                    {{
                        pointTopUpForm.processing
                            ? $t('Recording…')
                            : $t('Confirm payment and add points')
                    }}
                </Button>
            </div>
        </form>
    </HotelsModal>

    <HotelsModal
        v-model:open="paymentMethodEditorOpen"
        :title="
            editingPaymentMethod
                ? $t('Customize payment method')
                : $t('Add payment method')
        "
        :description="
            $t(
                'Set the payment provider, account details, and the instructions shown to hotel subscribers.',
            )
        "
        class="sm:max-w-[620px]"
    >
        <form class="mt-2 grid gap-3" @submit.prevent="savePaymentMethod">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Method name')
                    }}</span>
                    <input
                        v-model="paymentMethodForm.name"
                        :placeholder="
                            $t('BaridiMob, RedotPay, or another method')
                        "
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="paymentMethodForm.errors.name" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Recipient name')
                    }}</span>
                    <input
                        v-model="paymentMethodForm.recipient_name"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="paymentMethodForm.errors.recipient_name"
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Account / payment reference')
                    }}</span>
                    <input
                        v-model="paymentMethodForm.account_reference"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="paymentMethodForm.errors.account_reference"
                    />
                </label>
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Payment instructions')
                    }}</span>
                    <textarea
                        v-model="paymentMethodForm.instructions"
                        rows="3"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 rounded-md border px-3 py-2 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="paymentMethodForm.errors.instructions"
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold">{{
                        $t('Display order')
                    }}</span>
                    <input
                        v-model.number="paymentMethodForm.sort_order"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="paymentMethodForm.errors.sort_order"
                    />
                </label>
            </div>
            <label
                class="text-ink-slate flex items-center gap-2 text-[12px] font-medium"
            >
                <input
                    v-model="paymentMethodForm.is_active"
                    type="checkbox"
                    class="accent-brand-600 border-line size-4 rounded"
                />
                {{ $t('Show this method on the public landing page') }}
            </label>
            <InputError :message="paymentMethodForm.errors.is_active" />
            <div class="mt-1 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-10 px-4 text-[12px]"
                    @click="paymentMethodEditorOpen = false"
                    >{{ $t('Cancel') }}</Button
                >
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 px-4 text-[12px]"
                    :disabled="paymentMethodForm.processing"
                >
                    {{
                        paymentMethodForm.processing
                            ? $t('Saving…')
                            : $t('Save method')
                    }}
                </Button>
            </div>
        </form>
    </HotelsModal>
</template>
