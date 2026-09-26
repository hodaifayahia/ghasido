<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    Check,
    Clock3,
    Mail,
    MapPin,
    Play,
    UserCheck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import StatCard from '@/components/common/StatCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import {
    statusText,
    statusTone,
    seatPercent,
} from '@/components/hotels/hotelStatus';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { dashboard, hotels } from '@/routes';
import type {
    HotelDetailActivity,
    HotelDetailSummary,
    HotelEmployeeActivity,
    HotelOverview,
} from '@/types';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';

type Props = {
    hotel: HotelOverview;
    summary: HotelDetailSummary;
    activity: HotelDetailActivity;
    employees: HotelEmployeeActivity[];
};

const props = defineProps<Props>();

const { t, tc } = useI18n();

function percentOf(part: number): number {
    return props.summary.totalEmployees === 0
        ? 0
        : Math.round((part / props.summary.totalEmployees) * 100);
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: tk('Dashboard'),
                href: dashboard(),
            },
            {
                title: tk('Hotels'),
                href: hotels(),
            },
        ],
    },
});

type StatDefinition = {
    label: string;
    value: number;
    detail: string;
    tone: 'brand' | 'azure' | 'ai' | 'success' | 'warning';
    icon: Component;
};

const stats = computed<StatDefinition[]>(() => [
    {
        label: t('Total Employees'),
        value: props.summary.totalEmployees,
        detail: tc(
            ':count active account|:count active accounts',
            props.summary.activeAccounts,
        ),
        tone: 'brand',
        icon: Users,
    },
    {
        label: t('Active Users'),
        value: props.summary.activeUsers,
        detail: t('Activity in the last 7 days'),
        tone: 'success',
        icon: UserCheck,
    },
    {
        label: t('Started Training'),
        value: props.summary.startedTraining,
        detail: t(':percent% of employees', {
            percent: percentOf(props.summary.startedTraining),
        }),
        tone: 'azure',
        icon: Play,
    },
    {
        label: t('Completed Training'),
        value: props.summary.completedTraining,
        detail: t(':percent% of employees', {
            percent: percentOf(props.summary.completedTraining),
        }),
        tone: 'success',
        icon: Check,
    },
]);

const occupancy = computed(() =>
    seatPercent(props.hotel.usedSeats, props.hotel.totalSeats),
);

const contractText = computed(() => {
    if (props.hotel.status === 'paused') {
        return t('Paused');
    }

    return `${props.hotel.contractStart} – ${props.hotel.contractEnd}`;
});

const daysText = computed(() => {
    if (props.hotel.status === 'paused') {
        return t('Access is paused');
    }

    if (props.hotel.daysRemaining === null) {
        return t('No end date set');
    }

    if (props.hotel.daysRemaining < 0) {
        return tc(
            ':count day past contract end|:count days past contract end',
            Math.abs(props.hotel.daysRemaining),
        );
    }

    return tc(
        ':count day remaining|:count days remaining',
        props.hotel.daysRemaining,
    );
});

const trainingTone: Record<HotelEmployeeActivity['trainingStatus'], string> = {
    completed: 'bg-success-tint text-success-text',
    in_progress: 'bg-brand-100/70 text-brand-700',
    not_started: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
};

const activityTone: Record<HotelEmployeeActivity['activityStatus'], string> = {
    activeThisWeek: 'bg-success-tint text-success-text',
    activeThisMonth: 'bg-brand-100/70 text-brand-700',
    inactive: 'bg-danger-tint text-danger-text',
};

function employeeProgress(employee: HotelEmployeeActivity): string {
    return `${employee.progress}%`;
}
</script>

<template>
    <Head :title="`${hotel.name} · ${$t('Hotels')}`" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-8 md:px-6">
        <div class="flex min-w-0 items-start justify-between gap-4">
            <PageHeader
                :title="hotel.name"
                :description="
                    $t(':city · Hotel activity and employee progress', {
                        city: hotel.city,
                    })
                "
                class="min-w-0 flex-1"
            >
                <template #accent>
                    <ScriptAccent />
                </template>
            </PageHeader>

            <Link
                :href="hotels()"
                class="border-line bg-surface text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/40 shadow-card inline-flex min-h-10 shrink-0 items-center gap-2 rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
            >
                <ArrowLeft class="size-4 rtl:-scale-x-100" aria-hidden="true" />
                <span class="hidden sm:inline">{{ $t('Back to Hotels') }}</span>
                <span class="sm:hidden">{{ $t('Back') }}</span>
            </Link>
        </div>

        <div class="grid min-w-0 grid-cols-2 gap-2 md:grid-cols-4">
            <StatCard
                v-for="stat in stats"
                :key="stat.label"
                :value="stat.value"
                :label="stat.label"
                :detail="stat.detail"
                :tone="stat.tone"
            >
                <template #icon>
                    <component
                        :is="stat.icon"
                        class="size-5"
                        aria-hidden="true"
                    />
                </template>
            </StatCard>
        </div>

        <div class="grid min-w-0 gap-3 xl:grid-cols-[1fr_1fr]">
            <PanelCard
                :title="$t('Hotel Information')"
                title-id="hotel-information"
            >
                <template #icon>
                    <span
                        class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                    >
                        <Building2 class="size-4.5" aria-hidden="true" />
                    </span>
                </template>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <MapPin
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-ink-muted text-[11px] tracking-[0.08em] uppercase"
                            >
                                {{ $t('Location') }}
                            </p>
                            <p
                                class="text-brand-900 mt-0.5 truncate text-[13px] font-medium"
                            >
                                {{ hotel.city }}
                            </p>
                        </div>
                    </div>
                    <div class="flex min-w-0 items-start gap-2.5">
                        <Users
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-ink-muted text-[11px] tracking-[0.08em] uppercase"
                            >
                                {{ $t('Manager') }}
                            </p>
                            <p
                                class="text-brand-900 mt-0.5 truncate text-[13px] font-medium"
                            >
                                {{ hotel.manager }}
                            </p>
                        </div>
                    </div>
                    <div class="flex min-w-0 items-start gap-2.5">
                        <Mail
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-ink-muted text-[11px] tracking-[0.08em] uppercase"
                            >
                                {{ $t('Manager email') }}
                            </p>
                            <p
                                class="text-brand-900 mt-0.5 truncate text-[13px] font-medium"
                            >
                                {{ hotel.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex min-w-0 items-start gap-2.5">
                        <CalendarDays
                            class="text-brand-700 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-ink-muted text-[11px] tracking-[0.08em] uppercase"
                            >
                                {{ $t('Contract') }}
                            </p>
                            <p
                                class="text-brand-900 mt-0.5 truncate text-[13px] font-medium"
                            >
                                {{ contractText }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="border-line/80 bg-app-alt mt-4 rounded-lg border p-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="font-heading text-brand-800 text-[13px] font-semibold"
                            >
                                {{ $t('Contract status') }}
                            </p>
                            <p class="text-ink-slate mt-0.5 text-[12px]">
                                {{ daysText }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'rounded-pill inline-flex min-h-6 items-center justify-center px-2.5 text-[11px] font-semibold',
                                statusTone[hotel.status],
                            ]"
                        >
                            {{ $t(statusText[hotel.status]) }}
                        </span>
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                :title="$t('Seats & Time Spent')"
                title-id="hotel-capacity-time"
            >
                <template #icon>
                    <span
                        class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                    >
                        <Clock3 class="size-4.5" aria-hidden="true" />
                    </span>
                </template>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p
                                    class="font-heading text-brand-800 text-[24px] leading-7 font-bold"
                                >
                                    {{ hotel.usedSeats }}/{{ hotel.totalSeats }}
                                </p>
                                <p class="text-ink-slate mt-1 text-[12px]">
                                    {{ $t('Employee seats used') }}
                                </p>
                            </div>
                            <span
                                class="text-brand-700 text-[13px] font-semibold"
                                >{{ occupancy }}%</span
                            >
                        </div>
                        <ProgressBar
                            :value="occupancy"
                            :label="
                                $t(':name seat usage', { name: hotel.name })
                            "
                            tone="brand"
                            class="mt-3 h-2.5"
                        />
                    </div>
                    <div class="border-line/80 rounded-lg border p-3">
                        <p
                            class="text-ink-muted text-[11px] tracking-[0.08em] uppercase"
                        >
                            {{ $t('Recorded training time') }}
                        </p>
                        <p
                            class="font-heading text-brand-800 mt-1 text-[23px] leading-7 font-bold"
                        >
                            {{ summary.totalTimeSpent }}
                        </p>
                        <p class="text-ink-slate mt-1 text-[12px]">
                            {{
                                $t(
                                    ':time average per employee with recorded time',
                                    { time: summary.averageTimeSpent },
                                )
                            }}
                        </p>
                    </div>
                </div>

                <p
                    class="text-ink-muted border-line/80 mt-4 border-t pt-3 text-[11.5px] leading-5"
                >
                    {{
                        $t(
                            'Recorded time combines answered activity time and completed AI role-play duration. It does not measure idle browser time.',
                        )
                    }}
                </p>
            </PanelCard>
        </div>

        <PanelCard
            :title="$t('Departments & Seat Allocation')"
            title-id="hotel-departments"
        >
            <template #icon>
                <span
                    class="bg-ai/15 text-ai grid size-8 place-items-center rounded-full"
                >
                    <Building2 class="size-4.5" aria-hidden="true" />
                </span>
            </template>

            <div
                v-if="hotel.quotas.length === 0"
                class="border-line rounded-lg border border-dashed px-4 py-6 text-center"
            >
                <p class="text-ink-slate text-[13px]">
                    {{ $t('No department seat quotas have been configured.') }}
                </p>
            </div>
            <div v-else class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="quota in hotel.quotas"
                    :key="quota.departmentId"
                    class="border-line/80 rounded-lg border p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p
                            class="text-brand-900 truncate text-[13px] font-semibold"
                        >
                            {{ quota.department }}
                        </p>
                        <span
                            class="text-brand-700 shrink-0 text-[11px] font-semibold"
                            >{{ quota.usedSeats }}/{{ quota.totalSeats }}</span
                        >
                    </div>
                    <ProgressBar
                        :value="seatPercent(quota.usedSeats, quota.totalSeats)"
                        :label="
                            $t(':name seats used', { name: quota.department })
                        "
                        :tone="quota.state === 'over' ? 'warning' : 'brand'"
                        class="mt-2 h-1.5"
                    />
                </div>
            </div>
        </PanelCard>

        <PanelCard :title="$t('User Activity')" title-id="hotel-user-activity">
            <template #icon>
                <span
                    class="bg-success-tint text-success grid size-8 place-items-center rounded-full"
                >
                    <UserCheck class="size-4.5" aria-hidden="true" />
                </span>
            </template>

            <div class="grid grid-cols-3 gap-2 sm:max-w-xl">
                <div class="bg-success-tint rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-success-text text-[18px] font-semibold"
                    >
                        {{ activity.activeThisWeek }}
                    </p>
                    <p class="text-success-text/80 text-[11px] leading-4">
                        {{ $t('Active this week') }}
                    </p>
                </div>
                <div class="bg-brand-50 rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ activity.activeThisMonth }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">
                        {{ $t('Active this month') }}
                    </p>
                </div>
                <div class="bg-danger-tint rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-danger-text text-[18px] font-semibold"
                    >
                        {{ activity.inactive }}
                    </p>
                    <p class="text-danger-text/80 text-[11px] leading-4">
                        {{ $t('Inactive') }}
                    </p>
                </div>
            </div>
        </PanelCard>

        <PanelCard
            :title="$t('Employees & Training Progress')"
            title-id="hotel-employees"
        >
            <template #icon>
                <span
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <Users class="size-4.5" aria-hidden="true" />
                </span>
            </template>

            <div
                v-if="employees.length === 0"
                class="border-line rounded-lg border border-dashed px-4 py-10 text-center"
            >
                <p
                    class="font-heading text-brand-900 text-[15px] font-semibold"
                >
                    {{ $t('No employees yet') }}
                </p>
                <p class="text-ink-slate mt-1 text-[13px]">
                    {{ $t('This hotel does not have any employee accounts.') }}
                </p>
            </div>

            <div v-else>
                <div
                    class="border-line hidden overflow-hidden rounded-lg border md:block"
                >
                    <table
                        class="w-full table-fixed border-collapse text-[12px]"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Employee activity and training progress')
                            }}
                        </caption>
                        <colgroup>
                            <col class="w-[17%]" />
                            <col class="w-[13%]" />
                            <col class="w-[17%]" />
                            <col class="w-[20%]" />
                            <col class="w-[14%]" />
                            <col class="w-[11%]" />
                            <col />
                        </colgroup>
                        <thead class="bg-app-alt text-ink/90">
                            <tr class="h-9 text-start">
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('Employee') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('Department') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('What they are doing') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('Progress') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('Last activity') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('Time spent') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 text-start font-medium"
                                >
                                    {{ $t('Activity') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="employee in employees"
                                :key="employee.id"
                                class="border-line h-14 border-t"
                            >
                                <td class="px-2 align-middle">
                                    <p
                                        class="text-brand-900 truncate font-medium"
                                    >
                                        {{ employee.name }}
                                    </p>
                                    <p
                                        class="text-ink-muted truncate text-[11px]"
                                    >
                                        @{{ employee.username }}
                                    </p>
                                </td>
                                <td
                                    class="text-ink-muted truncate px-2 align-middle"
                                >
                                    {{ employee.department }}
                                </td>
                                <td class="px-2 align-middle">
                                    <span
                                        :class="[
                                            'rounded-pill inline-flex min-h-5 items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            trainingTone[
                                                employee.trainingStatus
                                            ],
                                        ]"
                                    >
                                        {{ employee.trainingStatusLabel }}
                                    </span>
                                </td>
                                <td class="px-2 align-middle">
                                    <div class="flex items-center gap-2">
                                        <ProgressBar
                                            :value="employee.progress"
                                            :label="
                                                $t(':name training progress', {
                                                    name: employee.name,
                                                })
                                            "
                                            class="h-1.5 min-w-0 flex-1"
                                        />
                                        <span
                                            class="text-brand-900 w-8 text-end text-[11px] font-semibold"
                                            >{{
                                                employeeProgress(employee)
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        class="text-ink-muted mt-1 text-[10.5px]"
                                    >
                                        {{
                                            $t(':done/:total lessons', {
                                                done: employee.lessonsCompleted,
                                                total: employee.lessonsTotal,
                                            })
                                        }}
                                    </p>
                                </td>
                                <td
                                    class="text-ink-muted px-2 align-middle text-[11px]"
                                >
                                    {{ employee.lastActivity }}
                                </td>
                                <td
                                    class="text-brand-900 px-2 align-middle text-[11px] font-medium"
                                >
                                    {{ employee.timeSpent }}
                                </td>
                                <td class="px-2 align-middle">
                                    <span
                                        :class="[
                                            'rounded-pill inline-flex min-h-5 items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            activityTone[
                                                employee.activityStatus
                                            ],
                                        ]"
                                    >
                                        {{ employee.activityStatusLabel }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul class="divide-line flex flex-col divide-y md:hidden">
                    <li
                        v-for="employee in employees"
                        :key="employee.id"
                        class="py-3 first:pt-0 last:pb-0"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-brand-900 truncate text-[14px] font-semibold"
                                >
                                    {{ employee.name }}
                                </p>
                                <p class="text-ink-muted mt-0.5 text-[12px]">
                                    {{ employee.department }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'rounded-pill inline-flex min-h-5 shrink-0 items-center justify-center px-2 text-[10.5px] font-semibold',
                                    activityTone[employee.activityStatus],
                                ]"
                            >
                                {{ employee.activityStatusLabel }}
                            </span>
                        </div>
                        <div
                            class="mt-3 flex items-center justify-between gap-3"
                        >
                            <span
                                :class="[
                                    'rounded-pill inline-flex min-h-5 items-center justify-center px-2 text-[10.5px] font-semibold',
                                    trainingTone[employee.trainingStatus],
                                ]"
                            >
                                {{ employee.trainingStatusLabel }}
                            </span>
                            <span
                                class="text-brand-900 text-[12px] font-semibold"
                                >{{ employeeProgress(employee) }}</span
                            >
                        </div>
                        <ProgressBar
                            :value="employee.progress"
                            :label="
                                $t(':name training progress', {
                                    name: employee.name,
                                })
                            "
                            class="mt-2 h-2"
                        />
                        <div
                            class="text-ink-muted mt-2 grid grid-cols-2 gap-2 text-[11.5px]"
                        >
                            <span>{{
                                $t('Last activity: :value', {
                                    value: employee.lastActivity,
                                })
                            }}</span>
                            <span class="text-end">{{
                                $t('Time spent: :value', {
                                    value: employee.timeSpent,
                                })
                            }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </PanelCard>
    </div>
</template>
