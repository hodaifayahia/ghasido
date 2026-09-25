<script setup lang="ts">
import { ref, watch } from 'vue';
import { Bot } from '@lucide/vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AiScenarioSelectOption, CreateAiScenarioPayload } from '@/types';

const props = defineProps<{
    open: boolean;
    departments: AiScenarioSelectOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    create: [payload: CreateAiScenarioPayload];
}>();

const title = ref('');
const department = ref('reception');
const level = ref('beginner');

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        title.value = '';
        department.value =
            props.departments.find((option) => option.value === 'reception')
                ?.value ??
            props.departments.find(
                (option) => option.value !== 'all-departments',
            )?.value ??
            'reception';
        level.value = 'beginner';
    },
);

function create(): void {
    const cleanTitle = title.value.trim();
    if (cleanTitle === '') return;

    emit('create', {
        title: cleanTitle,
        department: department.value,
        level: level.value,
    });
}
</script>

<template>
    <LessonsModal
        :open="open"
        title="Create New Scenario"
        description="Start with the situation details, then build the roles and conversation objectives."
        size="md"
        @update:open="emit('update:open', $event)"
    >
        <form class="mt-5 space-y-4" @submit.prevent="create">
            <div class="space-y-1.5">
                <Label
                    for="new-scenario-title"
                    class="text-brand-900 text-xs font-semibold"
                >
                    Scenario title <span class="text-danger">*</span>
                </Label>
                <Input
                    id="new-scenario-title"
                    v-model="title"
                    autofocus
                    placeholder="e.g. Guest Check-in"
                    class="border-line h-11 text-sm"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label class="text-brand-900 text-xs font-semibold">
                        Department
                    </Label>
                    <Select v-model="department">
                        <SelectTrigger class="border-line h-11 text-sm">
                            <SelectValue placeholder="Choose department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in departments.filter(
                                    (item) => item.value !== 'all-departments',
                                )"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-brand-900 text-xs font-semibold">
                        English level
                    </Label>
                    <Select v-model="level">
                        <SelectTrigger class="border-line h-11 text-sm">
                            <SelectValue placeholder="Choose level" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="beginner">Beginner</SelectItem>
                            <SelectItem value="elementary"
                                >Elementary</SelectItem
                            >
                            <SelectItem value="intermediate"
                                >Intermediate</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div
                class="border-line bg-brand-50/35 flex gap-3 rounded-md border p-3"
            >
                <Bot
                    class="text-brand-600 mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p class="text-ink-slate text-xs leading-5">
                    After creating it, the scenario builder will open so you can
                    add the image, hotel roles, learning objectives and AI
                    instructions.
                </p>
            </div>

            <div
                class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 rounded-md px-4 text-xs font-semibold"
                    @click="emit('update:open', false)"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-xs font-semibold text-white"
                    :disabled="title.trim() === ''"
                >
                    Create &amp; Open Builder
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
