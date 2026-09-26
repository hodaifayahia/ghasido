<script setup lang="ts">
import {
    ArrowRight,
    Bot,
    Check,
    CircleHelp,
    Filter,
    RotateCcw,
    Search,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
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
import type { AiScenarioLibrary, AiScenarioLibraryItem } from '@/types';

const props = defineProps<{
    library: AiScenarioLibrary;
}>();

const emit = defineEmits<{
    open: [id: string];
    create: [];
    tutorial: [];
}>();

const search = ref(props.library.search);
const department = ref(props.library.department);
const status = ref(props.library.status);

watch(
    () => props.library,
    (library) => {
        search.value = library.search;
        department.value = library.department;
        status.value = library.status;
    },
    { deep: true },
);

function updateFilter(
    target: 'department' | 'status',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    status.value = value;
}

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        department.value !== 'all-departments' ||
        status.value !== 'all-statuses',
);

const filteredScenarios = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    const departmentOption = props.library.departments.find(
        (option) => option.value === department.value,
    );
    const departmentLabel = (
        departmentOption?.label ?? department.value
    ).toLocaleLowerCase();

    return props.library.scenarios.filter((scenario) => {
        const searchable =
            `${scenario.title} ${scenario.department} ${scenario.level}`.toLocaleLowerCase();
        const scenarioDepartment = scenario.department.toLocaleLowerCase();

        return (
            (term === '' || searchable.includes(term)) &&
            (department.value === 'all-departments' ||
                scenarioDepartment === departmentLabel ||
                scenarioDepartment === department.value.toLocaleLowerCase()) &&
            (status.value === 'all-statuses' ||
                scenario.status.toLocaleLowerCase() ===
                    status.value.toLocaleLowerCase())
        );
    });
});

const visibleCountLabel = computed(() => {
    const count = filteredScenarios.value.length;

    return `${count} visible scenario${count === 1 ? '' : 's'}`;
});

function resetFilters(): void {
    search.value = '';
    department.value = 'all-departments';
    status.value = 'all-statuses';
}

function statusClass(scenario: AiScenarioLibraryItem): string {
    return scenario.status === 'published'
        ? 'bg-success-tint text-success-text'
        : 'bg-warning-tint text-warning-text';
}
</script>

<template>
    <PanelCard
        title="Scenario Library"
        title-id="scenario-library-title"
        class="overflow-hidden px-0 pt-0 pb-0"
        body-class="mt-0"
    >
        <template #icon>
            <div
                class="bg-azure/20 text-brand-600 grid size-8 place-items-center rounded-md"
            >
                <Filter class="size-4" aria-hidden="true" />
            </div>
        </template>
        <template #actions>
            <span class="text-ink-muted text-xs font-medium" aria-live="polite">
                {{ visibleCountLabel }}
            </span>
        </template>

        <div class="border-line bg-brand-50/30 border-y px-4 py-3">
            <div class="relative">
                <Search
                    class="text-ink-muted pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
                <Input
                    v-model="search"
                    type="search"
                    aria-label="Search scenarios"
                    placeholder="Search scenarios..."
                    class="border-line bg-surface h-10 rounded-md ps-9 text-sm"
                />
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <Select
                    :model-value="department"
                    @update:model-value="updateFilter('department', $event)"
                >
                    <SelectTrigger
                        class="border-line bg-surface h-9 w-full text-xs sm:w-[180px]"
                    >
                        <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in library.departments"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="status"
                    @update:model-value="updateFilter('status', $event)"
                >
                    <SelectTrigger
                        class="border-line bg-surface h-9 w-full text-xs sm:w-[150px]"
                    >
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in library.statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    variant="ghost"
                    :disabled="!hasActiveFilters"
                    class="text-brand-700 hover:bg-brand-100/60 h-9 px-1.5 text-xs disabled:opacity-45"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                    Reset filters
                </Button>
            </div>
        </div>

        <div
            v-if="filteredScenarios.length"
            class="hidden overflow-x-auto md:block"
        >
            <table class="w-full min-w-[720px] border-collapse text-start">
                <thead
                    class="bg-brand-50/35 text-ink-slate text-[11px] font-semibold tracking-[0.08em] uppercase"
                >
                    <tr class="border-line border-b">
                        <th class="px-5 py-3">Scenario</th>
                        <th class="px-3 py-3">Department</th>
                        <th class="px-3 py-3">Level</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-5 py-3 text-end">Open</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="scenario in filteredScenarios"
                        :key="scenario.id"
                        class="border-line/80 hover:bg-brand-50/35 border-b transition-colors last:border-b-0"
                    >
                        <td class="max-w-[310px] px-5 py-3">
                            <button
                                type="button"
                                class="flex min-w-0 items-center gap-3 text-start"
                                @click="emit('open', scenario.id)"
                            >
                                <span
                                    class="bg-brand-50 text-brand-600 border-line grid size-11 shrink-0 place-items-center rounded-md border"
                                    aria-hidden="true"
                                >
                                    <Bot class="size-5" />
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate text-[13px] font-semibold"
                                    >
                                        {{ scenario.title }}
                                    </span>
                                    <span
                                        class="text-ink-muted mt-0.5 block truncate text-[11px]"
                                    >
                                        Real hotel conversation practice
                                    </span>
                                </span>
                            </button>
                        </td>
                        <td class="text-ink-slate px-3 py-3 text-xs">
                            {{ scenario.department }}
                        </td>
                        <td class="text-ink-slate px-3 py-3 text-xs">
                            {{ scenario.level.replace('Level: ', '') }}
                        </td>
                        <td class="px-3 py-3">
                            <span
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold',
                                        statusClass(scenario),
                                    )
                                "
                            >
                                <Check
                                    v-if="scenario.status === 'published'"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{ scenario.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-end">
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-brand-700 hover:bg-brand-100/60 h-8 gap-1 px-2 text-xs"
                                @click="emit('open', scenario.id)"
                            >
                                Open
                                <ArrowRight
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="filteredScenarios.length"
            class="divide-line divide-y md:hidden"
        >
            <button
                v-for="scenario in filteredScenarios"
                :key="scenario.id"
                type="button"
                class="hover:bg-brand-50/40 flex w-full items-center gap-3 px-4 py-3 text-start transition-colors"
                @click="emit('open', scenario.id)"
            >
                <span
                    class="bg-brand-50 text-brand-600 border-line grid size-12 shrink-0 place-items-center rounded-md border"
                    aria-hidden="true"
                >
                    <Bot class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span
                        class="text-brand-900 block truncate text-[13px] font-semibold"
                    >
                        {{ scenario.title }}
                    </span>
                    <span class="text-ink-muted mt-0.5 block text-[11px]">
                        {{ scenario.department }} ·
                        {{ scenario.level.replace('Level: ', '') }}
                    </span>
                    <span
                        :class="
                            cn(
                                'mt-1 inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                statusClass(scenario),
                            )
                        "
                    >
                        {{ scenario.status }}
                    </span>
                </span>
                <ArrowRight
                    class="text-brand-700 size-4 shrink-0"
                    aria-hidden="true"
                />
            </button>
        </div>

        <div v-else class="px-5 py-12 text-center">
            <div
                class="bg-brand-50 text-brand-600 mx-auto grid size-11 place-items-center rounded-full"
            >
                <Search class="size-5" aria-hidden="true" />
            </div>
            <p class="text-brand-900 mt-3 text-sm font-semibold">
                No scenarios found
            </p>
            <p class="text-ink-muted mt-1 text-xs">
                Try changing the search or filters.
            </p>
        </div>

        <div
            class="border-line bg-brand-50/25 flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-ink-muted text-xs">
                Select a scenario to open its conversation builder and preview.
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-100/60 h-9 gap-1.5 rounded-md px-3 text-xs font-semibold"
                    data-test="ai-scenario-creation-tutorial-button"
                    @click="emit('tutorial')"
                >
                    <CircleHelp class="size-3.5" aria-hidden="true" />
                    How to create an AI scenario
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 shrink-0 rounded-md px-3 text-xs font-semibold text-white"
                    @click="emit('create')"
                >
                    Create New Scenario
                </Button>
            </div>
        </div>
    </PanelCard>
</template>
