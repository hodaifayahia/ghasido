<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    RotateCcw,
    Search,
    Users,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { ref, watch } from 'vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import HotelRowActions from '@/components/hotels/HotelRowActions.vue';
import {
    capacityText,
    capacityTone,
    progressTone,
    seatPercent,
    statusText,
    statusTone,
} from '@/components/hotels/hotelStatus';
import { useCan } from '@/composables/useCan';
import { useI18n } from '@/composables/useI18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    HotelFilters,
    HotelPagination,
    HotelRecord,
    HotelRowAction,
} from '@/types';

type Props = {
    filters: HotelFilters;
    hotels: HotelRecord[];
    pagination: HotelPagination;
    /** True while a partial reload is in flight, for the skeleton rows. */
    loading?: boolean;
};

const props = withDefaults(defineProps<Props>(), { loading: false });

export type HotelFilterValues = {
    search: string;
    status: string;
    capacity: string;
};

const emit = defineEmits<{
    /** Search or a filter changed: the caller reloads page 1 (AC-11). */
    filter: [values: HotelFilterValues];
    page: [page: number];
    action: [action: HotelRowAction, hotel: HotelRecord];
}>();

const { can } = useCan();
const { t, tc } = useI18n();
const canManage = can('hotels.manage');

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const capacity = ref(props.filters.capacity);

// The server is the source of truth for the filters; keep the controls in
// step when it answers (a Reset, a back button, a shared link).
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        status.value = filters.status;
        capacity.value = filters.capacity;
    },
    { deep: true },
);

function current(): HotelFilterValues {
    return {
        search: search.value,
        status: status.value,
        capacity: capacity.value,
    };
}

// Debounced so a keystroke does not repaint the page (AC-11, directory
// child, Watch out for).
watchDebounced(
    search,
    (value) => {
        if (value !== props.filters.search) {
            emit('filter', current());
        }
    },
    { debounce: 300 },
);

function onSelect(target: 'status' | 'capacity', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'status') {
        status.value = value;
    } else {
        capacity.value = value;
    }

    emit('filter', current());
}

function resetFilters(): void {
    search.value = '';
    status.value = 'all-statuses';
    capacity.value = 'all-capacities';
    emit('filter', current());
}

function daysLabel(hotel: HotelRecord): string {
    if (hotel.status === 'paused') {
        return t('Paused');
    }

    if (hotel.status === 'ended') {
        return t('Ended');
    }

    if (hotel.daysRemaining === null) {
        return '—';
    }

    return tc(':count day|:count days', hotel.daysRemaining);
}

const iconButton =
    'border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex items-center justify-center rounded-md border focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none';

const skeletonRows = [0, 1, 2, 3, 4, 5];
</script>

<template>
    <section
        :aria-label="$t('Hotels directory')"
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
                    :placeholder="$t('Search by hotel, manager or city...')"
                    :aria-label="$t('Search hotels')"
                    data-test="hotels-search-input"
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 md:flex md:items-center">
                <Select
                    :model-value="status"
                    @update:model-value="onSelect('status', $event)"
                >
                    <SelectTrigger
                        :aria-label="$t('Filter by status')"
                        data-test="hotels-status-filter"
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
                        :aria-label="$t('Filter by seat state')"
                        data-test="hotels-capacity-filter"
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
                    data-test="reset-hotel-filters-button"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                    {{ $t('Reset') }}
                </Button>
            </div>
        </div>

        <div
            class="border-line/80 mt-2.5 overflow-hidden rounded-lg border"
            :aria-busy="loading || undefined"
        >
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
                                {{ $t('Hotel') }}
                            </th>
                            <th class="w-[118px] px-2 py-2 text-start">
                                {{ $t('Manager') }}
                            </th>
                            <th class="w-[90px] px-2 py-2 text-start">
                                {{ $t('City') }}
                            </th>
                            <th class="w-[96px] px-2 py-2 text-start">
                                {{ $t('Departments') }}
                            </th>
                            <th class="w-[158px] px-2 py-2 text-start">
                                {{ $t('Seats Used') }}
                            </th>
                            <th class="w-[98px] px-2 py-2 text-start">
                                {{ $t('Contract End') }}
                            </th>
                            <th class="w-[78px] px-2 py-2 text-start">
                                {{ $t('Days Left') }}
                            </th>
                            <th class="w-[108px] px-2 py-2 text-start">
                                {{ $t('Status') }}
                            </th>
                            <th class="w-[108px] px-2 py-2 text-start">
                                {{ $t('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <template v-if="loading && hotels.length === 0">
                            <tr
                                v-for="row in skeletonRows"
                                :key="row"
                                class="border-line/80 border-t"
                            >
                                <td
                                    v-for="cell in 10"
                                    :key="cell"
                                    class="px-2 py-[11px]"
                                >
                                    <div
                                        class="bg-tint-track h-3.5 animate-pulse rounded-sm motion-reduce:animate-none"
                                    />
                                </td>
                            </tr>
                        </template>

                        <tr
                            v-else-if="hotels.length === 0"
                            class="border-line/80 border-t"
                        >
                            <td colspan="10" class="px-4 py-10 text-center">
                                <p
                                    class="font-heading text-brand-900 text-[14px] font-semibold"
                                >
                                    {{ $t('No hotels match these filters') }}
                                </p>
                                <p class="text-ink-slate mt-1 text-[12.5px]">
                                    {{
                                        $t(
                                            'Try another search, or clear the filters to see the whole portfolio.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="border-line text-brand-700 hover:bg-brand-50 mt-3 h-9 gap-1.5 rounded-md px-3 text-[12.5px] font-semibold shadow-none"
                                    @click="resetFilters"
                                >
                                    <RotateCcw
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ $t('Clear filters') }}
                                </Button>
                            </td>
                        </tr>

                        <tr
                            v-for="hotelItem in hotels"
                            v-else
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
                                            $t(
                                                capacityText[
                                                    hotelItem.capacityState
                                                ],
                                            )
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
                                {{
                                    $tc(
                                        ':count dept|:count depts',
                                        hotelItem.departments,
                                    )
                                }}
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
                                        :value="
                                            seatPercent(
                                                hotelItem.usedSeats,
                                                hotelItem.totalSeats,
                                            )
                                        "
                                        :tone="
                                            progressTone[
                                                hotelItem.capacityState
                                            ]
                                        "
                                        :label="
                                            $t(':name seats used', {
                                                name: hotelItem.name,
                                            })
                                        "
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
                                    {{ $t(statusText[hotelItem.status]) }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        :class="cn(iconButton, 'size-6.5')"
                                        :aria-label="
                                            $t('View :name', {
                                                name: hotelItem.name,
                                            })
                                        "
                                        :data-test="`hotel-${hotelItem.id}-view-button`"
                                        @click="
                                            emit('action', 'view', hotelItem)
                                        "
                                    >
                                        <Eye
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        v-if="
                                            canManage &&
                                            hotelItem.accessState !== 'archived'
                                        "
                                        type="button"
                                        :class="cn(iconButton, 'size-6.5')"
                                        :aria-label="
                                            $t('Edit :name', {
                                                name: hotelItem.name,
                                            })
                                        "
                                        :data-test="`hotel-${hotelItem.id}-edit-button`"
                                        @click="
                                            emit('action', 'edit', hotelItem)
                                        "
                                    >
                                        <Pencil
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        v-if="
                                            canManage &&
                                            hotelItem.accessState !== 'archived'
                                        "
                                        type="button"
                                        :class="cn(iconButton, 'size-6.5')"
                                        :aria-label="
                                            $t('Manage seats for :name', {
                                                name: hotelItem.name,
                                            })
                                        "
                                        :data-test="`hotel-${hotelItem.id}-seats-button`"
                                        @click="
                                            emit('action', 'seats', hotelItem)
                                        "
                                    >
                                        <Users
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <HotelRowActions
                                        :hotel="hotelItem"
                                        size="table"
                                        @select="
                                            emit('action', $event, hotelItem)
                                        "
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-if="hotels.length === 0"
                    class="bg-surface px-4 py-10 text-center"
                >
                    <p
                        class="font-heading text-brand-900 text-[15px] font-semibold"
                    >
                        {{ $t('No hotels match these filters') }}
                    </p>
                    <p class="text-ink-slate mt-1 text-[13px]">
                        {{ $t('Try another search, or clear the filters.') }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 mt-3 h-10 gap-1.5 rounded-md px-3 text-[13px] font-semibold shadow-none"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3.5" aria-hidden="true" />
                        {{ $t('Clear filters') }}
                    </Button>
                </li>
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
                            {{ $t(statusText[hotelItem.status]) }}
                        </span>
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('City:')
                            }}</span>
                            {{ hotelItem.city }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Departments:')
                            }}</span>
                            {{ hotelItem.departments }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Contract End:')
                            }}</span>
                            {{ hotelItem.contractEnd }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Days Left:')
                            }}</span>
                            {{ daysLabel(hotelItem) }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-brand-900 text-[13px] font-semibold">
                            {{ hotelItem.usedSeats }}/{{ hotelItem.totalSeats }}
                        </span>
                        <ProgressBar
                            :value="
                                seatPercent(
                                    hotelItem.usedSeats,
                                    hotelItem.totalSeats,
                                )
                            "
                            :tone="progressTone[hotelItem.capacityState]"
                            :label="
                                $t(':name seats used', { name: hotelItem.name })
                            "
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
                            {{ $t(capacityText[hotelItem.capacityState]) }}
                        </span>

                        <div class="ms-auto flex items-center gap-1.5">
                            <button
                                type="button"
                                :class="cn(iconButton, 'size-9')"
                                :aria-label="
                                    $t('View :name', { name: hotelItem.name })
                                "
                                @click="emit('action', 'view', hotelItem)"
                            >
                                <Eye class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                v-if="
                                    canManage &&
                                    hotelItem.accessState !== 'archived'
                                "
                                type="button"
                                :class="cn(iconButton, 'size-9')"
                                :aria-label="
                                    $t('Edit :name', { name: hotelItem.name })
                                "
                                @click="emit('action', 'edit', hotelItem)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                            <HotelRowActions
                                :hotel="hotelItem"
                                size="card"
                                @select="emit('action', $event, hotelItem)"
                            />
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div
            class="text-ink-muted mt-2.5 flex flex-col gap-2 text-[12.5px] leading-5 md:flex-row md:items-center md:justify-between"
        >
            <p>
                {{
                    $t('Showing :from-:to of :total hotels', {
                        from: pagination.from,
                        to: pagination.to,
                        total: pagination.total,
                    })
                }}
            </p>

            <nav
                :aria-label="$t('Hotels pagination')"
                class="flex flex-wrap items-center gap-1.5"
            >
                <button
                    type="button"
                    :disabled="pagination.currentPage <= 1"
                    class="text-ink-muted hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent"
                    @click="emit('page', pagination.currentPage - 1)"
                >
                    <ChevronLeft class="size-3.5" aria-hidden="true" />
                    {{ $t('Previous') }}
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
                        :aria-current="
                            page === pagination.currentPage ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'inline-flex size-8 items-center justify-center rounded-md border text-[12.5px] font-semibold',
                                page === pagination.currentPage
                                    ? 'border-brand-600 bg-brand-600 text-surface shadow-btn'
                                    : 'border-line text-brand-800 hover:bg-brand-50 bg-surface',
                            )
                        "
                        @click="emit('page', page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    :disabled="pagination.currentPage >= pagination.lastPage"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex min-h-8 items-center gap-1 rounded-md border px-2.5 text-[12.5px] font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('page', pagination.currentPage + 1)"
                >
                    {{ $t('Next') }}
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </section>
</template>
