<script setup lang="ts">
import { Check, Clock, Mail, Pause, Plus, Users } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import Donut from '@/components/data/Donut.vue';
import SolidBuildingIcon from '@/components/icons/SolidBuildingIcon.vue';
import { Button } from '@/components/ui/button';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type { HotelCapacityState, HotelOverview } from '@/types';

type Props = {
    overview: HotelOverview;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const statusText = {
    active: 'Active',
    expiring: 'Expiring Soon',
    paused: 'Paused',
    ended: 'Ended',
} as const;

const statusTone = {
    active: 'bg-success-tint text-success-text',
    expiring: 'bg-warning-tint text-warning-text',
    paused: 'bg-brand-100/70 text-brand-700',
    ended: 'bg-danger-tint text-danger-text',
} as const;

const quotaTone: Record<HotelCapacityState, 'brand' | 'warning'> = {
    available: 'brand',
    full: 'warning',
    over: 'warning',
};

const quotaText: Record<HotelCapacityState, string> = {
    available: 'Available',
    full: 'Full',
    over: 'Over quota',
};

const quotaPillTone: Record<HotelCapacityState, string> = {
    available: 'bg-brand-100/65 text-brand-700',
    full: 'bg-warning-tint text-warning-text',
    over: 'bg-danger-tint text-danger-text',
};

const occupancy = computed(() => {
    if (props.overview.totalSeats === 0) {
        return 0;
    }

    return Math.round(
        (props.overview.usedSeats / props.overview.totalSeats) * 100,
    );
});

const usedSeats = computed(() =>
    Math.min(props.overview.usedSeats, props.overview.totalSeats),
);

const freeSeats = computed(() =>
    Math.max(0, props.overview.totalSeats - props.overview.usedSeats),
);

const overageSeats = computed(() =>
    Math.max(0, props.overview.usedSeats - props.overview.totalSeats),
);

const donutSegments = computed(() => {
    const segments = [
        {
            label: 'Used Seats',
            value: usedSeats.value,
            colorClass: 'text-brand-600',
        },
        {
            label: 'Available Seats',
            value: freeSeats.value,
            colorClass: 'text-success',
        },
    ];

    if (overageSeats.value > 0) {
        segments.push({
            label: 'Over Quota',
            value: overageSeats.value,
            colorClass: 'text-danger',
        });
    }

    return segments;
});

const daysText = computed(() => {
    if (props.overview.status === 'paused') {
        return 'Paused';
    }

    if (props.overview.status === 'ended') {
        return 'Ended';
    }

    return `${props.overview.daysRemaining ?? 0} days left`;
});
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <PanelCard
            title="Hotel Overview"
            title-id="hotels-overview-title"
            body-class="flex flex-col gap-4"
        >
            <template #icon>
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <SolidBuildingIcon
                        class="size-4.5 fill-current"
                        aria-hidden="true"
                    />
                </div>
            </template>

            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p
                        class="font-heading text-brand-900 text-[17px] leading-6 font-semibold"
                    >
                        {{ overview.name }}
                    </p>
                    <p class="text-ink-slate mt-0.5 text-[12.5px] leading-5">
                        {{ overview.city }}
                    </p>
                </div>

                <span
                    :class="
                        cn(
                            'rounded-pill inline-flex min-h-6 min-w-[90px] items-center justify-center px-2.5 text-[11px] font-semibold whitespace-nowrap',
                            statusTone[overview.status],
                        )
                    "
                >
                    {{ statusText[overview.status] }}
                </span>
            </div>

            <div class="text-ink-muted grid gap-2 text-[12.5px] leading-5">
                <p class="flex items-center gap-2">
                    <Users
                        class="text-brand-700 size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="truncate"
                        >Manager: {{ overview.manager }}</span
                    >
                </p>
                <p class="flex items-center gap-2">
                    <Mail
                        class="text-brand-700 size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ overview.email }}</span>
                </p>
                <p class="flex items-center gap-2">
                    <Clock
                        class="text-brand-700 size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        {{ overview.contractStart }} -
                        {{ overview.contractEnd }}
                    </span>
                </p>
            </div>

            <div class="border-brand-100 bg-brand-50/60 rounded-lg border p-3">
                <p
                    class="font-heading text-brand-800 text-[13px] font-semibold"
                >
                    Contract Health
                </p>
                <p class="text-ink-muted mt-1 text-[12.5px] leading-5">
                    {{ daysText }}
                </p>
                <ul
                    class="text-ink-muted mt-2 grid gap-1.5 text-[12px] leading-4.5"
                >
                    <li
                        v-for="alert in overview.alerts"
                        :key="alert"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="bg-brand-600 mt-[5px] size-1.5 shrink-0 rounded-full"
                        />
                        <span>{{ alert }}</span>
                    </li>
                </ul>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ overview.employees }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">
                        Employees
                    </p>
                </div>
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ overview.departments }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">
                        Departments
                    </p>
                </div>
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ occupancy }}%
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">Seat Use</p>
                </div>
            </div>

            <div class="border-line/80 bg-surface rounded-lg border px-3 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p
                            class="font-heading text-brand-800 text-[13px] font-semibold"
                        >
                            Seat Occupancy
                        </p>
                        <p
                            class="text-ink-slate mt-0.5 text-[11.5px] leading-4"
                        >
                            {{ overview.usedSeats }} /
                            {{ overview.totalSeats }} seats used
                        </p>
                    </div>
                    <span class="text-success text-[11px] font-semibold">
                        {{
                            Math.max(
                                0,
                                overview.totalSeats - overview.usedSeats,
                            )
                        }}
                        free
                    </span>
                </div>

                <div class="mt-3 flex justify-center">
                    <Donut
                        :segments="donutSegments"
                        :max="Math.max(overview.totalSeats, overview.usedSeats)"
                        :size="128"
                        :thickness="12"
                        :gap="4"
                        rounded
                        :label="`${overview.name} seat occupancy`"
                    >
                        <p
                            class="font-heading text-brand-800 text-[23px] leading-none font-bold"
                        >
                            {{ occupancy }}%
                        </p>
                        <p class="text-ink-slate mt-1 text-[11px] leading-4">
                            Occupied
                        </p>
                    </Donut>
                </div>
            </div>
        </PanelCard>

        <PanelCard
            title="Department Seat Quotas"
            title-id="hotel-quotas-title"
            body-class="flex flex-col gap-3"
        >
            <template #icon>
                <div
                    class="bg-ai/12 text-ai grid size-8 place-items-center rounded-full"
                >
                    <Users
                        class="size-4.5 fill-current stroke-[1.8]"
                        aria-hidden="true"
                    />
                </div>
            </template>

            <article
                v-for="quota in overview.quotas"
                :key="quota.department"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p
                        class="text-brand-900 min-w-0 truncate text-[12.5px] font-semibold"
                    >
                        {{ quota.department }}
                    </p>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 min-w-[78px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                quotaPillTone[quota.state],
                            )
                        "
                    >
                        {{ quotaText[quota.state] }}
                    </span>
                </div>

                <div class="mt-2 flex items-center gap-3">
                    <ProgressBar
                        :value="
                            Math.min(
                                100,
                                Math.round(
                                    (quota.usedSeats / quota.totalSeats) * 100,
                                ),
                            )
                        "
                        :tone="quotaTone[quota.state]"
                        :label="`${quota.department} seats used`"
                        class="h-[7px] flex-1"
                    />
                    <span class="text-brand-900 text-[11.5px] font-medium">
                        {{ quota.usedSeats }}/{{ quota.totalSeats }}
                    </span>
                </div>
            </article>
        </PanelCard>

        <section
            class="border-line bg-surface shadow-card rounded-lg border px-4 pt-3 pb-3.5"
        >
            <header class="flex items-center gap-2.5">
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <Plus class="size-4.5 stroke-[2.3]" aria-hidden="true" />
                </div>
                <div>
                    <h2
                        class="font-heading text-brand-800 text-[15px] font-semibold"
                    >
                        Quick Actions
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                        Admin actions for contracts and onboarding.
                    </p>
                </div>
            </header>

            <div class="mt-3.5 grid gap-2">
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white"
                    @click="notifyComingSoon('Create hotel')"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Create Hotel
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="notifyComingSoon('Extend contract')"
                >
                    <Check class="size-4" aria-hidden="true" />
                    Extend Contract
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="notifyComingSoon('Pause hotel access')"
                >
                    <Pause class="size-4" aria-hidden="true" />
                    Pause Access
                </Button>
            </div>
        </section>
    </div>
</template>
