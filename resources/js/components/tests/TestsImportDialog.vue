<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CircleAlert, Download, FileSpreadsheet, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { importMethod, importTemplate } from '@/routes/tests/questions';
import type { TestListItem } from '@/types';

/*
 * "Import Questions" on the Pre-test & Post-test page (TEST-05, TSTM-01;
 * client request 2026-09-26): pick the test, then upload a CSV (Excel →
 * Save As → CSV) or paste rows. The server checks every row first; when any
 * row has a problem nothing is saved and every problem is listed here with
 * its line number.
 */
const props = defineProps<{
    tests: TestListItem[];
    currentTestId: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ imported: [testId: string] }>();

const mode = ref<'file' | 'paste'>('file');
const testId = ref<string>('');
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ file: File | null; rows: string }>({
    file: null,
    rows: '',
});

watch(open, (isOpen) => {
    if (!isOpen) return;

    testId.value =
        props.currentTestId !== null
            ? String(props.currentTestId)
            : (props.tests[0]?.id ?? '');
    form.reset();
    form.clearErrors();
});

const importErrors = computed((): string[] =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('import'))
        .map(([, message]) => message ?? '')
        .filter((message) => message !== ''),
);

const canSubmit = computed(
    () =>
        testId.value !== '' &&
        !form.processing &&
        (mode.value === 'file' ? form.file !== null : form.rows.trim() !== ''),
);

const kinds = [
    ['multiple_choice', 'options A–F, correct = the right letter'],
    ['true_false', 'no options needed, correct = A (true) or B (false)'],
    ['fill_blank', 'use ___ in the question, options, correct letter'],
    ['short_answer', 'a written answer, no options'],
    ['speaking', 'a spoken answer, no options'],
    ['ordering', 'options in the right order, no correct column'],
] as const;

function pick(files: FileList | null | undefined): void {
    const file = files?.[0] ?? null;
    form.file = file;
    form.clearErrors();
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    pick(event.dataTransfer?.files);
}

function submit(): void {
    if (!canSubmit.value) return;

    form.transform((data) =>
        mode.value === 'file'
            ? { file: data.file, rows: '' }
            : { file: null, rows: data.rows },
    ).post(importMethod.url(Number(testId.value)), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('imported', testId.value);
        },
    });
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        title="Import Questions"
        description="Add many questions at once from a CSV file (Excel → Save As → CSV) or by pasting rows."
        size="lg"
    >
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="import-test">Import into</Label>
                <Select v-model="testId">
                    <SelectTrigger id="import-test" class="h-10">
                        <SelectValue placeholder="Choose a test" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="test in tests"
                            :key="test.id"
                            :value="test.id"
                        >
                            {{ test.title }} ·
                            {{
                                test.type === 'post' ? 'Post-test' : 'Pre-test'
                            }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="tests.length === 0"
                    class="text-danger-text text-[12px]"
                >
                    Create a test first, then import its questions.
                </p>
            </div>

            <div
                class="bg-brand-50 flex flex-wrap items-center justify-between gap-3 rounded-md px-4 py-3"
            >
                <p class="text-ink-indigo text-[12.5px] leading-5">
                    <strong class="font-semibold">1.</strong> Download the
                    template &nbsp;<strong class="font-semibold">2.</strong> One
                    question per row &nbsp;<strong class="font-semibold"
                        >3.</strong
                    >
                    Upload it here
                </p>
                <a
                    :href="importTemplate.url()"
                    class="text-brand-700 hover:bg-brand-100 focus-visible:ring-brand-600/30 inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    data-test="download-question-template"
                >
                    <Download class="size-4" aria-hidden="true" />
                    CSV template
                </a>
            </div>

            <div
                role="tablist"
                aria-label="Import from"
                class="border-line bg-surface inline-grid w-fit grid-cols-2 gap-1 rounded-md border p-1"
            >
                <button
                    v-for="option in [
                        { key: 'file', label: 'Upload file' },
                        { key: 'paste', label: 'Paste rows' },
                    ] as const"
                    :key="option.key"
                    type="button"
                    role="tab"
                    :aria-selected="mode === option.key"
                    :class="
                        cn(
                            'h-8 rounded-sm px-3 text-[12.5px] font-semibold transition-colors',
                            mode === option.key
                                ? 'bg-brand-600 text-white'
                                : 'text-brand-700 hover:bg-brand-50',
                        )
                    "
                    @click="mode = option.key"
                >
                    {{ option.label }}
                </button>
            </div>

            <div v-if="mode === 'file'">
                <label
                    for="import-file"
                    :class="
                        cn(
                            'border-line-strong hover:border-brand-400 hover:bg-brand-50/60 flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors',
                            dragging && 'border-brand-600 bg-brand-50',
                        )
                    "
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <FileSpreadsheet
                        v-if="form.file"
                        class="text-excel size-8"
                        aria-hidden="true"
                    />
                    <Upload
                        v-else
                        class="text-brand-600 size-8"
                        aria-hidden="true"
                    />
                    <span
                        v-if="form.file"
                        class="text-ink-night text-[13px] font-semibold"
                    >
                        {{ form.file.name }}
                    </span>
                    <span v-else class="text-ink-indigo text-[13px]">
                        <strong class="text-brand-700 font-semibold"
                            >Choose a CSV file</strong
                        >
                        or drop it here
                    </span>
                    <span class="text-ink-slate text-[11.5px]"
                        >.csv, up to 2 MB, 200 questions</span
                    >
                </label>
                <input
                    id="import-file"
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="sr-only"
                    data-test="import-questions-file"
                    @change="pick(($event.target as HTMLInputElement).files)"
                />
                <button
                    v-if="form.file"
                    type="button"
                    class="text-ink-slate hover:text-danger-text mt-2 inline-flex items-center gap-1 text-[12px]"
                    @click="
                        form.file = null;
                        fileInput && (fileInput.value = '');
                    "
                >
                    <X class="size-3.5" aria-hidden="true" />
                    Remove file
                </button>
            </div>

            <div v-else class="grid gap-1.5">
                <Label for="import-rows">
                    Paste rows (copied from Excel or the template)
                </Label>
                <textarea
                    id="import-rows"
                    v-model="form.rows"
                    rows="7"
                    placeholder="type,question,option_a,option_b,option_c,option_d,option_e,option_f,correct"
                    class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface rounded-sm border px-3 py-2 font-mono text-[12px] leading-5 focus-visible:ring-3 focus-visible:outline-none"
                    data-test="import-questions-rows"
                />
            </div>

            <div
                v-if="
                    importErrors.length || form.errors.file || form.errors.rows
                "
                class="border-danger/30 bg-danger-tint grid gap-1 rounded-md border px-4 py-3"
                role="alert"
            >
                <p
                    class="text-danger-text flex items-center gap-2 text-[13px] font-semibold"
                >
                    <CircleAlert class="size-4" aria-hidden="true" />
                    Nothing was imported. Please fix:
                </p>
                <ul
                    class="text-danger-text max-h-36 list-disc overflow-y-auto ps-6 text-[12.5px] leading-5"
                >
                    <li v-if="form.errors.file">{{ form.errors.file }}</li>
                    <li v-if="form.errors.rows">{{ form.errors.rows }}</li>
                    <li v-for="error in importErrors" :key="error">
                        {{ error }}
                    </li>
                </ul>
            </div>

            <details class="text-ink-slate text-[12px]">
                <summary
                    class="text-brand-700 cursor-pointer font-semibold select-none"
                >
                    Question types you can import
                </summary>
                <ul class="mt-2 grid gap-1">
                    <li v-for="[kind, hint] in kinds" :key="kind">
                        <code class="text-ink-night font-semibold">{{
                            kind
                        }}</code>
                        — {{ hint }}
                    </li>
                </ul>
                <p class="mt-2">
                    Listening, image and video questions need a media file: add
                    those in the editor.
                </p>
            </details>

            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    :disabled="!canSubmit"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 text-white"
                    data-test="import-questions-button"
                >
                    <Upload class="size-4" aria-hidden="true" />
                    {{ form.processing ? 'Importing…' : 'Import questions' }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
