<script setup lang="ts">
import { ChevronDown, ChevronRight, CirclePlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import LessonsAddDialog from '@/components/lessons/LessonsAddDialog.vue';
import {
    toggleOpenPatch,
    visitLessons,
} from '@/components/lessons/lessonsQuery';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { cn } from '@/lib/utils';
import type {
    LessonsFilters,
    LessonsTreeCourse,
    LessonsTreeTone,
    LessonsTreeUnit,
} from '@/types';

type Props = {
    courses: LessonsTreeCourse[];
    filters: LessonsFilters;
    class?: HTMLAttributes['class'];
};

type AddMode = 'course' | 'unit' | 'lesson';

const props = defineProps<Props>();

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const addOpen = ref(false);
const addMode = ref<AddMode>('course');
const addParentId = ref<number | null>(null);

const toneClass: Record<LessonsTreeTone, string> = {
    brand: 'bg-brand-100 text-brand-700',
    aqua: 'bg-aqua-tint text-aqua',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    gold: 'bg-gold-tint text-gold',
    danger: 'bg-danger-tint text-danger',
};

const treeOnly = ['courses', 'filters'];

/**
 * Opening a course or unit only changes the `open` list; the editor keeps
 * showing the active lesson. Selecting a lesson reloads everything that
 * depends on it.
 */
function toggleCourse(course: LessonsTreeCourse): void {
    visitLessons(
        toggleOpenPatch(
            props.filters.open,
            `c${course.id}`,
            course.expanded === true,
        ),
        { only: treeOnly, replace: true },
    );
}

function toggleUnit(unit: LessonsTreeUnit): void {
    visitLessons(
        toggleOpenPatch(
            props.filters.open,
            `u${unit.id}`,
            unit.expanded === true,
        ),
        { only: treeOnly, replace: true },
    );
}

function openLesson(
    course: LessonsTreeCourse,
    unit: LessonsTreeUnit,
    lessonId: number,
): void {
    if (String(lessonId) === props.filters.lesson) {
        return;
    }

    visitLessons({
        course: String(course.id),
        unit: String(unit.id),
        lesson: String(lessonId),
    });
}

function startAdd(mode: AddMode, parentId: number | null = null): void {
    addMode.value = mode;
    addParentId.value = parentId;
    addOpen.value = true;
}
</script>

<template>
    <PanelCard
        title="Course Structure"
        title-id="course-structure"
        :class="cn('min-w-0 px-3 pt-3 pb-3.5', props.class)"
        body-class="mt-2.5"
    >
        <template #actions>
            <Button
                v-if="manage"
                type="button"
                data-test="add-course-button"
                class="bg-brand-600 hover:bg-brand-700 shadow-btn h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white active:scale-[.97]"
                @click="startAdd('course')"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                Add
            </Button>
        </template>

        <p
            v-if="courses.length === 0"
            class="text-ink-slate rounded-md px-1 py-2 text-[12.5px]"
        >
            No course in this department yet. Use Add to create the first one.
        </p>

        <div class="space-y-1.5">
            <div
                v-for="course in courses"
                :key="course.id"
                class="rounded-md border border-transparent"
            >
                <button
                    type="button"
                    :aria-expanded="course.expanded === true"
                    :data-test="`course-${course.id}-toggle`"
                    class="text-brand-900 hover:bg-brand-50/65 focus-visible:ring-brand-600/15 flex w-full items-center gap-2 rounded-md px-1 py-[3px] text-start text-[12.5px] font-medium focus-visible:ring-3 focus-visible:outline-none"
                    @click="toggleCourse(course)"
                >
                    <component
                        :is="course.expanded ? ChevronDown : ChevronRight"
                        class="text-ink-slate size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span
                        :class="
                            cn(
                                'grid size-[18px] shrink-0 place-items-center rounded-md',
                                toneClass[course.tone ?? 'brand'],
                            )
                        "
                    >
                        <span class="size-2 rounded-full bg-current" />
                    </span>
                    <span class="truncate">{{ course.title }}</span>
                </button>

                <div
                    v-if="course.expanded"
                    class="border-line ms-[13px] mt-1 border-s ps-3"
                >
                    <div
                        v-for="unit in course.units ?? []"
                        :key="unit.id"
                        class="pb-1"
                    >
                        <button
                            type="button"
                            :aria-expanded="unit.expanded === true"
                            :data-test="`unit-${unit.id}-toggle`"
                            class="text-brand-900 hover:bg-brand-50/65 focus-visible:ring-brand-600/15 flex w-full items-center gap-1.5 rounded-md py-[3px] pe-1 text-start text-[12.5px] focus-visible:ring-3 focus-visible:outline-none"
                            @click="toggleUnit(unit)"
                        >
                            <component
                                :is="unit.expanded ? ChevronDown : ChevronRight"
                                class="text-ink-slate size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ unit.title }}</span>
                        </button>

                        <div
                            v-if="unit.expanded"
                            class="border-line ms-[9px] mt-1 space-y-1 border-s ps-3"
                        >
                            <button
                                v-for="lesson in unit.lessons ?? []"
                                :key="lesson.id"
                                type="button"
                                :aria-current="
                                    lesson.active ? 'true' : undefined
                                "
                                :data-test="`lesson-${lesson.id}-open`"
                                :class="
                                    cn(
                                        'focus-visible:ring-brand-600/15 flex min-h-8 w-full items-center rounded-md px-3 text-start text-[12.5px] font-medium focus-visible:ring-3 focus-visible:outline-none',
                                        lesson.active
                                            ? 'bg-brand-100/80 text-brand-800 shadow-card'
                                            : 'text-ink-muted hover:bg-brand-50/65',
                                    )
                                "
                                @click="openLesson(course, unit, lesson.id)"
                            >
                                {{ lesson.title }}
                            </button>

                            <button
                                v-if="unit.addLessonLabel && manage"
                                type="button"
                                :data-test="`unit-${unit.id}-add-lesson`"
                                class="text-brand-700 hover:bg-brand-50/70 focus-visible:ring-brand-600/15 flex min-h-8 w-full items-center rounded-md px-3 text-start text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                                @click="startAdd('lesson', unit.id)"
                            >
                                {{ unit.addLessonLabel }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <LessonsAddDialog
            v-model:open="addOpen"
            :mode="addMode"
            :parent-id="addParentId"
            :filters="filters"
        />
    </PanelCard>
</template>
