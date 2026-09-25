<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Coins, Frown, Sparkles, Users } from '@lucide/vue';
import PanelCard from '@/components/common/PanelCard.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { aiPoints, dashboard } from '@/routes';
import {
    requestTopUp as requestAiPointTopUp,
    update as updateAllocation,
} from '@/routes/ai-points';

type Employee = {
    id: number;
    name: string;
    username: string;
    department: string;
    status: string;
    allocated: number;
    used: number;
    remaining: number;
};

type Props = {
    hotel: { id: number; name: string };
    plan: {
        name: string;
        employeeLimit: number;
        monthlyPointPool: number;
        paidTopUpPoints: number;
        pointsPerEmployee: number;
        bonusPointsPerEmployee: number;
        voicePointsPer10Minutes: number;
        aiActionPoints: number;
    };
    summary: {
        employees: number;
        allocated: number;
        available: number;
        used: number;
        remaining: number;
    };
    topUpRequestPending: boolean;
    employees: Employee[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'AI Points', href: aiPoints() },
        ],
    },
});

const forms = Object.fromEntries(
    props.employees.map((employee) => [
        employee.id,
        useForm({ ai_points_allocated: employee.allocated }),
    ]),
);
const topUpRequestForm = useForm({});

function save(employee: Employee): void {
    forms[employee.id].patch(updateAllocation(employee.id).url, {
        preserveScroll: true,
    });
}

function requestTopUp(): void {
    topUpRequestForm.post(requestAiPointTopUp().url, { preserveScroll: true });
}

function points(value: number): string {
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(
        value,
    );
}
</script>

<template>
    <Head title="AI Points" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            title="AI Points"
            :description="`Allocate your hotel's monthly AI points across employees · ${hotel.name}`"
        />

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
            <div
                class="border-line bg-surface shadow-card col-span-2 flex min-w-0 items-center gap-3 rounded-lg border p-3 sm:col-span-1"
            >
                <span
                    class="bg-brand-100 text-brand-700 grid size-9 shrink-0 place-items-center rounded-full"
                >
                    <Users class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-ink-muted text-[10px] font-semibold tracking-wide uppercase"
                    >
                        Plan and employees
                    </p>
                    <p
                        class="text-brand-900 mt-0.5 truncate text-[13px] font-semibold"
                    >
                        {{ plan.name }} · {{ summary.employees }} /
                        {{ plan.employeeLimit }}
                    </p>
                </div>
            </div>
            <div
                class="border-line bg-surface shadow-card flex min-w-0 items-center gap-3 rounded-lg border p-3"
            >
                <span
                    class="bg-brand-100 text-brand-700 grid size-9 shrink-0 place-items-center rounded-full"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-ink-muted text-[10px] font-semibold tracking-wide uppercase"
                    >
                        Monthly plan pool
                    </p>
                    <p class="text-brand-900 mt-0.5 text-[13px] font-semibold">
                        {{ points(plan.monthlyPointPool) }} points
                    </p>
                    <p
                        v-if="plan.paidTopUpPoints > 0"
                        class="text-ink-muted mt-0.5 text-[10px]"
                    >
                        Includes {{ points(plan.paidTopUpPoints) }} paid points
                    </p>
                </div>
            </div>
            <div
                class="border-line bg-surface shadow-card flex min-w-0 items-center gap-3 rounded-lg border p-3"
            >
                <span
                    class="bg-brand-100 text-brand-700 grid size-9 shrink-0 place-items-center rounded-full"
                >
                    <Coins class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-ink-muted text-[10px] font-semibold tracking-wide uppercase"
                    >
                        Allocated · available
                    </p>
                    <p
                        class="mt-0.5 text-[13px] font-semibold"
                        :class="
                            summary.available < 0
                                ? 'text-danger-text'
                                : 'text-brand-900'
                        "
                    >
                        {{ points(summary.allocated) }} ·
                        {{ points(summary.available) }} points
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="summary.remaining === 0"
            class="bg-danger-tint text-danger-text flex flex-wrap items-center justify-between gap-3 rounded-md px-3 py-2.5 text-[11px] leading-4"
            role="status"
        >
            <div class="flex min-w-0 items-start gap-2">
                <Frown class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <p>
                    Your hotel's AI points are used up. Ask the platform admin
                    to add points after payment.
                </p>
            </div>
            <Button
                v-if="!topUpRequestPending"
                type="button"
                variant="outline"
                class="border-danger-text text-danger-text h-9 shrink-0 text-[11px]"
                :disabled="topUpRequestForm.processing"
                @click="requestTopUp"
            >
                Request recharge
            </Button>
            <span v-else class="shrink-0 font-semibold">
                Recharge request sent
            </span>
        </div>

        <p
            v-if="summary.available < 0"
            class="bg-danger-tint text-danger-text rounded-md px-3 py-2 text-[11px] leading-4"
            role="status"
        >
            Employee allocations exceed this plan's monthly point pool by
            {{ points(Math.abs(summary.available)) }} points. Reduce allocations
            or ask the platform admin to add points after payment.
        </p>

        <PanelCard
            title="Employee AI budgets"
            title-id="employee-ai-budgets-heading"
            body-class="mt-2"
        >
            <template #icon>
                <span
                    class="bg-brand-100 text-brand-700 grid size-8 place-items-center rounded-full"
                >
                    <Coins class="size-4" aria-hidden="true" />
                </span>
            </template>
            <template #actions>
                <span class="text-ink-muted text-[10.5px]"
                    >{{ points(summary.used) }} used this month</span
                >
            </template>

            <div
                class="bg-app-alt border-line/80 text-ink-slate mb-3 grid gap-1 rounded-md border px-3 py-2.5 text-[11px] leading-4 sm:grid-cols-2"
            >
                <p>
                    Every employee starts with
                    {{ points(plan.pointsPerEmployee) }} points. You can assign
                    unused plan points to employees who need more.
                </p>
                <p class="sm:text-end">
                    Voice: {{ points(plan.voicePointsPer10Minutes) }} points /
                    10 minutes · Other AI actions:
                    {{ points(plan.aiActionPoints) }} points each
                </p>
            </div>

            <div
                v-if="employees.length === 0"
                class="border-line rounded-md border border-dashed px-4 py-10 text-center"
            >
                <p class="text-ink-slate text-[13px]">
                    There are no employee accounts in this hotel yet.
                </p>
            </div>

            <div v-else>
                <ul class="grid max-h-[40svh] gap-2 overflow-y-auto md:hidden">
                    <li
                        v-for="employee in employees"
                        :key="employee.id"
                        class="border-line/80 rounded-md border p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-brand-900 truncate text-[12px] font-semibold"
                                >
                                    {{ employee.name }}
                                </p>
                                <p
                                    class="text-ink-muted mt-0.5 truncate text-[10px]"
                                >
                                    {{ employee.department }} ·
                                    {{ employee.username }}
                                </p>
                            </div>
                            <span class="text-ink-slate shrink-0 text-[10px]"
                                >{{ points(employee.used) }} used</span
                            >
                        </div>
                        <div class="mt-3 flex items-end gap-2">
                            <label class="grid min-w-0 flex-1 gap-1">
                                <span
                                    class="text-ink-slate text-[10px] font-semibold"
                                    >Monthly allocation ({{
                                        points(employee.remaining)
                                    }}
                                    remaining)</span
                                >
                                <input
                                    v-model.number="
                                        forms[employee.id].ai_points_allocated
                                    "
                                    type="number"
                                    min="0"
                                    :aria-label="`Monthly AI points for ${employee.name}`"
                                    class="border-line bg-surface text-ink-indigo focus-visible:ring-brand-600/40 h-10 rounded-md border px-2 text-[12px] outline-none focus-visible:ring-2"
                                />
                            </label>
                            <Button
                                type="button"
                                variant="outline"
                                class="border-line text-brand-700 h-10 min-w-20 text-[11px]"
                                :disabled="
                                    forms[employee.id].processing ||
                                    forms[employee.id].ai_points_allocated ===
                                        employee.allocated
                                "
                                @click="save(employee)"
                            >
                                {{
                                    forms[employee.id].processing
                                        ? 'Saving…'
                                        : 'Save'
                                }}
                            </Button>
                        </div>
                        <InputError
                            :message="
                                forms[employee.id].errors.ai_points_allocated
                            "
                        />
                    </li>
                </ul>
                <div
                    class="border-line/80 hidden max-h-[56svh] overflow-y-auto rounded-md border md:block"
                >
                    <table class="w-full border-collapse text-start">
                        <thead class="bg-app-alt sticky top-0 z-10">
                            <tr
                                class="text-ink-slate text-[10px] tracking-wide uppercase"
                            >
                                <th
                                    class="px-3 py-2.5 text-start font-semibold"
                                >
                                    Employee
                                </th>
                                <th class="px-3 py-2.5 text-end font-semibold">
                                    Used
                                </th>
                                <th class="px-3 py-2.5 text-end font-semibold">
                                    Remaining
                                </th>
                                <th
                                    class="px-3 py-2.5 text-start font-semibold"
                                >
                                    Monthly allocation
                                </th>
                                <th class="px-3 py-2.5 text-end font-semibold">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-line/80 bg-surface divide-y">
                            <tr
                                v-for="employee in employees"
                                :key="employee.id"
                                class="text-[12px]"
                            >
                                <td class="px-3 py-2.5">
                                    <p class="text-brand-900 font-semibold">
                                        {{ employee.name }}
                                    </p>
                                    <p
                                        class="text-ink-muted mt-0.5 text-[10px]"
                                    >
                                        {{ employee.department }} ·
                                        {{ employee.username }}
                                    </p>
                                </td>
                                <td class="text-ink-slate px-3 py-2.5 text-end">
                                    {{ points(employee.used) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-end"
                                    :class="
                                        employee.remaining < 0
                                            ? 'text-danger-text'
                                            : 'text-ink-slate'
                                    "
                                >
                                    {{ points(employee.remaining) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <input
                                            v-model.number="
                                                forms[employee.id]
                                                    .ai_points_allocated
                                            "
                                            type="number"
                                            min="0"
                                            :aria-label="`Monthly AI points for ${employee.name}`"
                                            class="border-line bg-surface text-ink-indigo focus-visible:ring-brand-600/40 h-9 w-28 rounded-md border px-2 text-[12px] outline-none focus-visible:ring-2"
                                        />
                                        <span class="text-ink-muted text-[10px]"
                                            >points</span
                                        >
                                    </div>
                                    <InputError
                                        :message="
                                            forms[employee.id].errors
                                                .ai_points_allocated
                                        "
                                    />
                                </td>
                                <td class="px-3 py-2.5 text-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="border-line text-brand-700 h-9 min-w-20 text-[11px]"
                                        :disabled="
                                            forms[employee.id].processing ||
                                            forms[employee.id]
                                                .ai_points_allocated ===
                                                employee.allocated
                                        "
                                        @click="save(employee)"
                                    >
                                        {{
                                            forms[employee.id].processing
                                                ? 'Saving…'
                                                : 'Save'
                                        }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </PanelCard>
    </div>
</template>
