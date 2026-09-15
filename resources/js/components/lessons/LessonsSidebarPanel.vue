<script setup lang="ts">
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
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
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
import type {
    LessonBlockIcon,
    LessonBlockTone,
    LessonBuilderBlock,
    LessonsImageLibrary,
} from '@/types';

type Props = {
    blocks: LessonBuilderBlock[];
    library: LessonsImageLibrary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const activeLibraryTab = ref(props.library.activeTab);
const selectedCategory = ref(props.library.category);
const search = ref(props.library.search);

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

const filteredImages = computed(() => props.library.images);

function onCategorySelect(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    selectedCategory.value = value;
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <PanelCard
            title="Add Content Blocks"
            title-id="lesson-content-blocks"
            class="px-3 pt-3 pb-3"
            body-class="mt-1.5"
        >
            <p class="text-ink-slate mb-2 text-[11.5px]">
                Click to add a section to your lesson.
            </p>

            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="block in blocks"
                    :key="block.id"
                    type="button"
                    class="border-line hover:bg-brand-50/60 bg-surface flex min-h-[72px] flex-col items-center justify-center gap-1 rounded-md border px-2 py-2 text-center transition-colors duration-150"
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
            title="Image Library"
            title-id="lesson-image-library"
            class="px-3 pt-3 pb-3"
            body-class="mt-1.5"
        >
            <p class="text-ink-slate mb-2 text-[11.5px]">
                Upload your own images or choose from the gallery.
            </p>

            <div class="mb-2 grid grid-cols-3 gap-1.5">
                <button
                    v-for="tab in library.tabs"
                    :key="tab.key"
                    type="button"
                    :class="
                        cn(
                            'inline-flex h-8 items-center justify-center rounded-md border px-2 text-[10.5px] font-semibold transition-colors duration-150',
                            activeLibraryTab === tab.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/45 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="activeLibraryTab = tab.key"
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
                        placeholder="Search images..."
                        class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-8 pe-3 text-[12px] shadow-none"
                    />
                </div>

                <Select
                    :model-value="selectedCategory"
                    @update:model-value="onCategorySelect"
                >
                    <SelectTrigger
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

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="image in filteredImages"
                    :key="image.id"
                    type="button"
                    class="flex min-w-0 flex-col items-start gap-1.5 rounded-md"
                >
                    <LessonsMockupCrop
                        :crop="image.crop"
                        :alt="image.label"
                        class="border-line w-full rounded-md border"
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
                    class="border-line hover:bg-brand-50/55 bg-surface flex min-h-[72px] flex-col items-center justify-center rounded-md border border-dashed px-3 py-3 transition-colors duration-150"
                >
                    <Upload
                        class="text-brand-600 mb-1 size-5"
                        aria-hidden="true"
                    />
                    <span class="text-brand-900 text-[12px] font-semibold">
                        Upload New Image
                    </span>
                    <span class="text-ink-faint text-[10.5px]">
                        JPG, PNG - Max 5MB
                    </span>
                </button>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-auto min-h-[72px] rounded-md px-3 text-[12px] font-semibold shadow-none"
                >
                    Browse Images
                </Button>
            </div>
        </PanelCard>
    </div>
</template>
