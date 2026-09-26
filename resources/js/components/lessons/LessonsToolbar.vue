<script setup lang="ts">
import { router } from '@inertiajs/vue3';
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
import { computed, ref } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import {
    selectionPatch,
    visitLessons,
} from '@/components/lessons/lessonsQuery';
import { Button } from '@/components/ui/button';
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
import { publish, update } from '@/routes/lessons';
import type {
    LessonEditor,
    LessonFilterOption,
    LessonsFilters,
    LessonsTab,
    LessonsTabKey,
} from '@/types';

type Props = {
    filters: LessonsFilters;
    tabs: LessonsTab[];
    activeTab: LessonsTabKey;
    editor: LessonEditor;
    class?: HTMLAttributes['class'];
};

type FilterKey = 'hotel' | 'department' | 'course' | 'unit' | 'lesson';

const props = defineProps<Props>();

// "Generate with AI" and "Visible to" live in LessonsEditorAiActions (spec
// 0004), outside this row, so the mockup's tab row keeps its layout.

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));
const saving = ref<'draft' | 'publish' | null>(null);

const tabIcons: Record<LessonsTabKey, Component> = {
    content: BookOpen,
    preview: Eye,
    settings: Settings,
    materials: FolderOpen,
    roleplay: Bot,
    quiz: ClipboardCheck,
};

const filterFields = computed(
    (): Array<{
        key: FilterKey;
        label: string;
        options: LessonFilterOption[];
    }> => [
        { key: 'hotel', label: tk('Hotel'), options: props.filters.hotels },
        {
            key: 'department',
            label: tk('Department'),
            options: props.filters.departments,
        },
        { key: 'course', label: tk('Course'), options: props.filters.courses },
        { key: 'unit', label: tk('Unit'), options: props.filters.units },
        { key: 'lesson', label: tk('Lesson'), options: props.filters.lessons },
    ],
);

/**
 * The selects are driven by the server: choosing a value navigates with the
 * new query string and every child select is reset (spec 0003 Part D).
 */
function onSelect(target: FilterKey, value: AcceptableValue): void {
    if (typeof value !== 'string' || value === props.filters[target]) {
        return;
    }

    visitLessons(selectionPatch(target, value));
}

function onTab(tab: LessonsTabKey): void {
    if (tab === props.activeTab) {
        return;
    }

    visitLessons({ tab }, { only: ['activeTab'] });
}

const lessonOptions = {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
        saving.value = null;
    },
};

/** Save Draft keeps (or returns) the lesson in draft and confirms with a toast. */
function saveDraft(): void {
    if (props.editor.id === null) {
        return;
    }

    saving.value = 'draft';
    router.patch(
        update.url(props.editor.id),
        { status: 'draft', _notify: true },
        lessonOptions,
    );
}

function publishLesson(): void {
    if (props.editor.id === null) {
        return;
    }

    saving.value = 'publish';
    router.post(publish.url(props.editor.id), {}, lessonOptions);
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
                    {{ $t(field.label) }}
                </p>
                <Select
                    :model-value="filters[field.key]"
                    :disabled="field.options.length === 0"
                    @update:model-value="onSelect(field.key, $event)"
                >
                    <SelectTrigger
                        :data-test="`lessons-${field.key}-select`"
                        class="text-ink-indigo h-6 border-0 px-0 py-0 text-[12.5px] font-medium shadow-none focus-visible:ring-0"
                    >
                        <SelectValue
                            :placeholder="
                                field.options.length === 0 ? $t('None yet') : ''
                            "
                        />
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
                    :data-test="`lessons-tab-${tab.key}`"
                    :aria-pressed="activeTab === tab.key"
                    :class="
                        cn(
                            'inline-flex h-10 shrink-0 items-center gap-2 rounded-md border px-3 text-[12.5px] font-semibold whitespace-nowrap transition-colors duration-150',
                            'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
                            activeTab === tab.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/55 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="onTab(tab.key)"
                >
                    <component
                        :is="tabIcons[tab.key]"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ tab.label }}
                </button>
            </div>

            <div
                v-if="manage"
                class="flex items-center gap-2 self-end xl:self-auto"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="editor.id === null || saving !== null"
                    data-test="save-draft-button"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="saveDraft"
                >
                    <Save class="size-4" aria-hidden="true" />
                    {{ $t('Save Draft') }}
                </Button>

                <Button
                    type="button"
                    :disabled="editor.id === null || saving !== null"
                    data-test="publish-lesson-button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    @click="publishLesson"
                >
                    <CirclePlus class="size-4" aria-hidden="true" />
                    {{ $t('Publish Lesson') }}
                </Button>
            </div>
        </div>
    </div>
</template>
