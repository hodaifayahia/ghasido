<script setup lang="ts">
import { Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { JsonRequestError, postJson } from '@/components/lessons/lessonsHttp';
import type { ValidationErrors } from '@/components/lessons/lessonsHttp';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t, tk } from '@/lib/i18n';
import { store } from '@/routes/media';
import type { LessonLibraryImage } from '@/types';

/**
 * Upload New Image (MED-01, MED-07, SEC-04): file + required alt text +
 * optional category, validated on the server by MIME and size. Posted as
 * JSON so the calling media slot receives the new asset and can use it
 * straight away; the same dialog uploads audio and video.
 */
type Props = {
    kind?: 'image' | 'audio' | 'video';
    library?: string;
};

type UploadResponse = { image: LessonLibraryImage };

const props = withDefaults(defineProps<Props>(), {
    kind: 'image',
    library: 'my_images',
});

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    uploaded: [image: LessonLibraryImage];
}>();

const file = ref<File | null>(null);
const altText = ref('');
const category = ref('');
const errors = ref<ValidationErrors>({});
const processing = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        file.value = null;
        altText.value = '';
        category.value = '';
        errors.value = {};
    }
});

const accept: Record<NonNullable<Props['kind']>, string> = {
    image: 'image/jpeg,image/png,image/webp',
    audio: 'audio/mpeg,audio/wav,audio/mp4,audio/webm',
    video: 'video/mp4,video/webm',
};

const limit: Record<NonNullable<Props['kind']>, string> = {
    image: tk('JPG, PNG or WebP, up to 5 MB'),
    audio: tk('MP3, WAV, M4A or WebM, up to 20 MB'),
    video: tk('MP4 or WebM, up to 200 MB'),
};

const titles: Record<NonNullable<Props['kind']>, string> = {
    image: tk('Upload image'),
    audio: tk('Upload audio'),
    video: tk('Upload video'),
};

function onFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
}

async function submit(): Promise<void> {
    if (processing.value) {
        return;
    }

    errors.value = {};
    processing.value = true;

    const body = new FormData();
    body.set('kind', props.kind);
    body.set('library', props.library);
    body.set('alt_text', altText.value);
    body.set('category', category.value);

    if (file.value !== null) {
        body.set('file', file.value);
    }

    try {
        const response = await postJson<UploadResponse>(store.url(), body);
        toast.success(t(':name was uploaded.', { name: response.image.label }));
        open.value = false;
        emit('uploaded', response.image);
    } catch (error) {
        errors.value =
            error instanceof JsonRequestError
                ? error.errors
                : { _: t('The upload failed. Please try again.') };
    } finally {
        processing.value = false;
    }
}

const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t(titles[kind])"
        :description="$t(limit[kind])"
    >
        <form class="mt-2 grid gap-4" @submit.prevent="submit">
            <label
                class="border-line hover:bg-brand-50/55 bg-surface focus-within:border-brand-600 focus-within:ring-brand-600/15 flex min-h-[96px] cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed px-3 py-3 text-center focus-within:ring-3"
            >
                <Upload class="text-brand-600 size-5" aria-hidden="true" />
                <span class="text-brand-900 text-[12.5px] font-semibold">
                    {{ file === null ? $t('Choose a file') : file.name }}
                </span>
                <span class="text-ink-faint text-[11px]">{{
                    $t(limit[kind])
                }}</span>
                <input
                    type="file"
                    name="file"
                    :accept="accept[kind]"
                    required
                    class="sr-only"
                    data-test="upload-file-input"
                    @change="onFile"
                />
            </label>
            <InputError :message="errors.file" />

            <div class="grid gap-1.5">
                <Label
                    for="upload-alt"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{
                        kind === 'image'
                            ? $t('Alt text (what the image shows)')
                            : $t('Description')
                    }}
                </Label>
                <Input
                    id="upload-alt"
                    v-model="altText"
                    :required="kind === 'image'"
                    :aria-invalid="errors.alt_text ? true : undefined"
                    data-test="upload-alt-input"
                    :class="inputClass"
                />
                <InputError :message="errors.alt_text" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="upload-category"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ $t('Category (optional)') }}
                </Label>
                <Input
                    id="upload-category"
                    v-model="category"
                    :placeholder="$t('reception, hotel, people…')"
                    :class="inputClass"
                />
                <InputError :message="errors.category" />
            </div>

            <InputError :message="errors._" />

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing || file === null"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="upload-submit-button"
                >
                    {{ processing ? $t('Uploading…') : $t('Upload') }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
