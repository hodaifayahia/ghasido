<script setup lang="ts">
import { Image, Replace, Sparkles, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import LessonsImageGenerateDialog from '@/components/lessons/LessonsImageGenerateDialog.vue';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import LessonsUploadDialog from '@/components/lessons/LessonsUploadDialog.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    ContentGenerationMedia,
    LessonFilterOption,
    LessonLibraryImage,
    LessonLibraryTab,
    LessonMediaRef,
} from '@/types';

/**
 * One media slot (MED-02): Upload / Choose when empty, Replace / Remove when
 * filled. The slot holds an id; the parent stores it in the block settings
 * or the row column. Images open the library picker; audio and video only
 * upload.
 */
type Props = {
    label: string;
    media: LessonMediaRef | null;
    kind?: 'image' | 'audio' | 'video';
    tabs?: LessonLibraryTab[];
    categories?: LessonFilterOption[];
    compact?: boolean;
    /** Starting description for "Generate" (GEN-01, GEN-04). */
    generatePrompt?: string;
    generateSize?: 'landscape' | 'square';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    kind: 'image',
    tabs: () => [
        { key: 'my-images', label: 'My Images' },
        { key: 'guesvia-library', label: 'Guesvia Library' },
        { key: 'icons-stickers', label: 'Icons & Stickers' },
    ],
    categories: () => [{ value: 'all-categories', label: 'All Categories' }],
    compact: false,
    generatePrompt: '',
    generateSize: 'landscape',
});

const emit = defineEmits<{
    change: [media: LessonMediaRef | null];
}>();

const pickerOpen = ref(false);
const uploadOpen = ref(false);
const generateOpen = ref(false);

/** A generated picture fills the slot like a picked library image. */
function onGenerated(media: ContentGenerationMedia): void {
    emit('change', {
        id: media.id,
        url: media.url,
        thumbUrl: media.thumbUrl || media.url,
        alt: media.alt,
        label: media.label,
        kind: 'image',
    });
}

const filled = computed(() => props.media !== null);

function fromImage(image: LessonLibraryImage): LessonMediaRef {
    return {
        id: Number(image.id),
        url: image.url ?? '',
        thumbUrl: image.thumbUrl ?? image.url ?? '',
        alt: image.alt ?? '',
        label: image.label,
        kind: props.kind,
    };
}

function onChoose(image: LessonLibraryImage): void {
    emit('change', fromImage(image));
}

const buttonClass =
    'border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none';
</script>

<template>
    <div :class="cn('grid gap-1.5', props.class)">
        <p class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]">
            {{ label }}
        </p>

        <div
            v-if="media !== null"
            class="border-line bg-surface flex items-center gap-3 rounded-md border p-2"
        >
            <img
                v-if="media.kind === 'image'"
                :src="media.thumbUrl || media.url"
                :alt="media.alt"
                :class="
                    cn(
                        'border-line rounded-sm border object-cover',
                        compact ? 'h-12 w-16' : 'h-20 w-28',
                    )
                "
            />
            <video
                v-else-if="media.kind === 'video'"
                :src="media.url"
                controls
                preload="none"
                class="border-line h-20 w-32 rounded-sm border bg-black"
            />
            <audio
                v-else
                :src="media.url"
                controls
                preload="none"
                class="h-9 w-48"
            />
            <div class="min-w-0 flex-1">
                <p class="text-ink truncate text-[12.5px] font-medium">
                    {{ media.label }}
                </p>
                <p class="text-ink-faint truncate text-[11px]">
                    {{ media.alt || 'No alt text' }}
                </p>
            </div>
        </div>

        <div
            v-else
            class="border-line bg-brand-50/40 text-ink-slate flex min-h-14 items-center justify-center rounded-md border border-dashed px-3 text-[12px]"
        >
            <Image class="me-1.5 size-4" aria-hidden="true" />
            No {{ kind }} yet
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                variant="outline"
                :class="buttonClass"
                :data-test="`slot-upload-${label}`"
                @click="uploadOpen = true"
            >
                <Upload class="size-3.5" aria-hidden="true" />
                {{ filled && kind !== 'image' ? 'Replace' : 'Upload' }}
            </Button>
            <Button
                v-if="kind === 'image'"
                type="button"
                variant="outline"
                :class="buttonClass"
                :data-test="`slot-choose-${label}`"
                @click="pickerOpen = true"
            >
                <Replace class="size-3.5" aria-hidden="true" />
                {{ filled ? 'Replace' : 'Choose' }}
            </Button>
            <Button
                v-if="kind === 'image'"
                type="button"
                variant="outline"
                :class="buttonClass"
                :data-test="`slot-generate-${label}`"
                @click="generateOpen = true"
            >
                <Sparkles class="size-3.5" aria-hidden="true" />
                {{ filled ? 'Regenerate' : 'Generate' }}
            </Button>
            <Button
                v-if="filled"
                type="button"
                variant="outline"
                class="border-line text-danger-text hover:bg-danger-tint h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                :data-test="`slot-remove-${label}`"
                @click="emit('change', null)"
            >
                <Trash2 class="size-3.5" aria-hidden="true" />
                Remove
            </Button>
        </div>

        <LessonsMediaPicker
            v-if="kind === 'image'"
            v-model:open="pickerOpen"
            :tabs="tabs"
            :categories="categories"
            @choose="onChoose"
        />
        <LessonsImageGenerateDialog
            v-if="kind === 'image'"
            v-model:open="generateOpen"
            :default-prompt="generatePrompt || media?.alt || ''"
            :size="generateSize"
            @generated="onGenerated"
        />
        <LessonsUploadDialog
            v-model:open="uploadOpen"
            :kind="kind"
            @uploaded="onChoose"
        />
    </div>
</template>
