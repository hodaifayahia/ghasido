<script setup lang="ts">
import { Search, Upload } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { ref, watch } from 'vue';
import { getJson } from '@/components/lessons/lessonsHttp';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
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
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { index } from '@/routes/media';
import type {
    LessonFilterOption,
    LessonLibraryImage,
    LessonLibraryTab,
} from '@/types';

/**
 * Browse Images: the picker every media slot opens (MED-02: Choose /
 * Replace). Lists the library by tab, category and search from the JSON
 * route, one page at a time, with Upload inside.
 */
type Props = {
    tabs: LessonLibraryTab[];
    categories: LessonFilterOption[];
    initialTab?: string;
};

type IndexResponse = {
    images: LessonLibraryImage[];
    total: number;
    currentPage: number;
    lastPage: number;
};

const props = withDefaults(defineProps<Props>(), {
    initialTab: 'guesvia-library',
});

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    choose: [image: LessonLibraryImage];
}>();

const tab = ref(props.initialTab);
const category = ref('all-categories');
const search = ref('');
const page = ref(1);
const images = ref<LessonLibraryImage[]>([]);
const total = ref(0);
const lastPage = ref(1);
const loading = ref(false);
const failed = ref(false);
const uploadOpen = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    failed.value = false;

    try {
        const response = await getJson<IndexResponse>(
            index.url({
                query: {
                    lib: tab.value,
                    libCategory: category.value,
                    libSearch: search.value,
                    page: page.value,
                },
            }),
        );

        images.value = response.images;
        total.value = response.total;
        lastPage.value = response.lastPage;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

const reload = useDebounceFn(() => {
    page.value = 1;
    void load();
}, 250);

watch(open, (isOpen) => {
    if (isOpen) {
        tab.value = props.initialTab;
        page.value = 1;
        void load();
    }
});

watch([tab, category], () => {
    page.value = 1;
    void load();
});

watch(search, () => {
    void reload();
});

function onCategory(value: AcceptableValue): void {
    if (typeof value === 'string') {
        category.value = value;
    }
}

function goTo(next: number): void {
    page.value = next;
    void load();
}

function choose(image: LessonLibraryImage): void {
    emit('choose', image);
    open.value = false;
}

function onUploaded(image: LessonLibraryImage): void {
    choose(image);
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t('Browse Images')"
        :description="
            $t('Choose an image from the library, or upload a new one.')
        "
        size="lg"
    >
        <div class="mt-2 grid gap-3">
            <div class="grid grid-cols-3 gap-1.5">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    :aria-pressed="tab === item.key"
                    :class="
                        cn(
                            'inline-flex h-8 items-center justify-center rounded-md border px-2 text-[11px] font-semibold transition-colors duration-150',
                            'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
                            tab === item.key
                                ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                                : 'border-line bg-brand-50/45 text-brand-700 hover:bg-brand-100/70',
                        )
                    "
                    @click="tab = item.key"
                >
                    {{ $t(item.label) }}
                </button>
            </div>

            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_160px_auto]">
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
                        class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-8 pe-3 text-[12px] shadow-none"
                    />
                </div>
                <Select
                    :model-value="category"
                    @update:model-value="onCategory"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12px] shadow-none"
                        :aria-label="$t('Category')"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in categories"
                            :key="option.value"
                            :value="option.value"
                            class="text-[12px]"
                        >
                            {{ $t(option.label) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    data-test="picker-upload-button"
                    @click="uploadOpen = true"
                >
                    <Upload class="size-3.5" aria-hidden="true" />
                    {{ $t('Upload') }}
                </Button>
            </div>

            <div
                v-if="loading"
                class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6"
                aria-busy="true"
            >
                <Skeleton
                    v-for="n in 12"
                    :key="n"
                    class="aspect-[4/3] w-full rounded-md"
                />
            </div>

            <p
                v-else-if="failed"
                class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                {{ $t('The library could not be loaded.') }}
                <button type="button" class="underline" @click="load">
                    {{ $t('Try again') }}
                </button>
            </p>

            <p
                v-else-if="images.length === 0"
                class="text-ink-slate rounded-md px-1 py-6 text-center text-[12.5px]"
            >
                {{ $t('No image matches. Upload one, or clear the search.') }}
            </p>

            <div
                v-else
                class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6"
            >
                <button
                    v-for="image in images"
                    :key="image.id"
                    type="button"
                    :data-test="`picker-image-${image.id}`"
                    class="focus-visible:ring-brand-600/15 flex min-w-0 flex-col items-start gap-1 rounded-md focus-visible:ring-3 focus-visible:outline-none"
                    @click="choose(image)"
                >
                    <img
                        :src="image.thumbUrl ?? image.url"
                        :alt="image.alt ?? image.label"
                        loading="lazy"
                        decoding="async"
                        class="border-line hover:border-brand-400 aspect-[4/3] w-full rounded-md border object-cover"
                    />
                    <span
                        class="text-ink-muted block w-full truncate text-start text-[10.5px]"
                    >
                        {{ image.label }}
                    </span>
                </button>
            </div>

            <div
                v-if="lastPage > 1"
                class="flex items-center justify-between gap-2 pt-1"
            >
                <span class="text-ink-slate text-[12px]">
                    {{
                        $t(':total images · page :page of :last', {
                            total,
                            page,
                            last: lastPage,
                        })
                    }}
                </span>
                <div class="flex gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="page <= 1 || loading"
                        class="border-line text-brand-700 h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="goTo(page - 1)"
                    >
                        {{ $t('Previous') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="page >= lastPage || loading"
                        class="border-line text-brand-700 h-8 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="goTo(page + 1)"
                    >
                        {{ $t('Next') }}
                    </Button>
                </div>
            </div>
        </div>

        <LessonsUploadDialog v-model:open="uploadOpen" @uploaded="onUploaded" />
    </LessonsModal>
</template>
