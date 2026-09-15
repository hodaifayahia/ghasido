<script setup lang="ts">
import {
    Check,
    ChevronDown,
    CirclePlus,
    Image,
    Link2,
    List,
    ListOrdered,
    Sparkles,
    Trash2,
    User,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { AiScenarioEditor } from '@/types';

type Props = {
    editor: AiScenarioEditor;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const department = ref(props.editor.department);
const level = ref(props.editor.level);

function onSelect(
    target: 'department' | 'level',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    level.value = value;
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-3',
                props.class,
            )
        "
    >
        <div class="grid gap-4">
            <div class="flex items-center justify-between gap-3">
                <h2
                    class="font-heading text-brand-800 truncate text-base font-semibold"
                >
                    Edit Scenario
                </h2>

                <button
                    type="button"
                    class="bg-success-tint text-success-text inline-flex h-8 items-center gap-2 rounded-md px-3 text-[11.5px] font-semibold"
                >
                    <span class="bg-success size-2 rounded-full" />
                    {{ editor.status }}
                    <ChevronDown class="size-3.5" aria-hidden="true" />
                </button>
            </div>

            <div class="grid gap-1.5">
                <div class="flex items-center justify-between gap-3">
                    <label
                        for="scenario-title"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        Scenario Title *
                    </label>
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ editor.titleCount }}
                    </span>
                </div>
                <Input
                    id="scenario-title"
                    :default-value="editor.title"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        Department *
                    </label>
                    <Select
                        :model-value="department"
                        @update:model-value="onSelect('department', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in editor.departments"
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
                    <label class="text-brand-900 text-[12px] font-semibold">
                        Level *
                    </label>
                    <Select
                        :model-value="level"
                        @update:model-value="onSelect('level', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in editor.levels"
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

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    Scenario Image *
                </label>

                <div class="ai-scenario-image-layout grid gap-3 md:items-start">
                    <LessonsMockupCrop
                        :crop="editor.coverCrop"
                        src="/decor/ai-scenarios-mockup.jpg"
                        alt="Guest check-in scenario cover"
                        class="border-line w-full rounded-md border"
                    />

                    <div class="grid gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        >
                            <Image class="size-3.5" aria-hidden="true" />
                            Change Image
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-danger-text hover:bg-danger-tint h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                            Remove
                        </Button>
                        <p class="text-ink-faint text-[11px] leading-4.5">
                            Recommended size: 1200 x 628 px<br />
                            (JPG, PNG - Max 5MB)
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    Scenario Description *
                </label>

                <div class="border-line overflow-hidden rounded-md border">
                    <div
                        class="border-line bg-surface flex flex-wrap items-center gap-1 border-b px-2 py-1.5"
                    >
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border text-[12px] font-bold"
                        >
                            B
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border text-[12px] italic"
                        >
                            I
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border text-[12px] underline"
                        >
                            U
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                        >
                            <List class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                        >
                            <ListOrdered class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                        >
                            <Link2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="bg-surface px-3 py-2">
                        <textarea
                            rows="4"
                            class="text-ink min-h-[94px] w-full resize-none border-0 bg-transparent p-0 text-[13px] leading-[1.6] outline-none"
                            :value="editor.description"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ editor.descriptionCount }}
                    </span>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <section
                    class="border-line bg-surface rounded-md border px-3 py-3"
                >
                    <h3 class="text-brand-900 text-[12px] font-semibold">
                        AI Role (Guest) *
                    </h3>
                    <div class="mt-2 flex items-start gap-2.5">
                        <span
                            class="bg-ai/12 text-ai rounded-pill grid size-8 shrink-0 place-items-center"
                        >
                            <Sparkles class="size-4" aria-hidden="true" />
                        </span>
                        <p class="text-ink text-[12px] leading-[1.45]">
                            {{ editor.guestRole }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 mt-3 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    >
                        <Sparkles class="size-3.5" aria-hidden="true" />
                        Edit AI Instructions
                    </Button>
                </section>

                <section
                    class="border-line bg-surface rounded-md border px-3 py-3"
                >
                    <h3 class="text-brand-900 text-[12px] font-semibold">
                        Employee Role (User) *
                    </h3>
                    <div class="mt-2 flex items-start gap-2.5">
                        <span
                            class="bg-success-tint text-success rounded-pill grid size-8 shrink-0 place-items-center"
                        >
                            <User class="size-4" aria-hidden="true" />
                        </span>
                        <p class="text-ink text-[12px] leading-[1.45]">
                            {{ editor.employeeRole }}
                        </p>
                    </div>
                </section>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-brand-900 text-[12px] font-semibold">
                        Learning Objectives *
                    </h2>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    >
                        <CirclePlus class="size-3.5" aria-hidden="true" />
                        Add Objective
                    </Button>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="objective in editor.objectives"
                        :key="objective"
                        class="border-line bg-surface flex min-h-11 items-center gap-3 rounded-md border px-3 py-2"
                    >
                        <span
                            class="bg-brand-100 text-brand-700 rounded-pill grid size-6 shrink-0 place-items-center"
                        >
                            <Check class="size-3.5" aria-hidden="true" />
                        </span>
                        <span class="text-ink min-w-0 flex-1 text-[13px]">
                            {{ objective }}
                        </span>
                        <button
                            type="button"
                            class="text-ink-faint hover:bg-brand-50 inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                            :aria-label="`Remove ${objective}`"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@media (min-width: 768px) {
    .ai-scenario-image-layout {
        grid-template-columns: minmax(0, 1fr) 150px;
    }
}
</style>
