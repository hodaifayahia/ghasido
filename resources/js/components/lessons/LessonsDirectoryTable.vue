<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    Eye,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { ref, watch } from 'vue';
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
import { useCan } from '@/composables/useCan';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    LessonDirectoryFilters,
    LessonDirectoryPagination,
    LessonDirectoryRow,
} from '@/types';

type Props = {
    lessons: LessonDirectoryRow[];
    filters: LessonDirectoryFilters;
    pagination: LessonDirectoryPagination;
    loading?: boolean;
};

const props = withDefaults(defineProps<Props>(), { loading: false });

export type LessonDirectoryFilterValues = {
    search: string;
    hotel: string;
    department: string;
    course: string;
    status: string;
};

const emit = defineEmits<{
    create: [];
    generate: [];
    tutorial: [];
    filter: [values: LessonDirectoryFilterValues];
    page: [page: number];
    pageSize: [size: number];
    delete: [lesson: LessonDirectoryRow];
}>();

const { can } = useCan();
const manage = can('lessons.manage');

const search = ref(props.filters.search);
const hotel = ref(props.filters.hotel);
const department = ref(props.filters.department);
const course = ref(props.filters.course);
const status = ref(props.filters.status);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        hotel.value = filters.hotel;
        department.value = filters.department;
        course.value = filters.course;
        status.value = filters.status;
    },
    { deep: true },
);

function current(): LessonDirectoryFilterValues {
    return {
        search: search.value,
        hotel: hotel.value,
        department: department.value,
        course: course.value,
        status: status.value,
    };
}

watchDebounced(
    search,
    (value) => {
        if (value !== props.filters.search) {
            emit('filter', current());
        }
    },
    { debounce: 300 },
);

function onSelect(
    target: 'hotel' | 'department' | 'course' | 'status',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'hotel') {
        hotel.value = value;
    } else if (target === 'department') {
        department.value = value;
    } else if (target === 'course') {
        course.value = value;
    } else {
        status.value = value;
    }

    emit('filter', current());
}

function resetFilters(): void {
    search.value = '';
    hotel.value = 'all-hotels';
    department.value = 'all-departments';
    course.value = 'all-courses';
    status.value = 'all-statuses';
    emit('filter', current());
}

function goTo(page: number): void {
    if (
        page < 1 ||
        page > props.pagination.lastPage ||
        page === props.pagination.currentPage
    ) {
        return;
    }

    emit('page', page);
}

function changePageSize(value: AcceptableValue): void {
    const size = Number(value);

    if (
        !props.filters.pageSizes.includes(size) ||
        size === props.pagination.perPage
    ) {
        return;
    }

    emit('pageSize', size);
}

function statusLabel(statusValue: LessonDirectoryRow['status']): string {
    return statusValue === 'published' ? tk('Published') : tk('Draft');
}

function statusClass(statusValue: LessonDirectoryRow['status']): string {
    return statusValue === 'published'
        ? 'bg-success-tint text-success'
        : 'bg-warning-tint text-warning';
}

const iconButton =
    'border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex items-center justify-center rounded-md border focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45';

// Safe delete: outline only, danger colours. The ::after pad widens the
// 32px phone button to a 44px tap target (ACC-03).
const deleteButton =
    'border-danger/40 text-danger-text hover:bg-danger-tint bg-surface relative inline-flex size-8 shrink-0 items-center justify-center rounded-md border after:absolute after:-inset-1.5 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <PanelCard
        :title="$t('Lesson Directory')"
        title-id="lesson-directory-title"
        class="px-3 pt-3 pb-3 md:px-4"
        body-class="mt-2"
    >
        <template #actions>
            <!-- Phones get these in their own row below (RESP-01): the
                 title row cannot hold the count and both buttons. -->
            <div class="hidden items-center gap-2 sm:flex">
                <span class="text-ink-faint text-[11.5px] whitespace-nowrap">
                    {{ $tc(':count lesson|:count lessons', pagination.total) }}
                </span>
                <Button
                    v-if="manage"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold shadow-none"
                    data-test="generate-with-ai-from-directory"
                    @click="emit('generate')"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{ $t('Generate with AI') }}
                </Button>
                <Button
                    v-if="manage"
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn text-surface h-8 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold"
                    data-test="create-lesson-from-directory"
                    @click="emit('create')"
                >
                    <Plus class="size-3.5" aria-hidden="true" />
                    {{ $t('Create lesson') }}
                </Button>
            </div>
        </template>

        <div class="mb-3 grid gap-2 sm:hidden">
            <p class="text-ink-faint text-[11.5px]">
                {{ $tc(':count lesson|:count lessons', pagination.total) }}
            </p>
            <div v-if="manage" class="grid grid-cols-2 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-2 text-[12px] font-semibold shadow-none"
                    data-test="generate-with-ai-from-directory-mobile"
                    @click="emit('generate')"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{ $t('Generate with AI') }}
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn text-surface h-10 gap-1.5 rounded-md px-2 text-[12px] font-semibold"
                    data-test="create-lesson-from-directory-mobile"
                    @click="emit('create')"
                >
                    <Plus class="size-3.5" aria-hidden="true" />
                    {{ $t('Create lesson') }}
                </Button>
            </div>
        </div>

        <div
            class="border-line bg-brand-50/35 mb-3 rounded-lg border p-2.5 md:p-3"
        >
            <div
                class="mb-2.5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-brand-900 text-[12.5px] font-semibold">
                        {{ $t('Find a lesson quickly') }}
                    </p>
                    <p class="text-ink-slate mt-0.5 text-[11.5px]">
                        {{
                            $t(
                                'Search by lesson, course or unit, then narrow the library with filters.',
                            )
                        }}
                    </p>
                </div>
                <Button
                    v-if="manage"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 self-start rounded-md px-2.5 text-[11.5px] font-semibold shadow-none sm:self-auto"
                    data-test="lesson-creation-tutorial-button"
                    @click="emit('tutorial')"
                >
                    <CircleHelp class="size-3.5" aria-hidden="true" />
                    {{ $t('How to create a lesson') }}
                </Button>
            </div>

            <div
                class="grid gap-2 md:grid-cols-2 xl:grid-cols-[minmax(220px,1.4fr)_repeat(4,minmax(130px,1fr))_auto]"
            >
                <div class="relative min-w-0 md:col-span-2 xl:col-span-1">
                    <Search
                        aria-hidden="true"
                        class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="$t('Search lessons, courses or units...')"
                        :aria-label="$t('Search lessons, courses or units')"
                        data-test="lessons-directory-search"
                        class="border-line bg-surface placeholder:text-ink-faint h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                    />
                </div>

                <Select
                    :model-value="hotel"
                    @update:model-value="onSelect('hotel', $event)"
                >
                    <SelectTrigger
                        :aria-label="$t('Filter lessons by hotel')"
                        data-test="lessons-directory-hotel-filter"
                        class="border-line text-ink bg-surface h-9 w-full rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.hotels"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        :aria-label="$t('Filter lessons by department')"
                        data-test="lessons-directory-department-filter"
                        class="border-line text-ink bg-surface h-9 w-full rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.departments"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="course"
                    @update:model-value="onSelect('course', $event)"
                >
                    <SelectTrigger
                        :aria-label="$t('Filter lessons by course')"
                        data-test="lessons-directory-course-filter"
                        class="border-line text-ink bg-surface h-9 w-full rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in filters.courses"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="status"
                    @update:model-value="onSelect('status', $event)"
                >
                    <SelectTrigger
                        :aria-label="$t('Filter lessons by status')"
                        data-test="lessons-directory-status-filter"
                        class="border-line text-ink bg-surface h-9 w-full rounded-md px-3 text-[12.5px] shadow-none"
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

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    data-test="reset-lessons-directory-filters"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />
                    {{ $t('Reset') }}
                </Button>
            </div>
        </div>

        <div
            class="border-line/80 overflow-hidden rounded-lg border"
            :aria-busy="loading || undefined"
        >
            <div class="overflow-x-auto max-md:hidden">
                <table
                    class="w-full min-w-[820px] table-fixed border-collapse text-start"
                >
                    <!-- The Action column holds Edit + Delete for admins. -->
                    <colgroup>
                        <col class="w-[24%]" />
                        <col class="w-[18%]" />
                        <col class="w-[13%]" />
                        <col class="w-[12%]" />
                        <col class="w-[8%]" />
                        <col class="w-[10%]" />
                        <col class="w-[15%]" />
                    </colgroup>
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[11.5px] leading-4 font-semibold"
                        >
                            <th class="px-3 py-2.5 text-start">
                                {{ $t('Lesson') }}
                            </th>
                            <th class="px-2 py-2.5 text-start">
                                {{ $t('Course / Unit') }}
                            </th>
                            <th class="px-2 py-2.5 text-start">
                                {{ $t('Department') }}
                            </th>
                            <th class="px-2 py-2.5 text-start">
                                {{ $t('Hotel') }}
                            </th>
                            <th class="px-2 py-2.5 text-start">
                                {{ $t('Steps') }}
                            </th>
                            <th class="px-2 py-2.5 text-start">
                                {{ $t('Status') }}
                            </th>
                            <th class="px-2 py-2.5 text-start">
                                {{ $t('Action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12px]">
                        <tr
                            v-for="lesson in lessons"
                            :key="lesson.id"
                            class="border-line/80 hover:bg-brand-50/35 border-t transition-colors"
                            :data-test="`lesson-directory-row-${lesson.id}`"
                        >
                            <td class="px-3 py-2.5 align-middle">
                                <Link
                                    :href="lesson.url"
                                    class="focus-visible:ring-brand-600/15 block min-w-0 rounded-sm focus-visible:ring-3 focus-visible:outline-none"
                                >
                                    <span
                                        class="text-brand-700 block truncate font-semibold"
                                    >
                                        {{ lesson.title }}
                                    </span>
                                    <span
                                        class="text-ink-faint mt-0.5 block truncate text-[10.5px]"
                                    >
                                        {{
                                            $tc(
                                                ':count employee step|:count employee steps',
                                                lesson.steps,
                                            )
                                        }}
                                    </span>
                                </Link>
                            </td>
                            <td class="px-2 py-2.5 align-middle">
                                <span
                                    class="text-brand-900 block truncate font-medium"
                                    >{{ lesson.course }}</span
                                >
                                <span
                                    class="text-ink-faint mt-0.5 block truncate text-[10.5px]"
                                    >{{ lesson.unit }}</span
                                >
                            </td>
                            <td class="text-ink-slate px-2 py-2.5 align-middle">
                                <span class="block truncate">{{
                                    lesson.department
                                }}</span>
                            </td>
                            <td class="text-ink-slate px-2 py-2.5 align-middle">
                                <span class="block truncate">{{
                                    lesson.hotel
                                }}</span>
                            </td>
                            <td
                                class="text-brand-900 px-2 py-2.5 align-middle font-semibold"
                            >
                                {{ lesson.steps }}
                            </td>
                            <td class="px-2 py-2.5 align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex px-2 py-1 text-[10px] font-semibold capitalize',
                                            statusClass(lesson.status),
                                        )
                                    "
                                >
                                    {{ $t(statusLabel(lesson.status)) }}
                                </span>
                            </td>
                            <td class="px-2 py-2.5 align-middle">
                                <div class="flex items-center gap-2">
                                    <Button
                                        as-child
                                        type="button"
                                        variant="outline"
                                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-2.5 text-[11px] font-semibold shadow-none"
                                    >
                                        <Link :href="lesson.url">
                                            <component
                                                :is="manage ? Pencil : Eye"
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {{
                                                manage ? $t('Edit') : $t('View')
                                            }}
                                        </Link>
                                    </Button>
                                    <button
                                        v-if="manage"
                                        type="button"
                                        :class="deleteButton"
                                        :aria-label="
                                            $t('Delete :name', {
                                                name: lesson.title,
                                            })
                                        "
                                        :title="$t('Delete')"
                                        :data-test="`delete-lesson-${lesson.id}-button`"
                                        @click="emit('delete', lesson)"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="lessons.length === 0">
                            <td
                                colspan="7"
                                class="text-ink-muted px-4 py-10 text-center text-[13px]"
                            >
                                {{
                                    $t(
                                        'No lessons match these filters. Try clearing one or more filters.',
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-for="lesson in lessons"
                    :key="lesson.id"
                    class="bg-surface p-3.5"
                    :data-test="`lesson-directory-card-${lesson.id}`"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link
                                :href="lesson.url"
                                class="focus-visible:outline-none"
                            >
                                <p
                                    class="font-heading text-brand-800 truncate text-[15px] leading-5 font-semibold"
                                >
                                    {{ lesson.title }}
                                </p>
                            </Link>
                            <p
                                class="text-ink-muted mt-0.5 truncate text-[12px]"
                            >
                                {{ lesson.course }} · {{ lesson.unit }}
                            </p>
                        </div>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex shrink-0 px-2 py-1 text-[10px] font-semibold capitalize',
                                    statusClass(lesson.status),
                                )
                            "
                        >
                            {{ $t(statusLabel(lesson.status)) }}
                        </span>
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-[12px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Department:')
                            }}</span>
                            {{ lesson.department }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Hotel:')
                            }}</span>
                            {{ lesson.hotel }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium">{{
                                $t('Steps:')
                            }}</span>
                            {{ lesson.steps }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-end gap-2">
                        <Link
                            :href="lesson.url"
                            :class="
                                cn(
                                    iconButton,
                                    'min-h-8 gap-1.5 px-2.5 text-[11px] font-semibold',
                                )
                            "
                        >
                            <component
                                :is="manage ? Pencil : Eye"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ manage ? $t('Edit lesson') : $t('View lesson') }}
                        </Link>
                        <button
                            v-if="manage"
                            type="button"
                            :class="deleteButton"
                            :aria-label="
                                $t('Delete :name', { name: lesson.title })
                            "
                            :title="$t('Delete')"
                            :data-test="`delete-lesson-${lesson.id}-card-button`"
                            @click="emit('delete', lesson)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </li>
                <li
                    v-if="lessons.length === 0"
                    class="text-ink-muted bg-surface p-8 text-center text-[13px]"
                >
                    {{ $t('No lessons match these filters.') }}
                </li>
            </ul>
        </div>

        <div
            class="text-ink-muted mt-3 flex flex-col gap-2 text-[12px] leading-5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <p>
                    {{
                        $t('Showing :range of :total lessons', {
                            range:
                                pagination.total === 0
                                    ? '0'
                                    : `${pagination.from}-${pagination.to}`,
                            total: pagination.total,
                        })
                    }}
                </p>
                <div class="flex items-center gap-1.5">
                    <span>{{ $t('Show') }}</span>
                    <Select
                        :model-value="String(pagination.perPage)"
                        @update:model-value="changePageSize"
                    >
                        <SelectTrigger
                            :aria-label="$t('Lessons per page')"
                            data-test="lessons-directory-page-size"
                            class="border-line text-brand-800 bg-surface h-8 w-[72px] rounded-md px-2.5 text-[12px] font-semibold shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="size in filters.pageSizes"
                                :key="size"
                                :value="String(size)"
                                class="text-[13px]"
                            >
                                {{ size }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <span>{{ $t('per page') }}</span>
                </div>
            </div>

            <nav
                :aria-label="$t('Lessons pagination')"
                class="flex flex-wrap items-center gap-1.5"
            >
                <button
                    type="button"
                    class="text-ink-muted hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="pagination.currentPage <= 1 || loading"
                    data-test="lessons-directory-previous-page"
                    @click="goTo(pagination.currentPage - 1)"
                >
                    <ChevronLeft class="size-3.5" aria-hidden="true" />
                    {{ $t('Previous') }}
                </button>

                <template
                    v-for="(page, index) in pagination.pages"
                    :key="`${page}-${index}`"
                >
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
                                'inline-flex size-8 items-center justify-center rounded-md border text-[12px] font-semibold',
                                page === pagination.currentPage
                                    ? 'border-brand-600 bg-brand-600 text-surface shadow-btn'
                                    : 'border-line text-brand-800 hover:bg-brand-50 bg-surface',
                            )
                        "
                        :aria-current="
                            page === pagination.currentPage ? 'page' : undefined
                        "
                        :disabled="loading"
                        @click="goTo(page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex min-h-8 items-center gap-1 rounded-md border px-2.5 font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="
                        pagination.currentPage >= pagination.lastPage || loading
                    "
                    data-test="lessons-directory-next-page"
                    @click="goTo(pagination.currentPage + 1)"
                >
                    {{ $t('Next') }}
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </PanelCard>
</template>
