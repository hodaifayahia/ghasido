<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Bot,
    ClipboardCheck,
    Download,
    Image,
    MapPin,
    MessageSquare,
    Play,
    Search,
    SquarePen,
    StickyNote,
    Type,
    Upload,
    Video,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { BLOCK_DRAG_TYPE } from '@/components/lessons/lessonsBlocks';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import { visitLessons } from '@/components/lessons/lessonsQuery';
import LessonsUploadDialog from '@/components/lessons/LessonsUploadDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/composables/useCan';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store as storeBlock } from '@/routes/blocks';
import type {
    LessonBlockIcon,
    LessonBlockTone,
    LessonBuilderBlock,
    LessonLibraryImage,
    LessonsImageLibrary,
} from '@/types';

type Props = {
    blocks: LessonBuilderBlock[];
    library: LessonsImageLibrary;
    lessonId: number | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    /** An image chosen from the library or the picker (sets the lesson cover). */
    pick: [image: LessonLibraryImage];
}>();

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const search = ref(props.library.search);
const uploadOpen = ref(false);
const pickerOpen = ref(false);
const adding = ref<string | null>(null);

watch(
    () => props.library.search,
    (value) => {
        search.value = value;
    },
);

const blockTone: Record<LessonBlockTone, string> = {
    brand: 'bg-brand-100 text-brand-700',
    azure: 'bg-azure-tint text-azure',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    danger: 'bg-danger-tint text-danger',
    ai: 'bg-ai-tint text-ai',
    aqua: 'bg-aqua-tint text-aqua',
    gold: 'bg-gold-tint text-gold',
};

const blockIcons: Record<LessonBlockIcon, Component | null> = {
    situation: MapPin,
    vocabulary: null,
    expressions: Type,
    dialogue: MessageSquare,
    audio: Play,
    image: Image,
    video: Video,
    practice: SquarePen,
    roleplay: Bot,
    quiz: ClipboardCheck,
    download: Download,
    note: StickyNote,
};

/**
 * Click adds a block of the tile's type to the end of the lesson (before
 * the closing step); dragging the tile onto the Lesson Blocks list inserts
 * it at the drop position (BLD-02, BLD-03).
 */
function addBlock(block: LessonBuilderBlock): void {
    if (
        block.type === null ||
        props.lessonId === null ||
        !manage.value ||
        adding.value !== null
    ) {
        return;
    }

    adding.value = block.id;
    router.post(
        storeBlock.url(props.lessonId),
        { type: block.type },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}

function onDragStart(event: DragEvent, block: LessonBuilderBlock): void {
    if (block.type === null || event.dataTransfer === null) {
        return;
    }

    event.dataTransfer.setData(BLOCK_DRAG_TYPE, block.type);
    event.dataTransfer.setData('text/plain', block.label);
    event.dataTransfer.effectAllowed = 'copy';
}

function tileTitle(block: LessonBuilderBlock): string | undefined {
    return block.type === null ? t('Not available yet') : undefined;
}

// Library filters live in the query string; only the library prop reloads.
const libraryOnly = { only: ['library'], replace: true };

function onLibraryTab(key: string): void {
    if (key !== props.library.activeTab) {
        visitLessons({ lib: key }, libraryOnly);
    }
}

function onCategory(value: AcceptableValue): void {
    if (typeof value === 'string' && value !== props.library.category) {
        visitLessons({ libCategory: value }, libraryOnly);
    }
}

const onSearch = useDebounceFn(() => {
    if (search.value !== props.library.search) {
        visitLessons({ libSearch: search.value }, libraryOnly);
    }
}, 300);

function onUploaded(): void {
    // Uploads are stored in My Images. Switch there immediately so the new
    // file is visible and pickable instead of silently refreshing the current
    // Guesvia Library tab (MED-01, MED-02).
    visitLessons(
        {
            lib: 'my-images',
            libSearch: null,
            libCategory: 'all-categories',
        },
        libraryOnly,
    );
}

function onChoose(image: LessonLibraryImage): void {
    emit('pick', image);
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <PanelCard
            :title="$t('Add Content Blocks')"
            title-id="lesson-content-blocks"
            class="px-3 pt-3 pb-3"
            body-class="mt-1.5"
        >
            <p class="text-ink-slate mb-2 text-[11.5px]">
                {{ $t('Click to add a section to your lesson.') }}
            </p>

            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="block in blocks"
                    :key="block.id"
                    type="button"
                    :draggable="
                        block.type !== null && manage && lessonId !== null
                    "
                    :disabled="
                        block.type === null || lessonId === null || !manage
                    "
                    :title="tileTitle(block)"
                    :aria-busy="adding === block.id"
                    :data-test="`palette-${block.id}`"
                    class="border-line hover:bg-brand-50/60 bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 flex min-h-[72px] flex-col items-center justify-center gap-1 rounded-md border px-2 py-2 text-center transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    @click="addBlock(block)"
                    @dragstart="onDragStart($event, block)"
                >
                    <span
                        :class="
                            cn(
                                'rounded-pill grid size-8 place-items-center',
                                blockTone[block.tone],
                            )
                        "
                    >
                        <span
                            v-if="block.icon === 'vocabulary'"
                            class="font-heading text-[16px] font-bold tracking-[-0.03em]"
                        >
                            Aa
                        </span>
                        <component
                            :is="blockIcons[block.icon]"
                            v-else
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="text-brand-900 text-[10.5px] leading-[1.15] font-medium"
                    >
                        {{ block.label }}
                    </span>
                </button>
            </div>
        </PanelCard>

        <PanelCard
            :title="$t('Image Library')"
            title-id="lesson-image-library"
            class="px-3 pt-3 pb-3"
            body-class="mt-1.5"
        >
            <p class="text-ink-slate mb-2 text-[11.5px]">
                {{ $t('Upload your own images or choose from the gallery.') }}
            </p>

            <div class="mb-2 grid grid-cols-3 gap-1.5">
                <button
                    v-for="tab in library.tabs"
                    :key="tab.key"
                    type="button"
                    :aria-pressed="library.activeTab === tab.key"
                    :data-test="`library-tab-${tab.key}`"
                    :class="
                        cn(
                            'inline-flex h-8 items-center justify-center rounded-md border px-2 text-[10.5px] font-semibold transition-colors duration-150',
                            'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
                            library.activeTab === tab.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/45 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="onLibraryTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="mb-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_132px]">
                <div class="relative min-w-0">
                    <Search
                        aria-hidden="true"
                        class="text-ink-faint absolute start-3 top-1/2 size-3.5 -translate-y-1/2"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="$t('Search images...')"
                        :aria-label="$t('Search images')"
                        data-test="library-search-input"
                        class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-8 pe-3 text-[12px] shadow-none"
                        @input="onSearch"
                    />
                </div>

                <Select
                    :model-value="library.category"
                    @update:model-value="onCategory"
                >
                    <SelectTrigger
                        :aria-label="$t('Category')"
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in library.categories"
                            :key="option.value"
                            :value="option.value"
                            class="text-[12px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <p
                v-if="library.images.length === 0"
                class="text-ink-slate border-line rounded-md border border-dashed px-3 py-4 text-center text-[11.5px]"
            >
                {{ $t('No image here yet. Upload one or open another tab.') }}
            </p>

            <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="image in library.images"
                    :key="image.id"
                    type="button"
                    :title="
                        manage ? $t('Use as the lesson cover') : image.label
                    "
                    :data-test="`library-image-${image.id}`"
                    class="focus-visible:ring-brand-600/15 flex min-w-0 flex-col items-start gap-1.5 rounded-md focus-visible:ring-3 focus-visible:outline-none"
                    @click="onChoose(image)"
                >
                    <img
                        :src="image.thumbUrl ?? image.url"
                        :alt="image.alt ?? image.label"
                        loading="lazy"
                        decoding="async"
                        class="border-line bg-brand-50 aspect-[67/47] w-full rounded-md border object-cover"
                    />
                    <span
                        class="text-ink-muted block w-full truncate text-start text-[10.5px]"
                    >
                        {{ image.label }}
                    </span>
                </button>
            </div>

            <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_130px]">
                <button
                    type="button"
                    :disabled="!manage"
                    data-test="upload-image-button"
                    class="border-line hover:bg-brand-50/55 bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 flex min-h-[72px] flex-col items-center justify-center rounded-md border border-dashed px-3 py-3 transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60"
                    @click="uploadOpen = true"
                >
                    <Upload
                        class="text-brand-600 mb-1 size-5"
                        aria-hidden="true"
                    />
                    <span class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Upload New Image') }}
                    </span>
                    <span class="text-ink-faint text-[10.5px]">
                        {{ $t('JPG, PNG - Max 5MB') }}
                    </span>
                </button>

                <Button
                    type="button"
                    variant="outline"
                    data-test="browse-images-button"
                    class="border-line text-brand-700 hover:bg-brand-50 h-auto min-h-[72px] rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="pickerOpen = true"
                >
                    {{ $t('Browse Images') }}
                </Button>
            </div>
        </PanelCard>

        <LessonsUploadDialog v-model:open="uploadOpen" @uploaded="onUploaded" />
        <LessonsMediaPicker
            v-model:open="pickerOpen"
            :tabs="library.tabs"
            :categories="library.categories"
            :initial-tab="library.activeTab"
            @choose="onChoose"
        />
    </div>
</template>
