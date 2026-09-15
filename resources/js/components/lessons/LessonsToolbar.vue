<script setup lang="ts">
import {
    BookOpen,
    Bot,
    CirclePlus,
    ClipboardCheck,
    Eye,
    FolderOpen,
    Save,
    Settings,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { reactive, ref } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    LessonFilterOption,
    LessonsFilters,
    LessonsTab,
    LessonsTabKey,
} from '@/types';

type Props = {
    filters: LessonsFilters;
    tabs: LessonsTab[];
    activeTab: LessonsTabKey;
    class?: HTMLAttributes['class'];
};

type FilterKey = 'hotel' | 'department' | 'course' | 'unit' | 'lesson';

const props = defineProps<Props>();

const selectedValues = reactive<Record<FilterKey, string>>({
    hotel: props.filters.hotel,
    department: props.filters.department,
    course: props.filters.course,
    unit: props.filters.unit,
    lesson: props.filters.lesson,
});

const activeTab = ref<LessonsTabKey>(props.activeTab);

const tabIcons: Record<LessonsTabKey, Component> = {
    content: BookOpen,
    preview: Eye,
    settings: Settings,
    materials: FolderOpen,
    roleplay: Bot,
    quiz: ClipboardCheck,
};

const filterFields: Array<{
    key: FilterKey;
    label: string;
    options: LessonFilterOption[];
}> = [
    { key: 'hotel', label: 'Hotel', options: props.filters.hotels },
    {
        key: 'department',
        label: 'Department',
        options: props.filters.departments,
    },
    { key: 'course', label: 'Course', options: props.filters.courses },
    { key: 'unit', label: 'Unit', options: props.filters.units },
    { key: 'lesson', label: 'Lesson', options: props.filters.lessons },
];

function onSelect(target: FilterKey, value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    selectedValues[target] = value;
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-2.5', props.class)">
        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-5">
            <div
                v-for="field in filterFields"
                :key="field.key"
                class="border-line bg-surface shadow-card rounded-md border px-3 pt-[7px] pb-[5px]"
            >
                <p class="text-brand-900 text-[11px] leading-4 font-semibold">
                    {{ field.label }}
                </p>
                <Select
                    :model-value="selectedValues[field.key]"
                    @update:model-value="onSelect(field.key, $event)"
                >
                    <SelectTrigger
                        class="text-ink-indigo h-6 border-0 px-0 py-0 text-[12.5px] font-medium shadow-none focus-visible:ring-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div
            class="flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="flex min-w-0 gap-2 overflow-x-auto pb-1 xl:pb-0">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="
                        cn(
                            'inline-flex h-10 shrink-0 items-center gap-2 rounded-md border px-3 text-[12.5px] font-semibold whitespace-nowrap transition-colors duration-150',
                            activeTab === tab.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/55 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="activeTab = tab.key"
                >
                    <component
                        :is="tabIcons[tab.key]"
                        class="size-4 shrink-0"
                    />
                    {{ tab.label }}
                </button>
            </div>

            <div class="flex items-center gap-2 self-end xl:self-auto">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                >
                    <Save class="size-4" aria-hidden="true" />
                    Save Draft
                </Button>

                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white"
                >
                    <CirclePlus class="size-4" aria-hidden="true" />
                    Publish Lesson
                </Button>
            </div>
        </div>
    </div>
</template>
