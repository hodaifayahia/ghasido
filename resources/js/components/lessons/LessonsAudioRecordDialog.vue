<script setup lang="ts">
import { Mic, RotateCcw, Square } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { JsonRequestError, postJson } from '@/components/lessons/lessonsHttp';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { useRecorder } from '@/composables/useRecorder';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store } from '@/routes/media';
import type { LessonLibraryImage } from '@/types';

/**
 * Record audio for a question (client report 2026-09-29: "Upload / Record
 * audio / choose from the Library"). The microphone is asked for only on
 * the tap, after the line that explains why; the take can be heard and
 * recorded again, then it is uploaded into My Images like any other clip
 * (MED-01, SEC-04) and the calling slot uses it straight away.
 */
type UploadResponse = { image: LessonLibraryImage };

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    uploaded: [image: LessonLibraryImage];
}>();

const recorder = useRecorder({ maxSeconds: 120 });
const label = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) {
        recorder.reset();

        return;
    }

    label.value = '';
    errors.value = {};
});

function clock(ms: number): string {
    const total = Math.floor(ms / 1000);

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

const hint = computed((): string => {
    switch (recorder.state.value) {
        case 'idle':
            return t(
                'Tap the microphone and allow it in your browser. Nothing is recorded before you tap.',
            );
        case 'requesting':
            return t('Waiting for your permission…');
        case 'recording':
            return t('Recording… tap to stop.');
        case 'recorded':
            return t('Listen to it, record again, or use it.');
        case 'denied':
            return t(
                'Microphone access was refused. Allow the microphone for this site in your browser settings, or upload a file instead.',
            );
        case 'unsupported':
            return t(
                'This browser cannot record audio. Upload a file instead.',
            );
        default:
            return t('Recording failed. Please try again.');
    }
});

function primary(): void {
    if (recorder.state.value === 'recording') {
        recorder.stop();

        return;
    }

    void recorder.start();
}

async function use(): Promise<void> {
    const blob = recorder.blob.value;

    if (blob === null || processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    const mime = recorder.mimeType.value;
    const extension = mime.includes('mp4')
        ? 'm4a'
        : mime.includes('ogg')
          ? 'ogg'
          : 'webm';
    const name =
        label.value.trim() !== ''
            ? label.value.trim()
            : t('Recording :time', { time: new Date().toLocaleString() });

    const body = new FormData();
    body.set('kind', 'audio');
    body.set('library', 'my_images');
    body.set('label', name.slice(0, 120));
    body.set('alt_text', name.slice(0, 255));
    body.set('file', blob, `recording.${extension}`);

    try {
        const response = await postJson<UploadResponse>(store.url(), body);
        toast.success(t(':name was uploaded.', { name: response.image.label }));
        emit('uploaded', response.image);
        open.value = false;
    } catch (error) {
        errors.value =
            error instanceof JsonRequestError
                ? error.errors
                : { _: t('The upload failed. Please try again.') };
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t('Record audio')"
        :description="
            $t('Record the audio the learner hears, up to two minutes.')
        "
    >
        <div class="mt-2 grid gap-4">
            <div class="flex flex-col items-center gap-3">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :aria-label="
                            recorder.state.value === 'recording'
                                ? $t('Stop recording')
                                : $t('Start recording')
                        "
                        :aria-pressed="recorder.state.value === 'recording'"
                        :disabled="
                            recorder.state.value === 'requesting' ||
                            recorder.state.value === 'unsupported'
                        "
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/40 grid size-16 place-items-center rounded-full text-white transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-none active:scale-[.97] disabled:opacity-50',
                                recorder.state.value === 'recording'
                                    ? 'bg-danger animate-pulse-ring motion-reduce:animate-none'
                                    : 'bg-brand-600 shadow-btn hover:bg-brand-700',
                            )
                        "
                        data-test="admin-record-button"
                        @click="primary"
                    >
                        <Square
                            v-if="recorder.state.value === 'recording'"
                            class="size-6 fill-current"
                            aria-hidden="true"
                        />
                        <Mic v-else class="size-7" aria-hidden="true" />
                    </button>
                    <button
                        v-if="recorder.state.value === 'recorded'"
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/15 grid size-11 place-items-center rounded-full border focus-visible:ring-3 focus-visible:outline-none"
                        :aria-label="$t('Record again')"
                        @click="recorder.reset()"
                    >
                        <RotateCcw class="size-5" aria-hidden="true" />
                    </button>
                </div>
                <p
                    class="text-ink-slate text-[12.5px] tabular-nums"
                    aria-live="polite"
                >
                    <span v-if="recorder.state.value === 'recording'">
                        {{ clock(recorder.elapsedMs.value) }} / 2:00 ·
                    </span>
                    {{ hint }}
                </p>
                <audio
                    v-if="recorder.url.value"
                    :src="recorder.url.value"
                    controls
                    class="w-full max-w-sm"
                />
            </div>

            <div class="grid gap-1.5">
                <label
                    for="record-label"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ $t('Name (optional)') }}
                </label>
                <input
                    id="record-label"
                    v-model="label"
                    type="text"
                    maxlength="120"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                />
            </div>

            <InputError :message="errors.file ?? errors._" />

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
                    type="button"
                    :disabled="
                        processing || recorder.state.value !== 'recorded'
                    "
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="use-recording-button"
                    @click="use"
                >
                    {{
                        processing ? $t('Uploading…') : $t('Use this recording')
                    }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
