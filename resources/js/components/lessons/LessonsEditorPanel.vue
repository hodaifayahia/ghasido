<script setup lang="ts">
import {
    Check,
    ChevronDown,
    CirclePlus,
    Image,
    Link2,
    List,
    ListOrdered,
    MoreHorizontal,
    Trash2,
} from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { LessonEditor } from '@/types';

type Props = {
    editor: LessonEditor;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
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
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between gap-3">
                    <label
                        for="lesson-title"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        Lesson Title *
                    </label>
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ editor.titleCount }}
                    </span>
                </div>
                <Input
                    id="lesson-title"
                    :default-value="editor.title"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                />
            </div>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    Lesson Image (Cover) *
                </label>

                <LessonsMockupCrop
                    :crop="editor.coverCrop"
                    alt="Greeting Guests lesson cover"
                    class="border-line w-full rounded-md border"
                />

                <div class="flex flex-wrap items-center gap-2 pt-0.5">
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
                </div>
            </div>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    Lesson Introduction *
                </label>

                <div class="border-line overflow-hidden rounded-md border">
                    <div
                        class="border-line bg-surface flex flex-wrap items-center gap-1 border-b px-2 py-1.5"
                    >
                        <button
                            type="button"
                            class="text-ink border-line flex h-7 items-center gap-1 rounded-md border px-2 text-[11.5px] font-medium"
                        >
                            Paragraph
                            <ChevronDown class="size-3.5" aria-hidden="true" />
                        </button>
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
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                        >
                            <Image class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border"
                        >
                            <MoreHorizontal
                                class="size-3.5"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div class="bg-surface px-3 py-2">
                        <textarea
                            rows="5"
                            class="text-ink min-h-[108px] w-full resize-none border-0 bg-transparent p-0 text-[13px] leading-[1.65] outline-none"
                            :value="editor.introduction"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ editor.introductionCount }}
                    </span>
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-brand-900 text-[12px] font-semibold">
                        Lesson Objectives
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
