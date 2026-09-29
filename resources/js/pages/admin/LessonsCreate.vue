<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeft, Check, CirclePlus } from '@lucide/vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import PanelCard from '@/components/common/PanelCard.vue';
import TransText from '@/components/common/TransText.vue';

import InputError from '@/components/InputError.vue';
import MeaningFieldButton from '@/components/meaning/MeaningFieldButton.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard, lessonsContent } from '@/routes';
import { create as createLessonPage } from '@/routes/lessons-content';
import { store as storeLesson } from '@/routes/lessons';
import type { LessonCreateCourse, LessonFilterOption } from '@/types';
import { tk } from '@/lib/i18n';

type Props = {
    departments: LessonFilterOption[];
    departmentId: string | null;
    departmentName: string | null;
    courses: LessonCreateCourse[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Lessons & Content'), href: lessonsContent() },
            { title: tk('Create Lesson'), href: createLessonPage() },
        ],
    },
});

const department = ref(props.departmentId ?? '');
// Course and unit are optional, and a new one can be typed here (client
// request 2026-09-29). NONE = the department's "General" course / the
// course's first unit; NEW = create one with the typed title.
const NONE = 'none';
const NEW = 'new';
const courseId = ref(String(props.courses[0]?.id ?? NONE));
const unitId = ref(String(props.courses[0]?.units[0]?.id ?? NONE));
const newCourseTitle = ref('');
const newUnitTitle = ref('');
const lessonTitle = ref('');
const withBlocks = ref(true);

const course = computed(() =>
    props.courses.find((row) => String(row.id) === courseId.value),
);
const units = computed(() => course.value?.units ?? []);
const isId = (value: string): boolean => /^\d+$/.test(value);

watch(courseId, () => {
    unitId.value = String(units.value[0]?.id ?? NONE);
});

function onDepartment(value: string): void {
    if (value === department.value) {
        return;
    }

    router.get(
        createLessonPage.url({ query: { department: value } }),
        {},
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('Create Lesson')"
            :description="
                $t(
                    'Set the lesson location and title, then edit its content and employee steps.',
                )
            "
        />

        <TransText
            v-if="departmentName"
            tag="p"
            text="Department: :name"
            class="text-ink-slate -mt-1 text-[12.5px]"
        >
            <template #name>
                <span class="text-brand-900 font-semibold">{{
                    departmentName
                }}</span>
            </template>
        </TransText>

        <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <PanelCard
                :title="$t('Lesson details')"
                title-id="create-lesson-title"
                class="px-4 py-4 md:px-5"
                body-class="mt-4"
            >
                <Form
                    v-bind="storeLesson.form()"
                    :options="{ preserveScroll: true }"
                    class="grid gap-5"
                    v-slot="{ errors, processing }"
                >
                    <!-- Course and unit are optional (client request
                         2026-09-29): without them the lesson goes into the
                         department's "General" course. -->
                    <input
                        type="hidden"
                        name="department_id"
                        :value="department"
                    />
                    <input
                        type="hidden"
                        name="course_id"
                        :value="isId(courseId) ? courseId : ''"
                    />
                    <input
                        type="hidden"
                        name="unit_id"
                        :value="isId(unitId) ? unitId : ''"
                    />
                    <input
                        type="hidden"
                        name="blank"
                        :value="withBlocks ? '0' : '1'"
                    />

                    <div class="grid gap-1.5">
                        <label
                            for="create-department"
                            class="text-brand-900 text-[12px] font-semibold"
                        >
                            {{ $t('Department') }}
                            <span class="text-danger-text">*</span>
                        </label>
                        <Select
                            :model-value="department"
                            @update:model-value="
                                onDepartment(String($event ?? ''))
                            "
                        >
                            <SelectTrigger
                                id="create-department"
                                class="border-line text-ink bg-surface h-10 rounded-md text-[13px] shadow-none"
                                data-test="lesson-create-department"
                            >
                                <SelectValue
                                    :placeholder="$t('Choose a department')"
                                />
                            </SelectTrigger>
                            <SelectContent class="border-line shadow-pop">
                                <SelectItem
                                    v-for="option in departments"
                                    :key="option.value"
                                    :value="option.value"
                                    class="text-[13px]"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-1.5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-2"
                        >
                            <label
                                for="create-course"
                                class="text-brand-900 text-[12px] font-semibold"
                            >
                                {{ $t('Course') }}
                                <span class="text-ink-muted font-normal">{{
                                    $t('(optional)')
                                }}</span>
                            </label>
                            <MeaningFieldButton
                                v-if="courseId === NEW"
                                :text="newCourseTitle"
                                :label="$t('New course title')"
                                class="-me-1.5"
                            />
                        </div>
                        <Select v-model="courseId">
                            <SelectTrigger
                                id="create-course"
                                class="border-line text-ink bg-surface h-10 rounded-md text-[13px] shadow-none"
                                data-test="lesson-create-course"
                            >
                                <SelectValue
                                    :placeholder="$t('Choose a course')"
                                />
                            </SelectTrigger>
                            <SelectContent class="border-line shadow-pop">
                                <SelectItem :value="NONE" class="text-[13px]">
                                    {{ $t('None (use “General”)') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="row in courses"
                                    :key="row.id"
                                    :value="String(row.id)"
                                    class="text-[13px]"
                                >
                                    {{ row.title }}
                                </SelectItem>
                                <SelectItem
                                    :value="NEW"
                                    class="text-brand-700 text-[13px] font-semibold"
                                >
                                    {{ $t('+ New course…') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            v-if="courseId === NEW"
                            v-model="newCourseTitle"
                            name="new_course_title"
                            required
                            maxlength="120"
                            :placeholder="$t('New course title')"
                            :aria-label="$t('New course title')"
                            data-test="lesson-create-new-course"
                            class="border-line text-ink bg-surface h-10 rounded-md text-[13px] shadow-none"
                        />
                        <InputError :message="errors.new_course_title" />
                        <p
                            v-if="courses.length === 0"
                            class="text-ink-muted text-[12px]"
                        >
                            {{
                                $t(
                                    'No course yet: the lesson goes into a “General” course for this department. You can move it later.',
                                )
                            }}
                        </p>
                        <InputError :message="errors.course_id" />
                    </div>

                    <div class="grid gap-1.5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-2"
                        >
                            <label
                                for="create-unit"
                                class="text-brand-900 text-[12px] font-semibold"
                            >
                                {{ $t('Unit') }}
                                <span class="text-ink-muted font-normal">{{
                                    $t('(optional)')
                                }}</span>
                            </label>
                            <MeaningFieldButton
                                v-if="unitId === NEW"
                                :text="newUnitTitle"
                                :label="$t('New unit title')"
                                class="-me-1.5"
                            />
                        </div>
                        <Select v-model="unitId">
                            <SelectTrigger
                                id="create-unit"
                                class="border-line text-ink bg-surface h-10 rounded-md text-[13px] shadow-none"
                                data-test="lesson-create-unit"
                            >
                                <SelectValue
                                    :placeholder="$t('Choose a unit')"
                                />
                            </SelectTrigger>
                            <SelectContent class="border-line shadow-pop">
                                <SelectItem :value="NONE" class="text-[13px]">
                                    {{
                                        courseId === NEW || units.length === 0
                                            ? $t('None (use “General”)')
                                            : $t('None (first unit)')
                                    }}
                                </SelectItem>
                                <SelectItem
                                    v-for="unit in units"
                                    :key="unit.id"
                                    :value="String(unit.id)"
                                    class="text-[13px]"
                                >
                                    {{ unit.title }}
                                </SelectItem>
                                <SelectItem
                                    :value="NEW"
                                    class="text-brand-700 text-[13px] font-semibold"
                                >
                                    {{ $t('+ New unit…') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            v-if="unitId === NEW"
                            v-model="newUnitTitle"
                            name="new_unit_title"
                            required
                            maxlength="120"
                            :placeholder="$t('New unit title')"
                            :aria-label="$t('New unit title')"
                            data-test="lesson-create-new-unit"
                            class="border-line text-ink bg-surface h-10 rounded-md text-[13px] shadow-none"
                        />
                        <InputError :message="errors.new_unit_title" />
                        <InputError :message="errors.unit_id" />
                        <InputError :message="errors.department_id" />
                    </div>

                    <div class="grid gap-1.5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-2"
                        >
                            <label
                                for="create-lesson-title"
                                class="text-brand-900 text-[12px] font-semibold"
                            >
                                {{ $t('Lesson title') }}
                                <span class="text-danger-text">*</span>
                            </label>
                            <MeaningFieldButton
                                :text="lessonTitle"
                                :label="$t('Lesson title')"
                                class="-me-1.5"
                            />
                        </div>
                        <Input
                            id="create-lesson-title"
                            v-model="lessonTitle"
                            name="title"
                            required
                            maxlength="120"
                            :placeholder="$t('e.g. Handling a room request')"
                            :aria-invalid="errors.title ? true : undefined"
                            data-test="lesson-create-title"
                            class="border-line text-ink bg-surface h-10 rounded-md text-[13px] shadow-none"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <label
                        class="text-ink flex min-h-11 items-center gap-2.5 text-[13px]"
                    >
                        <Checkbox
                            :model-value="withBlocks"
                            @update:model-value="withBlocks = $event === true"
                        />
                        {{ $t('Start with the default nine employee steps') }}
                    </label>

                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            as-child
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                        >
                            <a :href="lessonsContent.url()">
                                <ArrowLeft class="size-4" aria-hidden="true" />
                                {{ $t('Cancel') }}
                            </a>
                        </Button>
                        <Button
                            type="submit"
                            :disabled="processing || department === ''"
                            class="bg-brand-600 hover:bg-brand-700 shadow-btn h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white"
                            data-test="submit-create-lesson"
                        >
                            <CirclePlus class="size-4" aria-hidden="true" />
                            {{ $t('Create Lesson') }}
                        </Button>
                    </div>
                </Form>
            </PanelCard>

            <PanelCard
                :title="$t('What happens next')"
                title-id="create-lesson-next"
                class="h-fit px-4 py-4 md:px-5"
                body-class="mt-3"
            >
                <ul class="text-ink-slate grid gap-3 text-[12.5px]">
                    <li class="flex items-start gap-2">
                        <Check class="text-success mt-0.5 size-4 shrink-0" />
                        {{ $t('The lesson is saved as a draft.') }}
                    </li>
                    <li class="flex items-start gap-2">
                        <Check class="text-success mt-0.5 size-4 shrink-0" />
                        {{
                            $t(
                                'The default employee steps are added automatically.',
                            )
                        }}
                    </li>
                    <li class="flex items-start gap-2">
                        <Check class="text-success mt-0.5 size-4 shrink-0" />
                        {{
                            $t(
                                'You are taken to the lesson editor to add content.',
                            )
                        }}
                    </li>
                </ul>
            </PanelCard>
        </div>
    </div>
</template>
