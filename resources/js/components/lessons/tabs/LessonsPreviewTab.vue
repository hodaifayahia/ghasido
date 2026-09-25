<script setup lang="ts">
import { ExternalLink, Monitor, Smartphone } from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { LessonBlockRow, LessonEditor } from '@/types';

/**
 * Preview as employee (CMS-03): the learner's own step page, served by the
 * preview route that writes nothing, inside a phone-width (390px) frame and
 * a desktop frame scaled to fit. Use the step tracker inside the frame to
 * move between steps.
 */
type Props = {
    editor: LessonEditor;
    blocks: LessonBlockRow[];
};

const props = defineProps<Props>();

const device = ref<'phone' | 'desktop'>('phone');
const desktopFrame = ref<HTMLElement | null>(null);
const { width } = useElementSize(desktopFrame);

const visible = computed(() => props.blocks.filter((block) => block.isVisible));
const url = computed(() => props.editor.previewUrl);
const scale = computed(() => (width.value > 0 ? width.value / 1280 : 0.5));

const deviceButton = (active: boolean): string =>
    cn(
        'h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none',
        active
            ? 'border-brand-600 bg-brand-600 hover:bg-brand-700 text-white hover:text-white'
            : 'border-line text-brand-700 hover:bg-brand-50',
    );
</script>

<template>
    <div class="grid gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex gap-1.5" role="group" aria-label="Preview device">
                <Button
                    type="button"
                    variant="outline"
                    :aria-pressed="device === 'phone'"
                    :class="deviceButton(device === 'phone')"
                    data-test="preview-phone-button"
                    @click="device = 'phone'"
                >
                    <Smartphone class="size-3.5" aria-hidden="true" />
                    Phone (390px)
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :aria-pressed="device === 'desktop'"
                    :class="deviceButton(device === 'desktop')"
                    data-test="preview-desktop-button"
                    @click="device = 'desktop'"
                >
                    <Monitor class="size-3.5" aria-hidden="true" />
                    Desktop
                </Button>
            </div>
            <a
                v-if="url !== null && visible.length > 0"
                :href="url"
                target="_blank"
                rel="noopener"
                class="text-brand-700 hover:bg-brand-50 inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-[12px] font-semibold"
            >
                <ExternalLink class="size-3.5" aria-hidden="true" />
                Open in a new tab
            </a>
        </div>

        <p class="text-ink-slate text-[12px]">
            This is the lesson exactly as an employee sees it, step by step.
            Nothing you do here is recorded as progress; use the step tracker in
            the frame to move between the {{ visible.length }} visible steps.
        </p>

        <div
            v-if="url === null || visible.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate flex min-h-40 items-center justify-center rounded-md border border-dashed px-4 text-center text-[13px]"
        >
            Add at least one visible block to preview this lesson.
        </div>

        <div v-else-if="device === 'phone'" class="flex justify-center py-2">
            <div
                class="border-line-strong bg-ink shadow-pop w-[406px] max-w-full rounded-[28px] border-[6px] p-0.5"
            >
                <iframe
                    :src="url"
                    title="Lesson preview on a phone"
                    class="bg-surface block h-[700px] w-full rounded-[22px]"
                    data-test="preview-frame"
                />
            </div>
        </div>

        <div
            v-else
            class="border-line-strong bg-ink overflow-hidden rounded-lg border p-1"
        >
            <div
                ref="desktopFrame"
                class="relative aspect-[1280/853] w-full overflow-hidden rounded-md"
            >
                <iframe
                    :src="url"
                    title="Lesson preview on a desktop"
                    class="bg-surface absolute top-0 left-0 h-[853px] w-[1280px] origin-top-left"
                    :style="{ transform: `scale(${scale})` }"
                    data-test="preview-frame"
                />
            </div>
        </div>
    </div>
</template>
