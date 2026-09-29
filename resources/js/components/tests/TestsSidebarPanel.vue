<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartColumn,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    FolderOpen,
    Image,
    Leaf,
    Upload,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import LessonsUploadDialog from '@/components/lessons/LessonsUploadDialog.vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { reportsExport } from '@/routes';
import type {
    TestMedia,
    TestMediaTabKey,
    TestPreview,
    TestEditorQuestion,
    TestQuestionMediaRef,
    TestResults,
    TestResultTone,
} from '@/types';

type Props = {
    preview: TestPreview;
    media: TestMedia;
    results: TestResults;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { t } = useI18n();
const { can } = useCan();

const emit = defineEmits<{
    'attach-media': [
        question: TestEditorQuestion,
        kind: TestMediaTabKey,
        mediaId: number | null,
    ];
}>();

const activeMediaTab = ref<TestMediaTabKey>(props.media.activeTab);
const previewIndex = ref(0);
const pickerOpen = ref(false);
const uploadOpen = ref(false);

const libraryTabs = computed(() => [
    { key: 'guesvia-library', label: t('GHASIDO Library') },
    { key: 'my-images', label: t('My Images') },
    { key: 'icons-stickers', label: t('Icons & Stickers') },
]);
const libraryCategories = computed(() => [
    { value: 'all-categories', label: t('All categories') },
]);

const uploadLabels: Record<TestMediaTabKey, string> = {
    image: tk('Upload image'),
    audio: tk('Upload audio'),
    video: tk('Upload video'),
};

const removeLabels: Record<TestMediaTabKey, string> = {
    image: tk('Remove image'),
    audio: tk('Remove audio'),
    video: tk('Remove video'),
};

const currentPreview = computed(() => {
    return props.preview.questions[previewIndex.value] ?? null;
});

watch(
    () => props.preview.questions.length,
    (length) => {
        if (length === 0) {
            previewIndex.value = 0;
        } else {
            previewIndex.value = Math.min(previewIndex.value, length - 1);
        }
    },
);

function movePreview(delta: number): void {
    const total = props.preview.questions.length;
    if (total === 0) return;
    previewIndex.value = (previewIndex.value + delta + total) % total;
}

function mediaRef(kind: TestMediaTabKey): TestQuestionMediaRef | null {
    return currentPreview.value?.media[kind] ?? null;
}

function chooseMedia(media: { id: string }): void {
    if (currentPreview.value) {
        emit(
            'attach-media',
            currentPreview.value,
            activeMediaTab.value,
            Number(media.id),
        );
    }
    pickerOpen.value = false;
}

function uploadedMedia(media: { id: string }): void {
    if (currentPreview.value) {
        emit(
            'attach-media',
            currentPreview.value,
            activeMediaTab.value,
            Number(media.id),
        );
    }
    uploadOpen.value = false;
}

function openMediaPicker(): void {
    if (activeMediaTab.value === 'image') {
        pickerOpen.value = true;
    } else {
        uploadOpen.value = true;
    }
}

function removeMedia(): void {
    if (currentPreview.value) {
        emit('attach-media', currentPreview.value, activeMediaTab.value, null);
    }
}

const resultIcon: Record<TestResultTone, Component> = {
    brand: ClipboardCheck,
    danger: Clock,
    success: ChartColumn,
    excel: Leaf,
};

const resultChip: Record<TestResultTone, string> = {
    brand: 'bg-brand-50 text-brand-600',
    danger: 'bg-danger-tint text-danger',
    success: 'bg-success-tint text-success',
    excel: 'bg-excel-tint text-excel',
};

const resultValueTone: Record<TestResultTone, string> = {
    brand: 'text-brand-700',
    danger: 'text-danger',
    success: 'text-success',
    excel: 'text-excel',
};
</script>

<template>
    <div
        :class="
            cn(
                'grid min-w-0 content-start gap-3 md:grid-cols-2 xl:grid-cols-1',
                props.class,
            )
        "
    >
        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    {{ $t('Question Preview') }}
                </h2>
                <div class="flex items-center gap-1.5">
                    <span class="text-ink-slate text-[11.5px] font-medium">
                        {{
                            props.preview.questions.length
                                ? `${previewIndex + 1} / ${props.preview.questions.length}`
                                : '0 / 0'
                        }}
                    </span>
                    <button
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 inline-flex size-6 items-center justify-center rounded-md border"
                        :aria-label="$t('Previous question')"
                        :disabled="props.preview.questions.length < 2"
                        @click="movePreview(-1)"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 inline-flex size-6 items-center justify-center rounded-md border"
                        :aria-label="$t('Next question')"
                        :disabled="props.preview.questions.length < 2"
                        @click="movePreview(1)"
                    >
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="border-line mt-3 flex gap-3 rounded-md border p-2.5">
                <img
                    v-if="currentPreview?.media.image"
                    :src="currentPreview.media.image.url"
                    :alt="
                        currentPreview.media.image.alt ||
                        currentPreview.media.image.label
                    "
                    class="border-line aspect-square w-[92px] shrink-0 self-start rounded-md border object-cover"
                />
                <LessonsMockupCrop
                    v-else
                    :crop="currentPreview?.imageCrop ?? preview.imageCrop"
                    src="/decor/tests-mockup.jpg"
                    :alt="$t('Question preview image')"
                    class="border-line w-[92px] shrink-0 self-start rounded-md border"
                />
                <div class="min-w-0 flex-1">
                    <p
                        class="text-ink text-[12.5px] leading-snug font-semibold"
                    >
                        {{ currentPreview?.text ?? preview.question }}
                    </p>
                    <div class="mt-2 grid gap-1.5">
                        <span
                            v-for="option in currentPreview?.options ??
                            preview.options"
                            :key="option.id"
                            class="text-ink-slate flex items-center gap-2 text-[12px]"
                        >
                            <span
                                class="border-line-strong size-3.5 shrink-0 rounded-full border-2"
                            />
                            {{ option.text }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <h2 class="font-heading text-brand-800 text-base font-semibold">
                {{ $t('Add Media to Question') }}
            </h2>

            <div class="tests-media-layout mt-3 grid gap-3">
                <div class="@container min-w-0">
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="tab in media.tabs"
                            :key="tab.key"
                            type="button"
                            :class="
                                cn(
                                    'inline-flex h-9 items-center justify-center rounded-md text-[12px] font-semibold transition-colors duration-150',
                                    activeMediaTab === tab.key
                                        ? 'bg-brand-600 shadow-btn text-white'
                                        : 'bg-brand-50/70 text-brand-700 hover:bg-brand-100/70',
                                )
                            "
                            @click="activeMediaTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div
                        class="border-line-strong bg-app/60 hover:bg-brand-50/60 mt-2.5 grid cursor-pointer place-items-center rounded-md border border-dashed px-3 py-5 text-center"
                        role="button"
                        tabindex="0"
                        @click="openMediaPicker"
                        @keydown.enter="openMediaPicker"
                    >
                        <Upload
                            class="text-brand-500 size-6"
                            aria-hidden="true"
                        />
                        <p
                            class="text-brand-800 mt-1.5 text-[12px] font-semibold"
                        >
                            {{
                                mediaRef(activeMediaTab)?.label ??
                                $t(uploadLabels[activeMediaTab])
                            }}
                        </p>
                        <p class="text-ink-faint text-[10.5px]">
                            {{
                                activeMediaTab === 'image'
                                    ? $t('JPG, PNG · Max 5MB')
                                    : activeMediaTab === 'audio'
                                      ? $t('MP3, WAV, M4A or WebM · Max 20MB')
                                      : $t('MP4 or WebM · Max 200MB')
                            }}
                        </p>
                    </div>

                    <div class="mt-2.5 grid gap-2 @[260px]:grid-cols-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-2 text-[11.5px] font-semibold shadow-none"
                            @click="openMediaPicker"
                        >
                            <Image class="size-3.5" aria-hidden="true" />
                            {{
                                activeMediaTab === 'image'
                                    ? $t('Browse Images')
                                    : $t('Upload File')
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-2 text-[11.5px] font-semibold shadow-none"
                            @click="openMediaPicker"
                        >
                            <FolderOpen class="size-3.5" aria-hidden="true" />
                            {{ $t('Use from Library') }}
                        </Button>
                    </div>
                    <Button
                        v-if="mediaRef(activeMediaTab)"
                        type="button"
                        variant="ghost"
                        class="text-danger-text hover:bg-danger-tint mt-1 h-8 px-1.5 text-[11px]"
                        @click="removeMedia"
                    >
                        {{ $t(removeLabels[activeMediaTab]) }}
                    </Button>
                </div>

                <div class="min-w-0">
                    <h3 class="text-brand-900 text-[11.5px] font-semibold">
                        {{ $t('Suggested Images') }}
                    </h3>
                    <div class="mt-2 grid grid-cols-2 gap-1.5">
                        <button
                            v-for="image in media.suggested"
                            :key="image.id"
                            type="button"
                            class="border-line hover:border-brand-500 overflow-hidden rounded-md border"
                            :aria-label="$t('Use :name', { name: image.label })"
                            @click="
                                activeMediaTab = 'image';
                                chooseMedia(image);
                            "
                        >
                            <img
                                :src="image.thumbUrl || image.url"
                                :alt="image.alt || image.label"
                                class="aspect-[4/3] w-full object-cover"
                            />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-brand-600 mt-2 text-[11.5px] font-semibold hover:underline"
                        @click="openMediaPicker"
                    >
                        {{ $t('View More') }}
                    </button>
                </div>
            </div>
        </section>

        <LessonsMediaPicker
            v-model:open="pickerOpen"
            :tabs="libraryTabs"
            :categories="libraryCategories"
            @choose="chooseMedia"
        />
        <LessonsUploadDialog
            v-model:open="uploadOpen"
            :kind="activeMediaTab"
            @uploaded="uploadedMedia"
        />

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    {{ $t('Recent Results') }}
                </h2>
                <!-- Full results and AI-judged answers live in Reports &
                     Export since the Results tab was removed (2026-09-29). -->
                <Link
                    v-if="can('reports.view')"
                    :href="reportsExport()"
                    class="text-brand-600 focus-visible:ring-brand-600/15 rounded-sm text-[11.5px] font-semibold hover:underline focus-visible:ring-3 focus-visible:outline-none"
                    data-test="tests-view-all-results-link"
                >
                    {{ $t('View All Results') }}
                </Link>
            </div>

            <div class="mt-3 grid grid-cols-4 gap-2">
                <div
                    v-for="stat in results.stats"
                    :key="stat.key"
                    class="border-line bg-surface flex flex-col items-center rounded-lg border px-1.5 py-2.5 text-center"
                >
                    <span
                        :class="
                            cn(
                                'grid size-9 place-items-center rounded-xl',
                                resultChip[stat.tone],
                            )
                        "
                    >
                        <component
                            :is="resultIcon[stat.tone]"
                            class="size-4.5"
                            aria-hidden="true"
                        />
                    </span>
                    <p
                        :class="
                            cn(
                                'font-heading mt-1.5 text-[20px] leading-6 font-bold',
                                resultValueTone[stat.tone],
                            )
                        "
                    >
                        {{ stat.value }}{{ stat.unit }}
                    </p>
                    <p class="text-ink-slate text-[10px] leading-tight">
                        {{ stat.label }}
                    </p>
                    <p class="text-ink-faint text-[10px] leading-tight">
                        {{ stat.detail }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
@media (min-width: 480px) {
    .tests-media-layout {
        grid-template-columns: minmax(0, 1fr) 104px;
    }
}
</style>
