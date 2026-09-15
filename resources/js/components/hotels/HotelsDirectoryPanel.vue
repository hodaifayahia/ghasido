<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    Pencil,
    RotateCcw,
    Search,
    Users,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type {
    HotelCapacityState,
    HotelContractStatus,
    HotelFilters,
    HotelPagination,
    HotelRecord,
} from '@/types';

type Props = {
    filters: HotelFilters;
    hotels: HotelRecord[];
    pagination: HotelPagination;
};

const props = defineProps<Props>();

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const capacity = ref(props.filters.capacity);

const statusText: Record<HotelContractStatus, string> = {
    active: 'Active',
    expiring: 'Expiring Soon',
    paused: 'Paused',
    ended: 'Ended',
};

const statusTone: Record<HotelContractStatus, string> = {
    active: 'bg-success-tint text-success-text',
    expiring: 'bg-warning-tint text-warning-text',
    paused: 'bg-brand-100/70 text-brand-700',
    ended: 'bg-danger-tint text-danger-text',
};

const capacityText: Record<HotelCapacityState, string> = {
    available: 'Seats Available',
    full: 'At Capacity',
    over: 'Over Quota',
};

const capacityTone: Record<HotelCapacityState, string> = {
    available: 'bg-brand-100/65 text-brand-700',
    full: 'bg-warning-tint text-warning-text',
    over: 'bg-danger-tint text-danger-text',
};

const progressTone: Record<HotelCapacityState, 'brand' | 'warning'> = {
    available: 'brand',
    full: 'warning',
    over: 'warning',
};

function onSelect(target: 'status' | 'capacity', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'status') {
        status.value = value;
        return;
    }

    capacity.value = value;
}

function resetFilters(): void {
    search.value = props.filters.search;
    status.value = props.filters.status;
    capacity.value = props.filters.capacity;
}

function seatPercent(hotel: HotelRecord): number {
    if (hotel.totalSeats === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((hotel.usedSeats / hotel.totalSeats) * 100),
    );
}

function daysLabel(hotel: HotelRecord): string {
    if (hotel.status === 'paused') {
        return 'Paused';
    }

    if (hotel.status === 'ended') {
        return 'Ended';
    }

    return `${hotel.daysRemaining ?? 0} days`;
}
</script>

<template>
    <section
        aria-label="Hotels directory"
        class="border-line bg-surface shadow-card rounded-lg border p-2.5"
    >
        <div class="flex flex-col gap-2 md:flex-row md:items-center">
            <div class="relative min-w-0 flex-1">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search by hotel, manager or city..."
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 md:flex md:items-center">
                <Select
                    :model-value="status"
                    @update:model-value="onSelect('status', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-[148px] rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.statuses"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="capacity"
                    @update:model-value="onSelect('capacity', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-[148px] rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.capacities"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12.5px] font-semibold shadow-none"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                    Reset
                </Button>
            </div>
        </div>

        <div class="border-line/80 mt-2.5 overflow-hidden rounded-lg border">
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-10 py-2 ps-3 pe-2 text-start">#</th>
                            <th class="w-[156px] px-2 py-2 text-start">
                                Hotel
                            </th>
                            <th class="w-[118px] px-2 py-2 text-start">
                                Manager
                            </th>
                            <th class="w-[90px] px-2 py-2 text-start">City</th>
                            <th class="w-[96px] px-2 py-2 text-start">
                                Departments
                            </th>
                            <th class="w-[158px] px-2 py-2 text-start">
                                Seats Used
                            </th>
                            <th class="w-[98px] px-2 py-2 text-start">
                                Contract End
                            </th>
                            <th class="w-[78px] px-2 py-2 text-start">
                                Days Left
                            </th>
                            <th class="w-[108px] px-2 py-2 text-start">
                                Status
                            </th>
                            <th class="w-[108px] px-2 py-2 text-start">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <tr
                            v-for="hotelItem in hotels"
                            :key="hotelItem.id"
                            class="border-line/80 hover:bg-brand-50/35 border-t"
                        >
                            <td
                                class="text-ink-muted py-[7px] ps-3 pe-2 align-middle"
                            >
                                {{ hotelItem.rank }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ hotelItem.name }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11.5px]"
                                    >
                                        {{
                                            capacityText[
                                                hotelItem.capacityState
                                            ]
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                <span class="block truncate">{{
                                    hotelItem.manager
                                }}</span>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ hotelItem.city }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ hotelItem.departments }} depts
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-brand-900 w-14 shrink-0 text-[11.5px] font-medium"
                                    >
                                        {{ hotelItem.usedSeats }}/{{
                                            hotelItem.totalSeats
                                        }}
                                    </span>
                                    <ProgressBar
                                        :value="seatPercent(hotelItem)"
                                        :tone="
                                            progressTone[
                                                hotelItem.capacityState
                                            ]
                                        "
                                        :label="`${hotelItem.name} seats used`"
                                        class="h-[6px] w-[70px]"
                                    />
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle text-[11.5px]"
                            >
                                {{ hotelItem.contractEnd }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle text-[11.5px]"
                            >
                                {{ daysLabel(hotelItem) }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[90px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            statusTone[hotelItem.status],
                                        )
                                    "
                                >
                                    {{ statusText[hotelItem.status] }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`View ${hotelItem.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${hotelItem.name} details`,
                                            )
                                        "
                                    >
                                        <Eye
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`Edit ${hotelItem.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${hotelItem.name} settings`,
                                            )
                                        "
                                    >
                                        <Pencil
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`Manage seats for ${hotelItem.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${hotelItem.name} seat quotas`,
                                            )
                                        "
                                    >
                                        <Users
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 items-center justify-center rounded-md border"
                                        :aria-label="`More actions for ${hotelItem.name}`"
                                        @click="
                                            notifyComingSoon(
                                                `${hotelItem.name} actions`,
                                            )
                                        "
                                    >
                                        <EllipsisVertical
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-for="hotelItem in hotels"
                    :key="hotelItem.id"
                    class="bg-surface p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="font-heading text-brand-800 text-[15px] leading-5 font-semibold"
                            >
                                {{ hotelItem.name }}
                            </p>
                            <p
                                class="text-ink-muted mt-0.5 text-[13px] leading-5"
                            >
                                {{ hotelItem.manager }}
                            </p>
                        </div>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center justify-center px-2.5 text-[11px] font-semibold',
                                    statusTone[hotelItem.status],
                                )
                            "
                        >
                            {{ statusText[hotelItem.status] }}
                        </span>
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium"
                                >City:</span
                            >
                            {{ hotelItem.city }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Departments:</span
                            >
                            {{ hotelItem.departments }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Contract End:</span
                            >
                            {{ hotelItem.contractEnd }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Days Left:</span
                            >
                            {{ daysLabel(hotelItem) }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-brand-900 text-[13px] font-semibold">
                            {{ hotelItem.usedSeats }}/{{ hotelItem.totalSeats }}
                        </span>
                        <ProgressBar
                            :value="seatPercent(hotelItem)"
                            :tone="progressTone[hotelItem.capacityState]"
                            :label="`${hotelItem.name} seats used`"
                            class="h-2 flex-1"
                        />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center justify-center px-2.5 text-[11px] font-semibold',
                                    capacityTone[hotelItem.capacityState],
                                )
                            "
                        >
                            {{ capacityText[hotelItem.capacityState] }}
                        </span>

                        <div class="ms-auto flex items-center gap-1.5">
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`View ${hotelItem.name}`"
                                @click="
                                    notifyComingSoon(
                                        `${hotelItem.name} details`,
                                    )
                                "
                            >
                                <Eye class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`Edit ${hotelItem.name}`"
                                @click="
                                    notifyComingSoon(
                                        `${hotelItem.name} settings`,
                                    )
                                "
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-9 items-center justify-center rounded-md border"
                                :aria-label="`More actions for ${hotelItem.name}`"
                                @click="
                                    notifyComingSoon(
                                        `${hotelItem.name} actions`,
                                    )
                                "
                            >
                                <EllipsisVertical
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div
            class="text-ink-muted mt-2.5 flex flex-col gap-2 text-[12.5px] leading-5 md:flex-row md:items-center md:justify-between"
        >
            <p>
                Showing {{ pagination.from }}-{{ pagination.to }} of
                {{ pagination.total }} hotels
            </p>

            <nav
                aria-label="Hotels pagination"
                class="flex flex-wrap items-center gap-1.5"
            >
                <button
                    type="button"
                    class="text-ink-muted hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2"
                >
                    <ChevronLeft class="size-3.5" aria-hidden="true" />
                    Previous
                </button>

                <template v-for="page in pagination.pages" :key="String(page)">
                    <span
                        v-if="page === 'ellipsis'"
                        class="text-ink-muted inline-flex min-w-8 justify-center px-1"
                    >
                        ...
                    </span>
                    <button
                        v-else
                        type="button"
                        :class="
                            cn(
                                'inline-flex size-8 items-center justify-center rounded-md border text-[12.5px] font-semibold',
                                page === pagination.currentPage
                                    ? 'border-brand-600 bg-brand-600 text-surface shadow-btn'
                                    : 'border-line text-brand-800 hover:bg-brand-50 bg-surface',
                            )
                        "
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex min-h-8 items-center gap-1 rounded-md border px-2.5 text-[12.5px] font-semibold"
                >
                    Next
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </section>
</template>
