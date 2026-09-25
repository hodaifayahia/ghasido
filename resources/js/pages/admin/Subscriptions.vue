<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    Pencil,
    Plus,
    Sparkles,
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

type Plan = {
    id: number;
    name: string;
    slug: string;
    employeeLimit: number;
    priceDzd: number;
    pointsPerEmployee: number;
    bonusPointsPerEmployee: number;
    voicePointsPer10Minutes: number;
    aiActionPoints: number;
    isActive: boolean;
    hotelCount: number;
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
    activePlans: Pick<Plan, 'id' | 'name' | 'employeeLimit' | 'priceDzd'>[];
    hotels: HotelRow[];
    paymentMethods: PaymentMethod[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Subscriptions', href: subscriptions() },
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

const activeHotelCount = computed(() => props.hotels.length);

function formatDzd(value: number): string {
    return `${new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 0 }).format(value)} DZD`;
}

function editPlan(plan: Plan): void {
    editing.value = plan;
    form.name = plan.name;
    form.employee_limit = plan.employeeLimit;
    form.price_dzd = plan.priceDzd;
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
    <Head title="Subscriptions" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            title="Subscriptions"
            description="Set hotel seat limits, monthly DZD prices, and AI point rates."
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div
                class="bg-surface border-line shadow-card inline-flex rounded-md border p-1"
                role="tablist"
                aria-label="Subscription management"
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
                    Plans
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
                    Hotel subscriptions
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
                    Payment methods
                </button>
            </div>
            <p class="text-ink-muted text-[11.5px]">
                Changes are recorded in the audit log.
            </p>
        </div>

        <section
            v-if="tab === 'plans'"
            class="grid min-w-0 gap-3 lg:grid-cols-3"
            role="tabpanel"
            aria-label="Plans"
        >
            <PanelCard
                v-for="plan in plans"
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
                        {{ plan.isActive ? 'Available' : 'Inactive' }}
                    </span>
                </template>

                <div class="flex items-baseline gap-1.5">
                    <p
                        class="font-heading text-brand-900 text-[24px] leading-7 font-bold"
                    >
                        {{ formatDzd(plan.priceDzd) }}
                    </p>
                    <span class="text-ink-muted text-[11px]">/ month</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="border-line/80 rounded-md border p-2.5">
                        <div class="text-ink-slate flex items-center gap-1.5">
                            <Users class="size-3.5" aria-hidden="true" />
                            <span class="text-[10px] font-medium"
                                >Employee seats</span
                            >
                        </div>
                        <p
                            class="font-heading text-brand-800 mt-1 text-[18px] font-semibold"
                        >
                            {{ plan.employeeLimit }}
                        </p>
                    </div>
                    <div class="border-line/80 rounded-md border p-2.5">
                        <div class="text-ink-slate flex items-center gap-1.5">
                            <Sparkles class="size-3.5" aria-hidden="true" />
                            <span class="text-[10px] font-medium"
                                >Monthly AI pool</span
                            >
                        </div>
                        <p
                            class="font-heading text-brand-800 mt-1 text-[18px] font-semibold"
                        >
                            {{ plan.pointPool.toLocaleString() }}
                        </p>
                    </div>
                </div>
                <p class="text-ink-muted mt-3 text-[11px] leading-4">
                    {{ plan.hotelCount }} hotels ·
                    {{ plan.pointsPerEmployee.toLocaleString() }} base points +
                    {{ plan.bonusPointsPerEmployee.toLocaleString() }} shared
                    points per seat
                </p>
                <div class="mt-auto pt-3">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 h-9 w-full gap-2 text-[12px]"
                        @click="editPlan(plan)"
                    >
                        <Pencil class="size-3.5" aria-hidden="true" />
                        Customize plan
                    </Button>
                </div>
            </PanelCard>
        </section>

        <PanelCard
            v-else-if="tab === 'hotels'"
            title="Hotels and their current plans"
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
                    No hotels are available.
                </p>
            </div>
            <div
                v-else
                class="border-line/80 max-h-[62vh] overflow-auto rounded-md border"
            >
                <table class="w-full min-w-[620px] border-collapse text-start">
                    <thead class="bg-app-alt sticky top-0 z-10">
                        <tr
                            class="text-ink-slate text-[10.5px] tracking-wide uppercase"
                        >
                            <th class="px-3 py-2.5 text-start font-semibold">
                                Hotel
                            </th>
                            <th class="px-3 py-2.5 text-start font-semibold">
                                Employees
                            </th>
                            <th class="px-3 py-2.5 text-start font-semibold">
                                Subscription plan
                            </th>
                            <th class="px-3 py-2.5 text-end font-semibold">
                                Action
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
                                    {{ hotel.usedEmployees }} /
                                    {{ hotel.employeeLimit }} seats
                                </p>
                                <p
                                    v-if="
                                        hotel.usedEmployees >
                                        hotel.employeeLimit
                                    "
                                    class="text-danger-text mt-0.5 text-[10px] font-semibold"
                                >
                                    Over plan by
                                    {{
                                        hotel.usedEmployees -
                                        hotel.employeeLimit
                                    }}
                                    seats
                                </p>
                            </td>
                            <td class="px-3 py-2.5">
                                <select
                                    v-model.number="selectedPlan[hotel.id]"
                                    :aria-label="`Plan for ${hotel.name}`"
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
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="border-line text-brand-700 h-9 min-w-20 text-[11px]"
                                    :disabled="
                                        savingHotel === hotel.id ||
                                        selectedPlan[hotel.id] === hotel.planId
                                    "
                                    @click="saveHotelPlan(hotel)"
                                >
                                    {{
                                        savingHotel === hotel.id
                                            ? 'Saving…'
                                            : 'Save plan'
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </PanelCard>

        <PanelCard
            v-else
            title="Payment methods"
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
                    Add method
                </Button>
            </template>
            <p class="text-ink-slate mb-4 text-[12px] leading-5">
                Add local transfer, wallet, card or other payment instructions.
                Active methods with account details appear on the public landing
                page.
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
                                Account details not set
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
                            {{ method.isActive ? 'Visible' : 'Hidden' }}
                        </span>
                    </div>
                    <p
                        v-if="method.recipientName"
                        class="text-ink-slate mt-2 text-[10.5px]"
                    >
                        Recipient: {{ method.recipientName }}
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
                        Customize method
                    </Button>
                </article>
            </div>
        </PanelCard>
    </div>

    <HotelsModal
        v-model:open="editorOpen"
        :title="`Customize ${editing?.name ?? 'subscription'} plan`"
        description="Seat limits, DZD price, and AI point costs apply to hotels on this plan. Existing employee allocations stay as they are."
        class="sm:max-w-[620px]"
    >
        <form class="mt-2 grid gap-3" @submit.prevent="savePlan">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Plan name</span
                    >
                    <input
                        v-model="form.name"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.name" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Maximum employees</span
                    >
                    <input
                        v-model.number="form.employee_limit"
                        type="number"
                        min="1"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.employee_limit" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Monthly price (DZD)</span
                    >
                    <input
                        v-model.number="form.price_dzd"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.price_dzd" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Base AI points per employee</span
                    >
                    <input
                        v-model.number="form.points_per_employee"
                        type="number"
                        min="0"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="form.errors.points_per_employee" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Additional shared points per seat</span
                    >
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
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Voice points per 10 minutes</span
                    >
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
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Points per other AI action</span
                    >
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
                Available for new hotel subscriptions
            </label>
            <InputError :message="form.errors.is_active" />
            <div class="mt-1 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-10 px-4 text-[12px]"
                    @click="editorOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 px-4 text-[12px]"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving…' : 'Save plan' }}
                </Button>
            </div>
        </form>
    </HotelsModal>

    <HotelsModal
        v-model:open="paymentMethodEditorOpen"
        :title="`${editingPaymentMethod ? 'Customize' : 'Add'} payment method`"
        description="Set the payment provider, account details, and the instructions shown to hotel subscribers."
        class="sm:max-w-[620px]"
    >
        <form class="mt-2 grid gap-3" @submit.prevent="savePaymentMethod">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Method name</span
                    >
                    <input
                        v-model="paymentMethodForm.name"
                        placeholder="BaridiMob, RedotPay, or another method"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError :message="paymentMethodForm.errors.name" />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Recipient name</span
                    >
                    <input
                        v-model="paymentMethodForm.recipient_name"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="paymentMethodForm.errors.recipient_name"
                    />
                </label>
                <label class="grid gap-1.5">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Account / payment reference</span
                    >
                    <input
                        v-model="paymentMethodForm.account_reference"
                        class="border-line bg-surface text-ink-indigo focus:ring-brand-600/40 h-10 rounded-md border px-3 text-[13px] outline-none focus:ring-2"
                    />
                    <InputError
                        :message="paymentMethodForm.errors.account_reference"
                    />
                </label>
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Payment instructions</span
                    >
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
                    <span class="text-ink-slate text-[11px] font-semibold"
                        >Display order</span
                    >
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
                Show this method on the public landing page
            </label>
            <InputError :message="paymentMethodForm.errors.is_active" />
            <div class="mt-1 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-10 px-4 text-[12px]"
                    @click="paymentMethodEditorOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 px-4 text-[12px]"
                    :disabled="paymentMethodForm.processing"
                >
                    {{
                        paymentMethodForm.processing ? 'Saving…' : 'Save method'
                    }}
                </Button>
            </div>
        </form>
    </HotelsModal>
</template>
