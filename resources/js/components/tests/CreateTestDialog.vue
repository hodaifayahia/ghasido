<script setup lang="ts">
import { ref, watch } from 'vue';
import { CirclePlus } from '@lucide/vue';
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
import type {
    CreateTestPayload,
    TestsSelectOption,
    TestVariant,
} from '@/types';

const props = defineProps<{
    open: boolean;
    departments: TestsSelectOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    create: [payload: CreateTestPayload];
}>();

const title = ref('');
const type = ref<TestVariant>('pre');
const department = ref('reception');
const timeLimit = ref('25');

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        title.value = '';
        type.value = 'pre';
        department.value =
            props.departments.find((option) => option.value === 'reception')
                ?.value ??
            props.departments.find(
                (option) => option.value !== 'all-departments',
            )?.value ??
            'all-departments';
        timeLimit.value = '25';
    },
);

function create(): void {
    const cleanTitle = title.value.trim();
    if (cleanTitle === '') return;

    emit('create', {
        title: cleanTitle,
        type: type.value,
        department: department.value,
        timeLimit: timeLimit.value,
    });
}
</script>

<template>
    <LessonsModal
        :open="open"
        :title="$t('Create New Test')"
        :description="
            $t('Set up the test details, then build and preview its questions.')
        "
        size="md"
        @update:open="emit('update:open', $event)"
    >
        <form class="mt-5 space-y-4" @submit.prevent="create">
            <div class="space-y-1.5">
                <Label
                    for="new-test-title"
                    class="text-brand-900 text-xs font-semibold"
                >
                    {{ $t('Test title') }} <span class="text-danger">*</span>
                </Label>
                <Input
                    id="new-test-title"
                    v-model="title"
                    autofocus
                    :placeholder="$t('e.g. Reception Pre-test')"
                    class="border-line h-11 text-sm"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label class="text-brand-900 text-xs font-semibold">{{
                        $t('Test type')
                    }}</Label>
                    <Select v-model="type">
                        <SelectTrigger class="border-line h-11 text-sm">
                            <SelectValue :placeholder="$t('Choose type')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="pre">{{
                                $t('Pre-test')
                            }}</SelectItem>
                            <SelectItem value="post">{{
                                $t('Post-test')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-brand-900 text-xs font-semibold">{{
                        $t('Department')
                    }}</Label>
                    <Select v-model="department">
                        <SelectTrigger class="border-line h-11 text-sm">
                            <SelectValue
                                :placeholder="$t('Choose department')"
                            />
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
            </div>

            <div class="space-y-1.5">
                <Label
                    for="new-test-time"
                    class="text-brand-900 text-xs font-semibold"
                >
                    {{ $t('Time limit') }}
                    <span class="text-ink-muted font-normal">{{
                        $t('(minutes, optional)')
                    }}</span>
                </Label>
                <Input
                    id="new-test-time"
                    v-model="timeLimit"
                    type="number"
                    min="0"
                    placeholder="25"
                    class="border-line h-11 text-sm"
                />
            </div>

            <div
                class="border-line bg-brand-50/35 flex gap-3 rounded-md border p-3"
            >
                <CirclePlus
                    class="text-brand-600 mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p class="text-ink-slate text-xs leading-5">
                    {{
                        $t(
                            'After creating it, the question builder will open so you can add questions and media.',
                        )
                    }}
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
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-xs font-semibold text-white"
                    :disabled="title.trim() === ''"
                >
                    {{ $t('Create & Open Builder') }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
