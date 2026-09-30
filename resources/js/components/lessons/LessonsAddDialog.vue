<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import LevelSelect from '@/components/common/LevelSelect.vue';
import InputError from '@/components/InputError.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeCourse } from '@/routes/courses';
import { store as storeLesson } from '@/routes/lessons';
import { store as storeUnit } from '@/routes/units';
import { tk } from '@/lib/i18n';
import type { LessonFilterOption, LessonsFilters } from '@/types';

/**
 * "+ Add" on the Course Structure panel: a course, a unit inside a course,
 * or a lesson inside a unit (CMS-01). One dialog, three shapes.
 */
type Props = {
    mode: 'course' | 'unit' | 'lesson';
    /** The course (unit mode) or unit (lesson mode) the row is added to. */
    parentId: number | null;
    filters: LessonsFilters;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const kind = ref<Props['mode']>(props.mode);
const department = ref(props.filters.department);
const hotel = ref(props.filters.hotel);
const tone = ref('brand');
const level = ref('');
const course = ref(props.filters.course);
const unit = ref(props.filters.unit);
const withBlocks = ref(true);

watch(
    () => open.value,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        kind.value = props.mode;
        department.value = props.filters.department;
        hotel.value = props.filters.hotel;
        tone.value = 'brand';
        level.value = '';
        course.value =
            props.mode === 'unit' && props.parentId !== null
                ? String(props.parentId)
                : props.filters.course;
        unit.value =
            props.mode === 'lesson' && props.parentId !== null
                ? String(props.parentId)
                : props.filters.unit;
        withBlocks.value = true;
    },
);

const kinds: LessonFilterOption[] = [
    { value: 'course', label: tk('Course') },
    { value: 'unit', label: tk('Unit') },
    { value: 'lesson', label: tk('Lesson') },
];

const tones: LessonFilterOption[] = [
    { value: 'brand', label: tk('Blue') },
    { value: 'aqua', label: tk('Teal') },
    { value: 'success', label: tk('Green') },
    { value: 'warning', label: tk('Amber') },
    { value: 'gold', label: tk('Gold') },
    { value: 'danger', label: tk('Red') },
];

const titles: Record<Props['mode'], { title: string; description: string }> = {
    course: {
        title: tk('Add Course'),
        description: tk(
            'A course groups units and lessons for one department. It starts as a draft.',
        ),
    },
    unit: {
        title: tk('Add Unit'),
        description: tk('A unit groups lessons inside the course.'),
    },
    lesson: {
        title: tk('Add Lesson'),
        description: tk(
            'The lesson starts as a draft with the default nine steps you can reorder or trim.',
        ),
    },
};

const createLabels: Record<Props['mode'], string> = {
    course: tk('Create course'),
    unit: tk('Create unit'),
    lesson: tk('Create lesson'),
};

const action = computed(() => {
    switch (kind.value) {
        case 'course':
            return storeCourse.form();
        case 'unit':
            return storeUnit.form();
        default:
            return storeLesson.form();
    }
});

function onKind(value: AcceptableValue): void {
    if (value === 'course' || value === 'unit' || value === 'lesson') {
        kind.value = value;
    }
}

const selectTrigger =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm text-[13px] shadow-none focus-visible:ring-3';
const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t(titles[kind].title)"
        :description="$t(titles[kind].description)"
    >
        <Form
            :key="kind"
            v-bind="action"
            :options="{ preserveScroll: true }"
            reset-on-success
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="open = false"
        >
            <div class="grid gap-1.5">
                <Label :class="labelClass">{{ $t('What to add') }}</Label>
                <Select :model-value="kind" @update:model-value="onKind">
                    <SelectTrigger
                        :class="selectTrigger"
                        data-test="add-kind-select"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in kinds"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ $t(option.label) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <template v-if="kind === 'course'">
                <input type="hidden" name="department_id" :value="department" />
                <input
                    type="hidden"
                    name="hotel_id"
                    :value="hotel === 'shared' ? '' : hotel"
                />
                <input type="hidden" name="tone" :value="tone" />

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">{{
                            $t('Department')
                        }}</Label>
                        <Select
                            :model-value="department"
                            @update:model-value="
                                department =
                                    typeof $event === 'string'
                                        ? $event
                                        : department
                            "
                        >
                            <SelectTrigger :class="selectTrigger">
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
                        <InputError :message="errors.department_id" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label :class="labelClass">{{ $t('Hotel') }}</Label>
                        <Select
                            :model-value="hotel"
                            @update:model-value="
                                hotel =
                                    typeof $event === 'string' ? $event : hotel
                            "
                        >
                            <SelectTrigger :class="selectTrigger">
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
                        <InputError :message="errors.hotel_id" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label :class="labelClass">{{ $t('Level') }}</Label>
                    <LevelSelect v-model="level" name="level" />
                    <p class="text-ink-slate text-[12px]">
                        {{
                            $t(
                                'Learners see the course when it matches their department and level.',
                            )
                        }}
                    </p>
                    <InputError :message="errors.level" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="course-title" :class="labelClass">{{
                        $t('Course title')
                    }}</Label>
                    <Input
                        id="course-title"
                        name="title"
                        required
                        :aria-invalid="errors.title ? true : undefined"
                        data-test="add-title-input"
                        :class="inputClass"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">{{
                            $t('Colour in the tree')
                        }}</Label>
                        <Select
                            :model-value="tone"
                            @update:model-value="
                                tone =
                                    typeof $event === 'string' ? $event : tone
                            "
                        >
                            <SelectTrigger :class="selectTrigger">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent class="border-line shadow-pop">
                                <SelectItem
                                    v-for="option in tones"
                                    :key="option.value"
                                    :value="option.value"
                                    class="text-[13px]"
                                >
                                    {{ $t(option.label) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.tone" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="course-description" :class="labelClass">
                            {{ $t('Description') }}
                        </Label>
                        <Input
                            id="course-description"
                            name="description"
                            :class="inputClass"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </template>

            <template v-else-if="kind === 'unit'">
                <input type="hidden" name="course_id" :value="course" />
                <div class="grid gap-1.5">
                    <Label :class="labelClass">{{ $t('Course') }}</Label>
                    <Select
                        :model-value="course"
                        @update:model-value="
                            course =
                                typeof $event === 'string' ? $event : course
                        "
                    >
                        <SelectTrigger :class="selectTrigger">
                            <SelectValue :placeholder="$t('Choose a course')" />
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
                    <InputError :message="errors.course_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="unit-title" :class="labelClass">{{
                        $t('Unit title')
                    }}</Label>
                    <Input
                        id="unit-title"
                        name="title"
                        required
                        :aria-invalid="errors.title ? true : undefined"
                        data-test="add-title-input"
                        :class="inputClass"
                    />
                    <InputError :message="errors.title" />
                </div>
            </template>

            <template v-else>
                <input type="hidden" name="unit_id" :value="unit" />
                <input
                    type="hidden"
                    name="blank"
                    :value="withBlocks ? '0' : '1'"
                />
                <div class="grid gap-1.5">
                    <Label :class="labelClass">{{ $t('Unit') }}</Label>
                    <Select
                        :model-value="unit"
                        @update:model-value="
                            unit = typeof $event === 'string' ? $event : unit
                        "
                    >
                        <SelectTrigger :class="selectTrigger">
                            <SelectValue :placeholder="$t('Choose a unit')" />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in filters.units"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.unit_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lesson-title" :class="labelClass">{{
                        $t('Lesson title')
                    }}</Label>
                    <Input
                        id="lesson-title"
                        name="title"
                        required
                        :aria-invalid="errors.title ? true : undefined"
                        data-test="add-title-input"
                        :class="inputClass"
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
                    {{
                        $t(
                            'Start with the default nine steps (Situation → Lesson Complete)',
                        )
                    }}
                </label>
            </template>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-add-button"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-add-button"
                >
                    {{ $t(createLabels[kind]) }}
                </Button>
            </div>
        </Form>
    </LessonsModal>
</template>
