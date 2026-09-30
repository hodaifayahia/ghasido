<script setup lang="ts">
import { CircleAlert, FileText, CloudUpload, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { t } from '@/lib/i18n';
import { formatFileSize } from './money';

/*
 * "Upload proof of payment" (client payment-flow reference, step 4): a
 * dashed drop zone that also opens the file picker on click or Enter, then
 * a chip with the receipt's thumbnail (or a PDF badge), name, size and a
 * remove button. Type and size are checked here for a quick answer; the
 * server checks them again (SEC-04).
 */
type Props = {
    types: string[];
    maxKb: number;
    error?: string;
};

const props = defineProps<Props>();

const file = defineModel<File | null>({ required: true });

const input = useTemplateRef<HTMLInputElement>('input');
const dragging = ref(false);
const localError = ref<string | null>(null);
const preview = ref<string | null>(null);

const accept = computed(() =>
    props.types.map((type) => `.${type.toLowerCase()}`).join(','),
);
const typesLabel = computed(() =>
    props.types
        .filter((type) => type.toLowerCase() !== 'jpeg')
        .map((type) => type.toUpperCase())
        .join(', '),
);
const maxLabel = computed(() => formatFileSize(props.maxKb * 1024));
const isPdf = computed(
    () =>
        file.value !== null &&
        (file.value.type === 'application/pdf' ||
            file.value.name.toLowerCase().endsWith('.pdf')),
);
const shownError = computed(() => localError.value ?? props.error ?? null);

function extensionOf(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

function canvasBlob(
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob | null> {
    return new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );
}

/**
 * A phone photo of a receipt is often larger than the upload limit. Rather
 * than refuse it, redraw it as a JPEG, smaller and lighter step by step,
 * until it fits; the text of a receipt stays readable at these sizes.
 * Returns null when the browser cannot read the image.
 */
async function shrinkImage(
    source: File,
    maxBytes: number,
): Promise<File | null> {
    let bitmap: ImageBitmap;

    try {
        bitmap = await createImageBitmap(source);
    } catch {
        return null;
    }

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();

        return null;
    }

    let longest = Math.min(2400, Math.max(bitmap.width, bitmap.height));

    try {
        for (let attempt = 0; attempt < 8; attempt++) {
            const scale = longest / Math.max(bitmap.width, bitmap.height);
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            // White behind transparent PNGs, which JPEG cannot keep.
            context.fillStyle = 'white';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

            const blob = await canvasBlob(canvas, attempt < 2 ? 0.85 : 0.75);

            if (blob && blob.size <= maxBytes) {
                const base = source.name.replace(/\.[^.]+$/, '') || 'receipt';

                return new File([blob], `${base}.jpg`, {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
            }

            longest = Math.round(longest * 0.8);
        }
    } finally {
        bitmap.close();
    }

    return null;
}

// Shared with the checkout, which holds the submit button until it is done.
const preparing = defineModel<boolean>('preparing', { default: false });

async function choose(candidate: File | undefined): Promise<void> {
    if (!candidate) {
        return;
    }

    const allowed = props.types.map((type) => type.toLowerCase());

    if (!allowed.includes(extensionOf(candidate.name))) {
        localError.value = t(
            'This file type is not accepted. Upload a :types file.',
            { types: typesLabel.value },
        );

        return;
    }

    const maxBytes = props.maxKb * 1024;

    if (
        candidate.size > maxBytes &&
        IMAGE_TYPES.includes(extensionOf(candidate.name))
    ) {
        preparing.value = true;
        localError.value = null;

        try {
            const smaller = await shrinkImage(candidate, maxBytes);

            if (smaller) {
                file.value = smaller;

                return;
            }
        } finally {
            preparing.value = false;
        }
    }

    if (candidate.size > maxBytes) {
        localError.value = t(
            'This file is :size. Receipts can be :max at most — try a screenshot or a smaller photo.',
            { size: formatFileSize(candidate.size), max: maxLabel.value },
        );

        return;
    }

    localError.value = null;
    file.value = candidate;
}

function onPick(event: Event): void {
    const target = event.target as HTMLInputElement;
    void choose(target.files?.[0]);
    target.value = '';
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    void choose(event.dataTransfer?.files[0]);
}

function onDragLeave(event: DragEvent): void {
    const related = event.relatedTarget as Node | null;

    if (!related || !(event.currentTarget as HTMLElement).contains(related)) {
        dragging.value = false;
    }
}

function remove(): void {
    file.value = null;
    localError.value = null;
    input.value?.focus();
}

function revoke(): void {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
}

watch(
    file,
    (current) => {
        revoke();

        if (current && !isPdf.value && current.type.startsWith('image/')) {
            preview.value = URL.createObjectURL(current);
        }
    },
    { immediate: true },
);

onBeforeUnmount(revoke);
</script>

<template>
    <div :data-invalid="shownError ? 'true' : undefined" class="grid gap-3">
        <div
            :class="
                dragging
                    ? 'border-brand-600 bg-brand-50'
                    : shownError
                      ? 'border-danger/60 bg-danger-tint/40'
                      : 'border-brand-300 bg-app hover:border-brand-600 hover:bg-brand-50/60'
            "
            class="has-[input:focus-visible]:ring-brand-600/20 relative rounded-lg border-2 border-dashed transition-colors duration-150 has-[input:focus-visible]:ring-3 motion-reduce:transition-none"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave="onDragLeave"
            @drop.prevent="onDrop"
        >
            <label
                for="payment-proof"
                class="flex min-h-40 cursor-pointer flex-col items-center justify-center gap-2 px-5 py-7 text-center"
            >
                <span
                    :class="dragging ? 'scale-110' : ''"
                    class="bg-surface text-brand-600 shadow-card grid size-12 place-items-center rounded-full transition-transform duration-200 motion-reduce:transition-none"
                    aria-hidden="true"
                >
                    <CloudUpload class="size-6" />
                </span>
                <span class="text-ink text-[14px] leading-6">
                    <span class="text-brand-600 font-semibold">{{
                        $t('Click to upload')
                    }}</span>
                    {{ $t('or drag and drop') }}
                </span>
                <span
                    v-if="preparing"
                    class="text-brand-700 text-[12px] font-semibold"
                    role="status"
                >
                    {{ $t('Making the photo smaller…') }}
                </span>
                <span v-else class="text-ink-muted text-[12px]">
                    {{
                        $t(':types (max :max)', {
                            types: typesLabel,
                            max: maxLabel,
                        })
                    }}
                </span>
            </label>
            <input
                id="payment-proof"
                ref="input"
                type="file"
                name="proof"
                :accept="accept"
                :aria-invalid="shownError ? 'true' : undefined"
                :aria-describedby="
                    shownError ? 'payment-proof-error' : undefined
                "
                class="sr-only"
                @change="onPick"
            />
        </div>

        <p
            v-if="shownError"
            id="payment-proof-error"
            class="text-danger-text flex items-start gap-1.5 text-[13px] leading-5"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ shownError }}
        </p>

        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="translate-y-1 opacity-0"
            leave-active-class="transition duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="file"
                class="border-line bg-surface shadow-card flex items-center gap-3 rounded-lg border p-3"
            >
                <img
                    v-if="preview"
                    :src="preview"
                    :alt="$t('Preview of :name', { name: file.name })"
                    class="border-line size-14 shrink-0 rounded-md border object-cover"
                />
                <span
                    v-else
                    class="bg-danger-tint text-danger-text grid size-14 shrink-0 place-items-center rounded-md"
                    aria-hidden="true"
                >
                    <FileText class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-ink truncate text-[14px] font-semibold"
                        dir="auto"
                    >
                        {{ file.name }}
                    </p>
                    <p class="text-ink-muted mt-0.5 text-[12px]">
                        <span v-if="isPdf">PDF · </span
                        >{{ formatFileSize(file.size) }}
                    </p>
                </div>
                <button
                    type="button"
                    :aria-label="$t('Remove :name', { name: file.name })"
                    class="text-ink-muted hover:bg-danger-tint hover:text-danger-text focus-visible:ring-brand-600/20 grid size-11 shrink-0 place-items-center rounded-md transition-colors focus-visible:ring-3 focus-visible:outline-none motion-reduce:transition-none"
                    @click="remove"
                >
                    <X class="size-5" aria-hidden="true" />
                </button>
            </div>
        </Transition>
    </div>
</template>
