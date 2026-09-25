<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create as createLessonPage } from '@/routes/lessons-content';
import type { LessonFilterOption } from '@/types';

type Props = {
    departments: LessonFilterOption[];
    defaultDepartment: string;
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { required: true });
const department = ref(props.defaultDepartment);

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen) {
            department.value = props.defaultDepartment;
        }
    },
);

function continueToCreate(): void {
    if (department.value === '') {
        return;
    }

    open.value = false;
    router.visit(
        createLessonPage.url({
            query: { department: department.value },
        }),
    );
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        title="Create a lesson"
        description="Choose the department first. The lesson details are completed on the next page."
    >
        <div class="mt-2 grid gap-4">
            <div class="grid gap-1.5">
                <label
                    for="create-lesson-department"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    Department <span class="text-danger-text">*</span>
                </label>
                <Select v-model="department">
                    <SelectTrigger
                        id="create-lesson-department"
                        class="border-line text-ink bg-surface h-10 w-full rounded-sm text-[13px] shadow-none"
                        data-test="create-lesson-department"
                    >
                        <SelectValue placeholder="Choose a department" />
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

            <p
                class="text-ink-slate bg-brand-50/60 rounded-md px-3 py-2 text-[12px]"
            >
                Hotel scope is not required. You will choose the course, unit,
                title, and lesson steps on the next page.
            </p>

            <div class="mt-1 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-create-lesson"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="department === ''"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-10 rounded-md px-4 text-[12.5px] font-semibold text-white"
                    data-test="continue-create-lesson"
                    @click="continueToCreate"
                >
                    Continue
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
