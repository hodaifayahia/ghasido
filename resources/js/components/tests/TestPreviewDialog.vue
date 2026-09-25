<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { Button } from '@/components/ui/button';
import type { TestPreview } from '@/types';

const props = defineProps<{
    open: boolean;
    preview: TestPreview;
    title: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const index = ref(0);

watch(
    () => props.open,
    (open) => {
        if (open) index.value = 0;
    },
);

const question = computed(() => props.preview.questions[index.value] ?? null);

function move(delta: number): void {
    const total = props.preview.questions.length;
    if (total === 0) return;
    index.value = (index.value + delta + total) % total;
}
</script>

<template>
    <LessonsModal
        :open="open"
        :title="`${title} preview`"
        description="This is the employee-facing question preview. No answer is submitted."
        size="lg"
        @update:open="emit('update:open', $event)"
    >
        <div v-if="question" class="mt-3 grid gap-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-ink-slate text-xs font-semibold">
                    Question {{ index + 1 }} of {{ preview.questions.length }}
                </span>
                <div class="flex gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        class="size-8 p-0"
                        aria-label="Previous question"
                        @click="move(-1)"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="size-8 p-0"
                        aria-label="Next question"
                        @click="move(1)"
                    >
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </div>
            <div
                class="border-line grid gap-4 rounded-lg border p-4 sm:grid-cols-[180px_minmax(0,1fr)]"
            >
                <img
                    v-if="question.media.image"
                    :src="question.media.image.url"
                    :alt="
                        question.media.image.alt || question.media.image.label
                    "
                    class="border-line aspect-square w-full rounded-md border object-cover"
                />
                <LessonsMockupCrop
                    v-else
                    :crop="
                        question.imageCrop ?? {
                            x: 208,
                            y: 282,
                            width: 46,
                            height: 47,
                        }
                    "
                    src="/decor/tests-mockup.jpg"
                    alt="Question illustration"
                    class="border-line aspect-square w-full rounded-md border"
                />
                <div>
                    <p class="text-ink text-base leading-6 font-semibold">
                        {{ question.text }}
                    </p>
                    <div class="mt-4 grid gap-2">
                        <div
                            v-for="option in question.options"
                            :key="option.id"
                            class="border-line text-ink-slate flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <span
                                class="border-line-strong size-4 rounded-full border-2"
                            />
                            {{ option.text }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p
            v-else
            class="text-ink-slate mt-3 rounded-md border border-dashed px-4 py-10 text-center text-sm"
        >
            Add a question before opening the preview.
        </p>
    </LessonsModal>
</template>
