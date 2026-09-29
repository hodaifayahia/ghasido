<script setup lang="ts">
import {
    Bot,
    MicOff,
    Pencil,
    Plus,
    Power,
    Search,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { intlLocale, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    IndividualFilters,
    IndividualPagination,
    IndividualRow,
    IndividualWindowState,
} from '@/types';

/**
 * The individual subscribers list: a table on desktop, stacked cards on a
 * phone (RESP-01). Each row shows the access window, the AI allowance left
 * this month and the switches of their own configuration.
 */
type Props = {
    rows: IndividualRow[];
    filters: IndividualFilters;
    pagination: IndividualPagination;
    canManage: boolean;
};

const props = defineProps<Props>();

const { t, tc } = useI18n();

const emit = defineEmits<{
    add: [];
    edit: [row: IndividualRow];
    toggle: [row: IndividualRow];
    delete: [row: IndividualRow];
    filter: [filters: IndividualFilters];
    page: [page: number];
}>();

const search = ref(props.filters.search);
const state = ref(props.filters.state);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        state.value = filters.state;
    },
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function onSearch(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        emit('filter', { search: search.value.trim(), state: state.value });
    }, 350);
}

function onState(value: string): void {
    state.value = value;
    emit('filter', { search: search.value.trim(), state: value });
}

const windowTone: Record<IndividualWindowState, string> = {
    active: 'bg-success-tint text-success-text',
    upcoming: 'bg-brand-50 text-brand-700',
    ended: 'bg-danger-tint text-danger-text',
};

const windowLabel: Record<IndividualWindowState, string> = {
    active: tk('Active'),
    upcoming: tk('Starts later'),
    ended: tk('Ended'),
};

function formatDate(value: string | null): string {
    if (value === null) {
        return t('No end date');
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString(intlLocale(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function accessLine(row: IndividualRow): string {
    if (row.windowState === 'upcoming') {
        return t('From :date', { date: formatDate(row.startsOn) });
    }

    if (row.endsOn === null) {
        return t('No end date');
    }

    return row.windowState === 'ended'
        ? t('Ended :date', { date: formatDate(row.endsOn) })
        : tc(
              'Until :date · :count day left|Until :date · :count days left',
              row.daysRemaining ?? 0,
              {
                  date: formatDate(row.endsOn),
              },
          );
}

function pointsPercent(row: IndividualRow): number {
    if (row.aiPoints <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((row.aiPointsUsed / row.aiPoints) * 100));
}

const states = [
    { value: 'all', label: tk('All') },
    { value: 'inactive', label: tk('Inactive') },
    { value: 'ended', label: tk('Ended') },
];
</script>

<template>
    <PanelCard
        :title="$t('Individual subscribers')"
        title-id="individual-subscribers-title"
        body-class="-mx-4 -mb-4 mt-3"
    >
        <template #actions>
            <Button
                v-if="canManage"
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                data-test="add-individual-button"
                @click="emit('add')"
            >
                <Plus class="size-4" aria-hidden="true" />
                <span class="hidden sm:inline">{{ $t('Add subscriber') }}</span>
                <span class="sm:hidden">{{ $t('Add') }}</span>
            </Button>
        </template>

        <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
            <label class="relative min-w-0 flex-1 sm:max-w-xs">
                <span class="sr-only">{{
                    $t('Search individual subscribers')
                }}</span>
                <Search
                    class="text-ink-faint pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('Search name, username or email')"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-md border ps-9 pe-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                    @input="onSearch"
                />
            </label>
            <div
                class="border-line bg-app flex rounded-md border p-0.5"
                role="group"
                :aria-label="$t('Filter by state')"
            >
                <button
                    v-for="option in states"
                    :key="option.value"
                    type="button"
                    :aria-pressed="state === option.value"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600 min-h-9 rounded-[8px] px-3 text-[12px] font-semibold focus-visible:ring-2 focus-visible:outline-none',
                            state === option.value
                                ? 'bg-surface text-brand-700 shadow-card'
                                : 'text-ink-slate hover:text-brand-700',
                        )
                    "
                    @click="onState(option.value)"
                >
                    {{ $t(option.label) }}
                </button>
            </div>
        </div>

        <!-- Desktop / tablet: the table -->
        <div class="hidden min-w-0 overflow-hidden rounded-b-lg md:block">
            <table
                class="w-full table-fixed border-collapse text-start text-[13px]"
            >
                <caption class="sr-only">
                    {{
                        $t(
                            'Individual subscribers, their access period and AI allowance',
                        )
                    }}
                </caption>
                <colgroup>
                    <col class="w-[25%]" />
                    <col class="w-[15%]" />
                    <col class="w-[21%]" />
                    <col class="w-[19%]" />
                    <col class="w-[20%]" />
                </colgroup>
                <thead
                    class="bg-app text-ink-slate text-[11px] tracking-wide uppercase"
                >
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-start">
                            {{ $t('Subscriber') }}
                        </th>
                        <th scope="col" class="px-4 py-2.5 text-start">
                            {{ $t('Department') }}
                        </th>
                        <th scope="col" class="px-4 py-2.5 text-start">
                            {{ $t('Access') }}
                        </th>
                        <th scope="col" class="px-4 py-2.5 text-start">
                            {{ $t('AI this month') }}
                        </th>
                        <th scope="col" class="px-4 py-2.5 text-end">
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="border-line text-ink border-t align-top"
                        :data-test="`individual-row-${row.id}`"
                    >
                        <th
                            scope="row"
                            class="px-4 py-3 text-start font-normal"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="bg-brand-50 text-brand-600 grid size-8 shrink-0 place-items-center rounded-lg"
                                >
                                    <UserRound
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="font-heading block truncate text-[13px] font-semibold"
                                        >{{ row.name }}</span
                                    >
                                    <span
                                        class="text-ink-slate block truncate text-[11px]"
                                        >@{{ row.username }}</span
                                    >
                                </span>
                            </div>
                        </th>
                        <td class="px-4 py-3">
                            <span
                                class="text-brand-800 block truncate font-medium"
                            >
                                {{ row.department }}
                            </span>
                            <span
                                v-if="row.status === 'inactive'"
                                class="bg-app text-ink-slate mt-1 inline-flex rounded-[5px] px-2 py-0.5 text-[10px] font-semibold"
                            >
                                {{ $t('Inactive') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="
                                    cn(
                                        'inline-flex rounded-[5px] px-2 py-0.5 text-[10.5px] font-semibold',
                                        windowTone[row.windowState],
                                    )
                                "
                            >
                                {{ $t(windowLabel[row.windowState]) }}
                            </span>
                            <span
                                class="text-ink-slate mt-1 block text-[11.5px]"
                            >
                                {{ accessLine(row) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="row.aiEnabled">
                                <span
                                    class="text-brand-900 block text-[12.5px] font-semibold"
                                >
                                    {{ row.aiPointsLeft.toLocaleString('en') }}
                                    <span class="text-ink-slate font-normal">{{
                                        $t('/ :total pts left', {
                                            total: row.aiPoints.toLocaleString(
                                                'en',
                                            ),
                                        })
                                    }}</span>
                                </span>
                                <span
                                    class="bg-tint-track mt-1.5 block h-1.5 w-full max-w-[160px] overflow-hidden rounded-full"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="bg-brand-600 block h-full rounded-full"
                                        :style="{
                                            width: `${pointsPercent(row)}%`,
                                        }"
                                    />
                                </span>
                                <span
                                    v-if="!row.voiceEnabled"
                                    class="text-ink-slate mt-1 inline-flex items-center gap-1 text-[11px]"
                                >
                                    <MicOff class="size-3" aria-hidden="true" />
                                    {{ $t('Voice off') }}
                                </span>
                            </template>
                            <span
                                v-else
                                class="bg-app text-ink-slate inline-flex items-center gap-1 rounded-[5px] px-2 py-0.5 text-[10.5px] font-semibold"
                            >
                                <Bot class="size-3" aria-hidden="true" />
                                {{ $t('AI not included') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <div
                                v-if="canManage"
                                class="flex flex-wrap justify-end gap-1.5"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="border-line text-ink h-9 gap-1.5 rounded-md px-2.5"
                                    :aria-label="
                                        $t('Edit :name', { name: row.name })
                                    "
                                    :data-test="`edit-individual-${row.id}`"
                                    @click="emit('edit', row)"
                                >
                                    <Pencil
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ $t('Edit') }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :class="
                                        cn(
                                            'border-line size-9 rounded-md p-0',
                                            row.status === 'active'
                                                ? 'text-danger-text hover:bg-danger-tint'
                                                : 'text-success-text hover:bg-success-tint',
                                        )
                                    "
                                    :aria-label="
                                        row.status === 'active'
                                            ? $t('Deactivate :name', {
                                                  name: row.name,
                                              })
                                            : $t('Activate :name', {
                                                  name: row.name,
                                              })
                                    "
                                    :title="
                                        row.status === 'active'
                                            ? $t('Deactivate')
                                            : $t('Activate')
                                    "
                                    @click="emit('toggle', row)"
                                >
                                    <Power class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="border-danger text-danger-text hover:bg-danger-tint bg-surface size-9 rounded-md p-0"
                                    :aria-label="
                                        $t('Delete :name', { name: row.name })
                                    "
                                    :title="$t('Delete')"
                                    :data-test="`delete-individual-${row.id}`"
                                    @click="emit('delete', row)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td
                            colspan="5"
                            class="text-ink-slate px-4 py-10 text-center"
                        >
                            {{
                                $t(
                                    'No individual subscribers yet. Add one to give a learner access without a hotel.',
                                )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phone: stacked cards -->
        <ul class="grid gap-2 px-4 pb-4 md:hidden">
            <li
                v-for="row in rows"
                :key="row.id"
                class="border-line bg-surface grid gap-2 rounded-md border p-3"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p
                            class="font-heading text-brand-900 truncate text-[13.5px] font-semibold"
                        >
                            {{ row.name }}
                        </p>
                        <p class="text-ink-slate truncate text-[11.5px]">
                            @{{ row.username }} · {{ row.department }}
                        </p>
                    </div>
                    <span
                        :class="
                            cn(
                                'inline-flex shrink-0 rounded-[5px] px-2 py-0.5 text-[10.5px] font-semibold',
                                row.status === 'inactive'
                                    ? 'bg-app text-ink-slate'
                                    : windowTone[row.windowState],
                            )
                        "
                    >
                        {{
                            row.status === 'inactive'
                                ? $t('Inactive')
                                : $t(windowLabel[row.windowState])
                        }}
                    </span>
                </div>
                <p class="text-ink-slate text-[12px]">{{ accessLine(row) }}</p>
                <p class="text-ink-slate text-[12px]">
                    <template v-if="row.aiEnabled">
                        {{
                            row.voiceEnabled
                                ? $t('AI: :left / :total points left', {
                                      left: row.aiPointsLeft.toLocaleString(
                                          'en',
                                      ),
                                      total: row.aiPoints.toLocaleString('en'),
                                  })
                                : $t(
                                      'AI: :left / :total points left · voice off',
                                      {
                                          left: row.aiPointsLeft.toLocaleString(
                                              'en',
                                          ),
                                          total: row.aiPoints.toLocaleString(
                                              'en',
                                          ),
                                      },
                                  )
                        }}
                    </template>
                    <template v-else>{{ $t('AI not included') }}</template>
                </p>
                <!-- Three actions share one row down to 360px: icon over a
                     short label, each at least 44px tall (ACC-03). -->
                <div v-if="canManage" class="grid grid-cols-3 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-ink h-auto min-h-11 min-w-0 flex-col gap-1 rounded-md px-1 py-1.5 text-[11.5px] leading-tight whitespace-normal has-[>svg]:px-1"
                        @click="emit('edit', row)"
                    >
                        <Pencil class="size-3.5" aria-hidden="true" />
                        {{ $t('Edit') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :class="
                            cn(
                                'border-line h-auto min-h-11 min-w-0 flex-col gap-1 rounded-md px-1 py-1.5 text-[11.5px] leading-tight whitespace-normal has-[>svg]:px-1',
                                row.status === 'active'
                                    ? 'text-danger-text hover:bg-danger-tint'
                                    : 'text-success-text hover:bg-success-tint',
                            )
                        "
                        @click="emit('toggle', row)"
                    >
                        <Power class="size-4" aria-hidden="true" />
                        {{
                            row.status === 'active'
                                ? $t('Deactivate')
                                : $t('Activate')
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-danger text-danger-text hover:bg-danger-tint bg-surface h-auto min-h-11 min-w-0 flex-col gap-1 rounded-md px-1 py-1.5 text-[11.5px] leading-tight whitespace-normal has-[>svg]:px-1"
                        :aria-label="$t('Delete :name', { name: row.name })"
                        :data-test="`delete-individual-${row.id}-card`"
                        @click="emit('delete', row)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        {{ $t('Delete') }}
                    </Button>
                </div>
            </li>
            <li
                v-if="rows.length === 0"
                class="text-ink-slate px-2 py-8 text-center text-[13px]"
            >
                {{ $t('No individual subscribers yet.') }}
            </li>
        </ul>

        <nav
            v-if="pagination.lastPage > 1"
            :aria-label="$t('Subscriber pages')"
            class="border-line flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-[12px]"
        >
            <span class="text-ink-slate">
                {{
                    $t(':from–:to of :total', {
                        from: pagination.from,
                        to: pagination.to,
                        total: pagination.total,
                    })
                }}
            </span>
            <div class="flex gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-9 rounded-md px-3 text-[12px]"
                    :disabled="pagination.currentPage <= 1"
                    @click="emit('page', pagination.currentPage - 1)"
                >
                    {{ $t('Previous') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-9 rounded-md px-3 text-[12px]"
                    :disabled="pagination.currentPage >= pagination.lastPage"
                    @click="emit('page', pagination.currentPage + 1)"
                >
                    {{ $t('Next') }}
                </Button>
            </div>
        </nav>
    </PanelCard>
</template>
