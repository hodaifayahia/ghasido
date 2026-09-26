<script setup lang="ts">
import { ArrowRight, Check, Filter, RotateCcw, Search } from '@lucide/vue';
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
import type { TestListItem, TestsList, TestsSelectOption } from '@/types';

const props = defineProps<{
    list: TestsList;
}>();

const emit = defineEmits<{
    open: [id: string];
    create: [];
}>();

const search = ref(props.list.search);
const hotel = ref(props.list.hotel);
const department = ref(props.list.department);
const type = ref(props.list.type);

watch(
    () => props.list,
    (list) => {
        search.value = list.search;
        hotel.value = list.hotel;
        department.value = list.department;
        type.value = list.type;
    },
    { deep: true },
);

function labelFor(options: TestsSelectOption[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

const filteredItems = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    const hotelLabel = labelFor(
        props.list.hotels,
        hotel.value,
    ).toLocaleLowerCase();
    const departmentLabel = labelFor(
        props.list.departments,
        department.value,
    ).toLocaleLowerCase();

    return props.list.items.filter((item) => {
        const searchable =
            `${item.title} ${item.department} ${item.hotel}`.toLocaleLowerCase();

        return (
            (term === '' || searchable.includes(term)) &&
            (hotel.value === 'all-hotels' ||
                item.hotel.toLocaleLowerCase() === hotelLabel) &&
            (department.value === 'all-departments' ||
                item.department.toLocaleLowerCase() === departmentLabel ||
                (department.value === 'food-service' &&
                    item.department.toLocaleLowerCase() === 'f&b')) &&
            (type.value === 'all-types' || item.type === type.value)
        );
    });
});

function resetFilters(): void {
    search.value = '';
    hotel.value = 'all-hotels';
    department.value = 'all-departments';
    type.value = 'all-types';
}

function typeLabel(item: TestListItem): string {
    return item.type === 'pre' ? 'Pre-test' : 'Post-test';
}

function statusClass(item: TestListItem): string {
    return item.status === 'active'
        ? 'bg-success/15 text-success'
        : 'bg-gold/25 text-sunset';
}
</script>

<template>
    <PanelCard
        title="Test Library"
        title-id="test-library-title"
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
            <span class="text-ink-muted text-xs font-medium">
                {{ filteredItems.length }} of {{ list.items.length }} tests
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
                    aria-label="Search tests"
                    placeholder="Search tests..."
                    class="border-line bg-surface h-10 rounded-md ps-9 text-sm"
                />
            </div>

            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                <Select v-model="hotel">
                    <SelectTrigger class="border-line bg-surface h-9 text-xs">
                        <SelectValue placeholder="All Hotels" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in list.hotels"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="department">
                    <SelectTrigger class="border-line bg-surface h-9 text-xs">
                        <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in list.departments"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="type">
                    <SelectTrigger class="border-line bg-surface h-9 text-xs">
                        <SelectValue placeholder="All Types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in list.types"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button
                type="button"
                variant="ghost"
                class="text-brand-700 hover:bg-brand-100/60 mt-2 h-7 px-1.5 text-xs"
                @click="resetFilters"
            >
                <RotateCcw class="size-3.5" aria-hidden="true" />
                Reset filters
            </Button>
        </div>

        <div
            v-if="filteredItems.length"
            class="hidden overflow-x-auto md:block"
        >
            <table class="w-full min-w-[760px] border-collapse text-start">
                <thead
                    class="bg-brand-50/35 text-ink-slate text-[11px] font-semibold tracking-[0.08em] uppercase"
                >
                    <tr class="border-line border-b">
                        <th class="px-5 py-3">Test</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">Department</th>
                        <th class="px-3 py-3">Questions</th>
                        <th class="px-3 py-3">Time</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-5 py-3 text-end">Open</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in filteredItems"
                        :key="item.id"
                        class="border-line/80 hover:bg-brand-50/35 border-b transition-colors last:border-b-0"
                    >
                        <td class="max-w-[270px] px-5 py-3">
                            <button
                                type="button"
                                class="flex min-w-0 items-center gap-3 text-start"
                                @click="emit('open', item.id)"
                            >
                                <span
                                    class="border-line bg-brand-50 relative block size-10 shrink-0 overflow-hidden rounded-md border"
                                    :style="{
                                        backgroundImage: `url('/decor/tests-mockup.jpg')`,
                                        backgroundPosition: `-${item.crop.x}px -${item.crop.y}px`,
                                        backgroundSize: '1280px 853px',
                                    }"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate text-[13px] font-semibold"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <span
                                        class="text-ink-muted mt-0.5 block truncate text-[11px]"
                                    >
                                        {{ item.hotel }}
                                    </span>
                                </span>
                            </button>
                        </td>
                        <td class="px-3 py-3">
                            <span
                                :class="
                                    cn(
                                        'inline-flex rounded-full px-2 py-1 text-[11px] font-semibold',
                                        item.type === 'pre'
                                            ? 'bg-azure/25 text-brand-700'
                                            : 'bg-aqua-tint text-aqua',
                                    )
                                "
                            >
                                {{ typeLabel(item) }}
                            </span>
                        </td>
                        <td class="text-ink-slate px-3 py-3 text-xs">
                            {{ item.department }}
                        </td>
                        <td class="text-ink-slate px-3 py-3 text-xs">
                            {{ item.questionCount }}
                        </td>
                        <td class="text-ink-slate px-3 py-3 text-xs">
                            {{ item.timeLimit ? `${item.timeLimit} min` : '—' }}
                        </td>
                        <td class="px-3 py-3">
                            <span
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold capitalize',
                                        statusClass(item),
                                    )
                                "
                            >
                                <Check
                                    v-if="item.status === 'active'"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{ item.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-end">
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-brand-700 hover:bg-brand-100/60 h-8 gap-1 px-2 text-xs"
                                @click="emit('open', item.id)"
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

        <div v-if="filteredItems.length" class="divide-line divide-y md:hidden">
            <button
                v-for="item in filteredItems"
                :key="item.id"
                type="button"
                class="hover:bg-brand-50/40 flex w-full items-center gap-3 px-4 py-3 text-start transition-colors"
                @click="emit('open', item.id)"
            >
                <span
                    class="border-line bg-brand-50 block size-11 shrink-0 overflow-hidden rounded-md border"
                    :style="{
                        backgroundImage: `url('/decor/tests-mockup.jpg')`,
                        backgroundPosition: `-${item.crop.x}px -${item.crop.y}px`,
                        backgroundSize: '1280px 853px',
                    }"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="text-brand-900 block truncate text-[13px] font-semibold"
                    >
                        {{ item.title }}
                    </span>
                    <span class="text-ink-muted mt-0.5 block text-[11px]">
                        {{ item.department }} ·
                        {{ item.questionCount }} questions
                    </span>
                    <span class="mt-1 flex items-center gap-1.5">
                        <span
                            :class="
                                cn(
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                    item.type === 'pre'
                                        ? 'bg-azure/25 text-brand-700'
                                        : 'bg-aqua-tint text-aqua',
                                )
                            "
                        >
                            {{ typeLabel(item) }}
                        </span>
                        <span
                            :class="
                                cn(
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold capitalize',
                                    statusClass(item),
                                )
                            "
                        >
                            {{ item.status }}
                        </span>
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
                No tests found
            </p>
            <p class="text-ink-muted mt-1 text-xs">
                Try changing the search or filters.
            </p>
        </div>

        <div
            class="border-line bg-brand-50/25 flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-ink-muted text-xs">
                Select a test to open its question builder and uploaded content.
            </p>
            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 shrink-0 rounded-md px-3 text-xs font-semibold text-white"
                @click="emit('create')"
            >
                Create New Test
            </Button>
        </div>
    </PanelCard>
</template>
