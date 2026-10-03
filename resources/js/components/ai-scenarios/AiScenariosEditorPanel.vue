<script setup lang="ts">
import {
    BookOpen,
    Check,
    ChevronDown,
    CirclePlus,
    ExternalLink,
    Image,
    Link2,
    List,
    ListOrdered,
    Sparkles,
    Trash2,
    User,
} from '@lucide/vue';
import { Link as InertiaLink } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
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
import { lessonsContent } from '@/routes';
import type { AiScenarioEditor, AiScenarioSavePayload } from '@/types';

type Props = {
    editor: AiScenarioEditor;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    save: [payload: AiScenarioSavePayload];
    generate: [];
    'apply-draft': [];
    publish: [];
    'open-instructions': [];
}>();

const title = ref(props.editor.title);
const department = ref(props.editor.department);
const level = ref(props.editor.level);
const description = ref(props.editor.description);
const situation = ref(props.editor.situation ?? '');
const guestRole = ref(props.editor.guestRole);
const employeeRole = ref(props.editor.employeeRole);
const objective = ref(props.editor.objective ?? '');
const openingLine = ref(props.editor.openingLine ?? '');
const goals = ref([...props.editor.objectives]);
const newObjective = ref('');
const addingObjective = ref(false);
const coverPreview = ref<string | null>(null);
const imageName = ref('');
const imageInput = ref<HTMLInputElement | null>(null);
const descriptionInput = ref<HTMLTextAreaElement | null>(null);

watch(
    () => props.editor,
    (editor) => {
        title.value = editor.title;
        department.value = editor.department;
        level.value = editor.level;
        description.value = editor.description;
        situation.value = editor.situation ?? '';
        guestRole.value = editor.guestRole;
        employeeRole.value = editor.employeeRole;
        objective.value = editor.objective ?? '';
        openingLine.value = editor.openingLine ?? '';
        goals.value = [...editor.objectives];
        clearCoverPreview();
        newObjective.value = '';
        addingObjective.value = false;
    },
    { deep: true },
);

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

function save(settings?: AiScenarioSavePayload['settings']): void {
    emit('save', {
        title: title.value.trim(),
        department_id: Number(department.value),
        difficulty: level.value as AiScenarioSavePayload['difficulty'],
        description: description.value.trim(),
        situation: situation.value.trim(),
        ai_role: guestRole.value.trim(),
        employee_role: employeeRole.value.trim(),
        objective: objective.value.trim(),
        opening_line: openingLine.value.trim(),
        goals: goals.value.map((goal) => goal.trim()).filter(Boolean),
        useful_phrases: props.editor.usefulPhrases ?? [],
        ...(settings ? { settings } : {}),
    });
}

defineExpose({ save });

function chooseImage(): void {
    imageInput.value?.click();
}

function onImageSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (
        !file ||
        !file.type.startsWith('image/') ||
        file.size > 5 * 1024 * 1024
    ) {
        input.value = '';
        return;
    }

    clearCoverPreview();
    coverPreview.value = URL.createObjectURL(file);
    imageName.value = file.name;
}

function clearCoverPreview(): void {
    if (coverPreview.value !== null) {
        URL.revokeObjectURL(coverPreview.value);
    }

    coverPreview.value = null;
    imageName.value = '';
}

function addObjective(): void {
    const value = newObjective.value.trim();

    if (value === '') {
        addingObjective.value = true;
        return;
    }

    goals.value.push(value);
    newObjective.value = '';
    addingObjective.value = false;
}

function removeObjective(index: number): void {
    goals.value.splice(index, 1);
}

function formatDescription(
    format: 'bold' | 'italic' | 'underline' | 'list' | 'ordered' | 'link',
): void {
    const textarea = descriptionInput.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = description.value.slice(start, end) || 'text';
    const wrappers: Record<typeof format, [string, string]> = {
        bold: ['**', '**'],
        italic: ['*', '*'],
        underline: ['__', '__'],
        list: ['- ', ''],
        ordered: ['1. ', ''],
        link: ['[', '](https://)'],
    };
    const [before, after] = wrappers[format];
    const replacement = `${before}${selected}${after}`;

    description.value =
        description.value.slice(0, start) +
        replacement +
        description.value.slice(end);

    nextTick(() => {
        textarea.focus();
        textarea.setSelectionRange(
            start + before.length,
            start + before.length + selected.length,
        );
    });
}

onBeforeUnmount(clearCoverPreview);
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
        <!-- minmax(0,1fr): a long lesson name must not widen the column. -->
        <div class="grid grid-cols-[minmax(0,1fr)] gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2
                    class="font-heading text-brand-800 truncate text-base font-semibold"
                >
                    {{ $t('Edit Scenario') }}
                </h2>

                <button
                    type="button"
                    class="bg-success-tint text-success-text inline-flex h-8 items-center gap-2 rounded-md px-3 text-[11.5px] font-semibold"
                    @click="emit('publish')"
                >
                    <span class="bg-success size-2 rounded-full" />
                    {{ editor.status }}
                    <ChevronDown class="size-3.5" aria-hidden="true" />
                </button>
                <Button
                    v-if="editor.aiStatus === 'done' && editor.applyDraftUrl"
                    type="button"
                    variant="outline"
                    class="border-line text-ai hover:bg-ai-tint h-8 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold shadow-none"
                    @click="emit('apply-draft')"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{ $t('Apply AI Draft') }}
                </Button>
                <Button
                    v-else-if="editor.generateUrl"
                    type="button"
                    variant="outline"
                    class="border-line text-ai hover:bg-ai-tint h-8 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold shadow-none"
                    :disabled="
                        editor.aiStatus === 'pending' ||
                        editor.aiStatus === 'running'
                    "
                    @click="emit('generate')"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{
                        editor.aiStatus === 'failed'
                            ? $t('Regenerate with Qwen')
                            : $t('Generate with Qwen')
                    }}
                </Button>
            </div>

            <div
                v-if="editor.aiDraft && editor.aiStatus === 'done'"
                class="border-ai/25 bg-ai-tint/45 rounded-md border px-3 py-2.5"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-ai text-[12px] font-semibold">
                        {{ $t('Qwen draft ready for review') }}
                    </p>
                    <span class="text-ai text-[11px] font-medium">{{
                        $t('Apply only after checking the wording')
                    }}</span>
                </div>
                <p class="text-ink mt-1 text-[12px] leading-[1.45]">
                    {{ String(editor.aiDraft.description ?? '') }}
                </p>
                <p class="text-ink-slate mt-1 text-[11.5px] leading-[1.45]">
                    {{ String(editor.aiDraft.objective ?? '') }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <div class="flex items-center justify-between gap-3">
                    <label
                        for="scenario-title"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        {{ $t('Scenario Title *') }}
                    </label>
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ editor.titleCount }}
                    </span>
                </div>
                <Input
                    id="scenario-title"
                    v-model="title"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Department *') }}
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
                        {{ $t('Level *') }}
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

            <section
                class="border-line bg-brand-50/35 rounded-md border px-3 py-3"
                aria-labelledby="scenario-lesson-connection"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="bg-brand-100 text-brand-700 grid size-8 shrink-0 place-items-center rounded-md"
                        >
                            <BookOpen class="size-4" aria-hidden="true" />
                        </span>
                        <div>
                            <h3
                                id="scenario-lesson-connection"
                                class="text-brand-900 text-[12px] font-semibold"
                            >
                                {{ $t('Lesson connection') }}
                            </h3>
                            <p
                                class="text-ink-slate mt-1 text-[11.5px] leading-[1.45]"
                            >
                                {{
                                    $t(
                                        'Add this scenario to a lesson from its AI Role-play block. The same scenario can be reused in more than one lesson.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <InertiaLink
                        :href="lessonsContent()"
                        class="text-brand-700 hover:bg-brand-100 inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-[11.5px] font-semibold"
                    >
                        {{ $t('Open Lessons & Content') }}
                        <ExternalLink class="size-3.5" aria-hidden="true" />
                    </InertiaLink>
                </div>

                <div
                    v-if="editor.usedInLessons.length"
                    class="mt-3 grid gap-1.5"
                >
                    <InertiaLink
                        v-for="lesson in editor.usedInLessons"
                        :key="`${lesson.id}-${lesson.blockId}`"
                        :href="lesson.url"
                        class="border-line bg-surface text-brand-800 hover:border-brand-300 flex min-h-9 items-center justify-between gap-3 rounded-md border px-2.5 py-2 text-[12px]"
                    >
                        <span class="min-w-0 truncate">
                            <span class="font-semibold">{{
                                lesson.title
                            }}</span>
                            <span class="text-ink-muted ms-1">
                                {{ lesson.course
                                }}<span v-if="lesson.unit">
                                    · {{ lesson.unit }}</span
                                >
                            </span>
                        </span>
                        <span class="text-ink-faint shrink-0">{{
                            lesson.status === 'published'
                                ? $t('Published')
                                : $t('Draft')
                        }}</span>
                    </InertiaLink>
                </div>
                <p v-else class="text-ink-muted mt-3 text-[11.5px]">
                    {{
                        $t(
                            'Not attached to a lesson yet. Open Lessons & Content, add an AI Role-play block, and select this scenario.',
                        )
                    }}
                </p>
            </section>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Scenario Image *') }}
                </label>

                <div class="ai-scenario-image-layout grid gap-3 md:items-start">
                    <img
                        v-if="coverPreview"
                        :src="coverPreview"
                        :alt="$t('Selected scenario cover')"
                        class="border-line aspect-[1200/628] w-full rounded-md border object-cover"
                    />
                    <LessonsMockupCrop
                        v-else
                        :crop="editor.coverCrop"
                        src="/decor/ai-scenarios-mockup.jpg"
                        :alt="$t('Guest check-in scenario cover')"
                        class="border-line w-full rounded-md border"
                    />

                    <div class="grid gap-2">
                        <input
                            ref="imageInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="onImageSelected"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                            @click="chooseImage"
                        >
                            <Image class="size-3.5" aria-hidden="true" />
                            {{ $t('Change Image') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-danger-text hover:bg-danger-tint h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                            :disabled="
                                coverPreview === null && imageName === ''
                            "
                            @click="clearCoverPreview"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                            {{ $t('Remove') }}
                        </Button>
                        <p class="text-ink-faint text-[11px] leading-4.5">
                            <span
                                v-if="imageName"
                                class="text-success-text block truncate"
                                >{{ imageName }}</span
                            >
                            {{ $t('Recommended size: 1200 x 628 px') }}<br />
                            {{ $t('(JPG, PNG - Max 5MB)') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Scenario Description *') }}
                </label>

                <div class="border-line overflow-hidden rounded-md border">
                    <div
                        class="border-line bg-surface flex flex-wrap items-center gap-1 border-b px-2 py-1.5"
                    >
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border text-[12px] font-bold"
                            :aria-label="$t('Bold description')"
                            @click="formatDescription('bold')"
                        >
                            B
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border text-[12px] italic"
                            :aria-label="$t('Italic description')"
                            @click="formatDescription('italic')"
                        >
                            I
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border text-[12px] underline"
                            :aria-label="$t('Underline description')"
                            @click="formatDescription('underline')"
                        >
                            U
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                            :aria-label="$t('Bulleted list')"
                            @click="formatDescription('list')"
                        >
                            <List class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                            :aria-label="$t('Numbered list')"
                            @click="formatDescription('ordered')"
                        >
                            <ListOrdered class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                            :aria-label="$t('Insert link')"
                            @click="formatDescription('link')"
                        >
                            <Link2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="bg-surface px-3 py-2">
                        <textarea
                            ref="descriptionInput"
                            rows="4"
                            class="text-ink min-h-[94px] w-full resize-none border-0 bg-transparent p-0 text-[13px] leading-[1.6] outline-none"
                            v-model="description"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ description.length }}/500
                    </span>
                </div>
            </div>

            <!-- What the AI acts out (client report 2026-10-02: the roles
                 could not be written, so every conversation fell back to
                 the same check-in). Each field goes into the AI's brief. -->
            <div class="grid gap-1.5">
                <label
                    for="scenario-situation"
                    class="text-brand-900 text-[12px] font-semibold"
                >
                    {{ $t('Situation *') }}
                </label>
                <textarea
                    id="scenario-situation"
                    v-model="situation"
                    rows="3"
                    maxlength="2000"
                    :placeholder="
                        $t(
                            'What is happening? Example: A guest calls reception at 11 pm because the air conditioning in room 305 is not working.',
                        )
                    "
                    data-test="scenario-situation"
                    class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-[84px] w-full resize-y rounded-md border px-3 py-2 text-[13px] leading-[1.6] focus-visible:ring-3 focus-visible:outline-none"
                />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <section
                    class="border-line bg-surface grid gap-2 rounded-md border px-3 py-3"
                >
                    <label
                        for="scenario-ai-role"
                        class="text-brand-900 flex items-center gap-2 text-[12px] font-semibold"
                    >
                        <span
                            class="bg-ai/12 text-ai rounded-pill grid size-7 shrink-0 place-items-center"
                            aria-hidden="true"
                        >
                            <Sparkles class="size-3.5" />
                        </span>
                        {{ $t('AI Role (Guest) *') }}
                    </label>
                    <textarea
                        id="scenario-ai-role"
                        v-model="guestRole"
                        rows="4"
                        maxlength="2000"
                        :placeholder="
                            $t(
                                'Who the AI plays and how they behave. Example: A tired business guest, impatient, who wants a quick solution.',
                            )
                        "
                        data-test="scenario-ai-role"
                        class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-[96px] w-full resize-y rounded-md border px-3 py-2 text-[12.5px] leading-[1.5] focus-visible:ring-3 focus-visible:outline-none"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 justify-self-start rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="emit('open-instructions')"
                    >
                        <Sparkles class="size-3.5" aria-hidden="true" />
                        {{ $t('Edit AI Instructions') }}
                    </Button>
                </section>

                <section
                    class="border-line bg-surface grid gap-2 rounded-md border px-3 py-3"
                >
                    <label
                        for="scenario-employee-role"
                        class="text-brand-900 flex items-center gap-2 text-[12px] font-semibold"
                    >
                        <span
                            class="bg-success-tint text-success rounded-pill grid size-7 shrink-0 place-items-center"
                            aria-hidden="true"
                        >
                            <User class="size-3.5" />
                        </span>
                        {{ $t('Employee Role (User) *') }}
                    </label>
                    <textarea
                        id="scenario-employee-role"
                        v-model="employeeRole"
                        rows="4"
                        maxlength="2000"
                        :placeholder="
                            $t(
                                'Who the learner is. Example: The night receptionist who must calm the guest and offer a solution.',
                            )
                        "
                        data-test="scenario-employee-role"
                        class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-[96px] w-full resize-y rounded-md border px-3 py-2 text-[12.5px] leading-[1.5] focus-visible:ring-3 focus-visible:outline-none"
                    />
                </section>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <label
                        for="scenario-objective"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        {{ $t('Main objective *') }}
                    </label>
                    <Input
                        id="scenario-objective"
                        v-model="objective"
                        maxlength="500"
                        :placeholder="
                            $t(
                                'Example: Solve the problem and keep the guest happy.',
                            )
                        "
                        data-test="scenario-objective"
                        class="border-line h-10 text-[12.5px]"
                    />
                </div>
                <div class="grid gap-1.5">
                    <label
                        for="scenario-opening-line"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        {{ $t("Guest's first line (calls)") }}
                    </label>
                    <Input
                        id="scenario-opening-line"
                        v-model="openingLine"
                        maxlength="200"
                        :placeholder="
                            $t(
                                'Example: Hello, my air conditioning is not working!',
                            )
                        "
                        data-test="scenario-opening-line"
                        class="border-line h-10 text-[12.5px]"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Learning Objectives *') }}
                    </h2>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="addingObjective = !addingObjective"
                    >
                        <CirclePlus class="size-3.5" aria-hidden="true" />
                        {{ $t('Add Objective') }}
                    </Button>
                </div>

                <div v-if="addingObjective" class="flex gap-2">
                    <Input
                        v-model="newObjective"
                        autofocus
                        :placeholder="$t('Add a learning objective')"
                        class="border-line h-10 text-[12px]"
                        @keyup.enter="addObjective"
                    />
                    <Button
                        type="button"
                        class="bg-brand-600 hover:bg-brand-700 h-10 shrink-0 px-3 text-xs font-semibold text-white"
                        @click="addObjective"
                    >
                        {{ $t('Add') }}
                    </Button>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="(objective, index) in goals"
                        :key="`${objective}-${index}`"
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
                            :aria-label="
                                $t('Remove :item', { item: objective })
                            "
                            @click="removeObjective(index)"
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
