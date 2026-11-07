<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    CirclePlus,
    EyeOff,
    GripVertical,
    Layers,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import LessonsAddDialog from '@/components/lessons/LessonsAddDialog.vue';
import LessonsMoveDialog from '@/components/lessons/LessonsMoveDialog.vue';
import LessonsTreeActionDialog from '@/components/lessons/LessonsTreeActionDialog.vue';
import LessonsTreeRowMenu from '@/components/lessons/LessonsTreeRowMenu.vue';
import {
    selectionPatch,
    toggleOpenPatch,
    visitLessons,
} from '@/components/lessons/lessonsQuery';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { cn } from '@/lib/utils';
import courseRoutes from '@/routes/courses';
import lessonRoutes from '@/routes/lessons';
import unitRoutes from '@/routes/units';
import type {
    LessonsFilters,
    LessonsTreeAction,
    LessonsTreeCourse,
    LessonsTreeLesson,
    LessonsTreeTarget,
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

// Rename / Copy / Delete from each row's menu (client request 2026-10-05).
const actionOpen = ref(false);
const actionMode = ref<'rename' | 'delete'>('rename');
const actionTarget = ref<LessonsTreeTarget | null>(null);
const copying = ref(false);

function courseTarget(course: LessonsTreeCourse): LessonsTreeTarget {
    return { kind: 'course', id: course.id, name: course.title };
}

function unitTarget(unit: LessonsTreeUnit): LessonsTreeTarget {
    return { kind: 'unit', id: unit.id, name: unit.name ?? unit.title };
}

function lessonTarget(lesson: LessonsTreeLesson): LessonsTreeTarget {
    return { kind: 'lesson', id: lesson.id, name: lesson.name ?? lesson.title };
}

/*
 * One course at a time (client request 2026-11-05): with a course open the
 * tree shows only that course's units and lessons, so departments with many
 * courses do not mix their content. "All courses" lists every course again.
 */
const showAll = ref(false);

const visibleCourses = computed((): LessonsTreeCourse[] => {
    if (showAll.value || props.filters.course === '') {
        return props.courses;
    }

    const open = props.courses.filter(
        (course) => String(course.id) === props.filters.course,
    );

    return open.length > 0 ? open : props.courses;
});

const focused = computed(
    () => visibleCourses.value.length < props.courses.length,
);

function openCourse(course: LessonsTreeCourse): void {
    showAll.value = false;

    if (String(course.id) === props.filters.course) {
        toggleCourse(course);

        return;
    }

    visitLessons(selectionPatch('course', String(course.id)));
}

/**
 * A new course stays hidden from employees until the admin shows it
 * (client request 2026-11-05); publishing a lesson no longer shows its
 * course by itself.
 */
function setVisibility(target: LessonsTreeTarget, visible: boolean): void {
    router.patch(
        courseRoutes.update.url(target.id),
        { status: visible ? 'published' : 'draft' },
        { preserveScroll: true, preserveState: true },
    );
}

function onAction(target: LessonsTreeTarget, action: LessonsTreeAction): void {
    if (action === 'publish' || action === 'unpublish') {
        setVisibility(target, action === 'publish');

        return;
    }

    if (action === 'duplicate') {
        duplicate(target);

        return;
    }

    if (action !== 'rename' && action !== 'delete') {
        return;
    }

    actionTarget.value = target;
    actionMode.value = action;
    actionOpen.value = true;
}

/*
 * Moving lessons (client request 2026-11-07): drag a lesson by its handle
 * onto another lesson (it takes that place) or onto a unit (it goes last),
 * or use Move up / Move down / Move to… in its menu, which also work with
 * a keyboard and on touch screens where dragging does not (ACC-03).
 */
const moving = ref(false);
const dragging = ref<number | null>(null);
const dropTarget = ref<string | null>(null);
const moveOpen = ref(false);
const moveLesson = ref<LessonsTreeTarget | null>(null);

function sendMove(
    lessonId: number,
    unitId: number,
    position: number | null,
): void {
    if (moving.value) {
        return;
    }

    router.patch(
        lessonRoutes.move.url(lessonId),
        { unit_id: unitId, position },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                moving.value = true;
            },
            onFinish: () => {
                moving.value = false;
            },
        },
    );
}

function onLessonAction(
    unit: LessonsTreeUnit,
    index: number,
    lesson: LessonsTreeLesson,
    action: LessonsTreeAction,
): void {
    if (action === 'move_up') {
        sendMove(lesson.id, unit.id, index);

        return;
    }

    if (action === 'move_down') {
        sendMove(lesson.id, unit.id, index + 2);

        return;
    }

    if (action === 'move_to') {
        moveLesson.value = lessonTarget(lesson);
        moveOpen.value = true;

        return;
    }

    onAction(lessonTarget(lesson), action);
}

function onDragStart(event: DragEvent, lesson: LessonsTreeLesson): void {
    dragging.value = lesson.id;
    event.dataTransfer?.setData('text/plain', String(lesson.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd(): void {
    dragging.value = null;
    dropTarget.value = null;
}

function onDragOver(event: DragEvent, key: string): void {
    if (dragging.value === null) {
        return;
    }

    event.preventDefault();
    dropTarget.value = key;
}

function onDrop(unit: LessonsTreeUnit, position: number | null): void {
    const lessonId = dragging.value;
    onDragEnd();

    if (lessonId !== null) {
        sendMove(lessonId, unit.id, position);
    }
}

/** A draft copy lands at the end of the same parent and opens. */
function duplicate(target: LessonsTreeTarget): void {
    if (copying.value) {
        return;
    }

    const url = {
        course: () => courseRoutes.duplicate.url(target.id),
        unit: () => unitRoutes.duplicate.url(target.id),
        lesson: () => lessonRoutes.duplicate.url(target.id),
    }[target.kind]();

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                copying.value = true;
            },
            onFinish: () => {
                copying.value = false;
            },
        },
    );
}
</script>

<template>
    <PanelCard
        :title="$t('Course Structure')"
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
                {{ $t('Add') }}
            </Button>
        </template>

        <p
            v-if="courses.length === 0"
            class="text-ink-slate rounded-md px-1 py-2 text-[12.5px]"
        >
            {{
                $t(
                    'No course in this department yet. Use Add to create the first one.',
                )
            }}
        </p>

        <button
            v-if="focused || showAll"
            type="button"
            class="text-brand-700 hover:bg-brand-50/70 focus-visible:ring-brand-600/15 mb-1.5 flex min-h-8 w-full items-center gap-1.5 rounded-md px-1 text-start text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
            data-test="tree-all-courses-toggle"
            @click="showAll = !showAll"
        >
            <Layers class="size-3.5 shrink-0" aria-hidden="true" />
            {{
                showAll
                    ? $t('Show only the open course')
                    : $t('All courses (:count)', { count: courses.length })
            }}
        </button>

        <div class="space-y-1.5">
            <div
                v-for="course in visibleCourses"
                :key="course.id"
                class="rounded-md border border-transparent"
            >
                <div class="group/row relative flex items-center gap-0.5">
                    <button
                        type="button"
                        :aria-expanded="course.expanded === true"
                        :data-test="`course-${course.id}-toggle`"
                        class="text-brand-900 hover:bg-brand-50/65 focus-visible:ring-brand-600/15 flex min-w-0 flex-1 items-center gap-2 rounded-md px-1 py-[3px] text-start text-[12.5px] font-medium focus-visible:ring-3 focus-visible:outline-none"
                        @click="openCourse(course)"
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
                        <span
                            v-if="course.status === 'draft'"
                            class="rounded-pill bg-warning-tint text-warning-text inline-flex shrink-0 items-center gap-1 px-2 py-0.5 text-[10.5px] font-semibold"
                            :title="
                                $t('Hidden from employees until you show it')
                            "
                            :data-test="`course-${course.id}-hidden`"
                        >
                            <EyeOff class="size-3" aria-hidden="true" />
                            {{ $t('Hidden') }}
                        </span>
                        <span
                            v-if="course.levelLabel"
                            class="rounded-pill bg-ai-tint text-ai shrink-0 px-2 py-0.5 text-[10.5px] font-semibold"
                            :data-test="`course-${course.id}-level`"
                        >
                            {{ course.levelLabel }}
                        </span>
                    </button>
                    <LessonsTreeRowMenu
                        v-if="manage"
                        :name="course.title"
                        :test-id="`course-${course.id}`"
                        :can-edit="course.canEdit !== false"
                        :can-copy="course.canCopy !== false"
                        :can-delete="course.canDelete !== false"
                        :visibility="course.status ?? null"
                        @select="onAction(courseTarget(course), $event)"
                    />
                </div>

                <div
                    v-if="course.expanded"
                    class="border-line ms-[13px] mt-1 border-s ps-3"
                >
                    <div
                        v-for="unit in course.units ?? []"
                        :key="unit.id"
                        class="pb-1"
                    >
                        <div
                            :class="
                                cn(
                                    'group/row relative flex items-center gap-0.5 rounded-md',
                                    dropTarget === `u${unit.id}` &&
                                        'bg-brand-50 ring-brand-300 ring-2',
                                )
                            "
                            @dragover="onDragOver($event, `u${unit.id}`)"
                            @dragleave="dropTarget = null"
                            @drop.prevent="onDrop(unit, null)"
                        >
                            <button
                                type="button"
                                :aria-expanded="unit.expanded === true"
                                :data-test="`unit-${unit.id}-toggle`"
                                class="text-brand-900 hover:bg-brand-50/65 focus-visible:ring-brand-600/15 flex min-w-0 flex-1 items-center gap-1.5 rounded-md py-[3px] pe-1 text-start text-[12.5px] focus-visible:ring-3 focus-visible:outline-none"
                                @click="toggleUnit(unit)"
                            >
                                <component
                                    :is="
                                        unit.expanded
                                            ? ChevronDown
                                            : ChevronRight
                                    "
                                    class="text-ink-slate size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ unit.title }}</span>
                            </button>
                            <LessonsTreeRowMenu
                                v-if="manage"
                                :name="unit.name ?? unit.title"
                                :test-id="`unit-${unit.id}`"
                                :can-edit="course.canEdit !== false"
                                :can-copy="course.canCopy !== false"
                                :can-delete="course.canDelete !== false"
                                @select="onAction(unitTarget(unit), $event)"
                            />
                        </div>

                        <div
                            v-if="unit.expanded"
                            class="border-line ms-[9px] mt-1 space-y-1 border-s ps-3"
                        >
                            <div
                                v-for="(lesson, index) in unit.lessons ?? []"
                                :key="lesson.id"
                                :class="
                                    cn(
                                        'group/row relative flex items-center gap-0.5 rounded-md',
                                        dragging === lesson.id && 'opacity-40',
                                        dropTarget === `l${lesson.id}` &&
                                            dragging !== lesson.id &&
                                            'before:bg-brand-600 before:absolute before:inset-x-1 before:-top-0.5 before:h-0.5 before:rounded-full',
                                    )
                                "
                                :draggable="manage && course.canEdit !== false"
                                :data-test="`lesson-${lesson.id}-row`"
                                @dragstart="onDragStart($event, lesson)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver($event, `l${lesson.id}`)"
                                @drop.prevent="onDrop(unit, index + 1)"
                            >
                                <GripVertical
                                    v-if="manage && course.canEdit !== false"
                                    class="text-ink-faint hidden size-3.5 shrink-0 cursor-grab pointer-fine:block"
                                    :aria-label="$t('Drag to move')"
                                />
                                <button
                                    type="button"
                                    :aria-current="
                                        lesson.active ? 'true' : undefined
                                    "
                                    :data-test="`lesson-${lesson.id}-open`"
                                    :class="
                                        cn(
                                            'focus-visible:ring-brand-600/15 flex min-h-8 min-w-0 flex-1 items-center rounded-md px-3 text-start text-[12.5px] font-medium focus-visible:ring-3 focus-visible:outline-none',
                                            lesson.active
                                                ? 'bg-brand-100/80 text-brand-800 shadow-card'
                                                : 'text-ink-muted hover:bg-brand-50/65',
                                        )
                                    "
                                    @click="openLesson(course, unit, lesson.id)"
                                >
                                    <span class="truncate">{{
                                        lesson.title
                                    }}</span>
                                </button>
                                <LessonsTreeRowMenu
                                    v-if="manage"
                                    :name="lesson.name ?? lesson.title"
                                    :pinned="lesson.active === true"
                                    :test-id="`lesson-${lesson.id}`"
                                    :can-edit="course.canEdit !== false"
                                    :can-copy="course.canEdit !== false"
                                    :can-delete="course.canDelete !== false"
                                    :can-move-up="
                                        course.canEdit !== false && index > 0
                                    "
                                    :can-move-down="
                                        course.canEdit !== false &&
                                        index < (unit.lessons ?? []).length - 1
                                    "
                                    :can-move-to="course.canEdit !== false"
                                    @select="
                                        onLessonAction(
                                            unit,
                                            index,
                                            lesson,
                                            $event,
                                        )
                                    "
                                />
                            </div>

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

        <LessonsTreeActionDialog
            v-model:open="actionOpen"
            :target="actionTarget"
            :mode="actionMode"
        />

        <LessonsMoveDialog
            v-model:open="moveOpen"
            :lesson-id="moveLesson?.id ?? null"
            :lesson-name="moveLesson?.name ?? ''"
        />

        <LessonsAddDialog
            v-model:open="addOpen"
            :mode="addMode"
            :parent-id="addParentId"
            :filters="filters"
        />
    </PanelCard>
</template>
