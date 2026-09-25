<script setup lang="ts">
import { Clock, Mail, Users } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import Donut from '@/components/data/Donut.vue';
import {
    capacityTone,
    progressTone,
    quotaText,
    seatPercent,
    statusText,
    statusTone,
} from '@/components/hotels/hotelStatus';
import SolidBuildingIcon from '@/components/icons/SolidBuildingIcon.vue';
import { cn } from '@/lib/utils';
import type { HotelOverview } from '@/types';

type Props = {
    /** Null when the directory has no row to show (AC-18). */
    overview: HotelOverview | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const occupancy = computed(() => {
    const overview = props.overview;

    if (overview === null || overview.totalSeats === 0) {
        return 0;
    }

    // Rounded half up and capped at 100 for display; the over quota pill
    // carries the overflow (spec 0002, Value sourcing).
    return Math.min(
        100,
        Math.round((overview.usedSeats / overview.totalSeats) * 100),
    );
});

const usedSeats = computed(() =>
    props.overview === null
        ? 0
        : Math.min(props.overview.usedSeats, props.overview.totalSeats),
);

const freeSeats = computed(() =>
    props.overview === null
        ? 0
        : Math.max(0, props.overview.totalSeats - props.overview.usedSeats),
);

const overageSeats = computed(() =>
    props.overview === null
        ? 0
        : Math.max(0, props.overview.usedSeats - props.overview.totalSeats),
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
    const overview = props.overview;

    if (overview === null) {
        return '';
    }

    if (overview.status === 'paused') {
        return 'Paused';
    }

    if (overview.status === 'ended') {
        return 'Ended';
    }

    if (overview.status === 'pending') {
        return 'Waiting for approval';
    }

    if (overview.status === 'archived') {
        return 'Archived';
    }

    return `${overview.daysRemaining ?? 0} days left`;
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

            <div
                v-if="overview === null"
                class="border-line/80 rounded-lg border border-dashed px-4 py-8 text-center"
                data-test="hotel-overview-empty"
            >
                <p
                    class="font-heading text-brand-900 text-[14px] font-semibold"
                >
                    No hotel selected
                </p>
                <p class="text-ink-slate mt-1 text-[12.5px] leading-5">
                    Nothing matches the current filters. Clear them, or add a
                    hotel to see its overview here.
                </p>
            </div>

            <template v-else>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p
                            class="font-heading text-brand-900 text-[17px] leading-6 font-semibold"
                        >
                            {{ overview.name }}
                        </p>
                        <p
                            class="text-ink-slate mt-0.5 text-[12.5px] leading-5"
                        >
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

                <div
                    class="border-brand-100 bg-brand-50/60 rounded-lg border p-3"
                >
                    <p
                        class="font-heading text-brand-800 text-[13px] font-semibold"
                    >
                        Contract Health
                    </p>
                    <p class="text-ink-muted mt-1 text-[12.5px] leading-5">
                        {{ daysText }}
                    </p>
                    <ul
                        v-if="overview.alerts.length > 0"
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
                    <div
                        class="bg-tint-header rounded-md px-3 py-2.5 text-center"
                    >
                        <p
                            class="font-heading text-brand-800 text-[18px] font-semibold"
                        >
                            {{ overview.employees }}
                        </p>
                        <p class="text-ink-slate text-[11px] leading-4">
                            Employees
                        </p>
                    </div>
                    <div
                        class="bg-tint-header rounded-md px-3 py-2.5 text-center"
                    >
                        <p
                            class="font-heading text-brand-800 text-[18px] font-semibold"
                        >
                            {{ overview.departments }}
                        </p>
                        <p class="text-ink-slate text-[11px] leading-4">
                            Departments
                        </p>
                    </div>
                    <div
                        class="bg-tint-header rounded-md px-3 py-2.5 text-center"
                    >
                        <p
                            class="font-heading text-brand-800 text-[18px] font-semibold"
                        >
                            {{ occupancy }}%
                        </p>
                        <p class="text-ink-slate text-[11px] leading-4">
                            Seat Use
                        </p>
                    </div>
                </div>

                <div
                    class="border-line/80 bg-surface rounded-lg border px-3 py-3"
                >
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
                        <span
                            v-if="overageSeats > 0"
                            class="text-danger-text text-[11px] font-semibold"
                        >
                            {{ overageSeats }} over
                        </span>
                        <span
                            v-else
                            class="text-success text-[11px] font-semibold"
                        >
                            {{ freeSeats }} free
                        </span>
                    </div>

                    <div class="mt-3 flex justify-center">
                        <Donut
                            :segments="donutSegments"
                            :max="
                                Math.max(
                                    overview.totalSeats,
                                    overview.usedSeats,
                                )
                            "
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
                            <p
                                class="text-ink-slate mt-1 text-[11px] leading-4"
                            >
                                Occupied
                            </p>
                        </Donut>
                    </div>
                </div>
            </template>
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

            <p
                v-if="overview === null || overview.quotas.length === 0"
                class="text-ink-slate border-line/80 rounded-md border border-dashed px-3 py-6 text-center text-[12.5px] leading-5"
                data-test="hotel-quotas-empty"
            >
                {{
                    overview === null
                        ? 'Select a hotel to see its department quotas.'
                        : 'No seat quotas yet. Use Manage seats to allocate departments.'
                }}
            </p>

            <article
                v-for="quota in overview?.quotas ?? []"
                :key="quota.departmentId"
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
                                capacityTone[quota.state],
                            )
                        "
                    >
                        {{ quotaText[quota.state] }}
                    </span>
                </div>

                <div class="mt-2 flex items-center gap-3">
                    <ProgressBar
                        :value="seatPercent(quota.usedSeats, quota.totalSeats)"
                        :tone="progressTone[quota.state]"
                        :label="`${quota.department} seats used`"
                        class="h-[7px] flex-1"
                    />
                    <span class="text-brand-900 text-[11.5px] font-medium">
                        {{ quota.usedSeats }}/{{ quota.totalSeats }}
                    </span>
                </div>
            </article>
        </PanelCard>
    </div>
</template>
