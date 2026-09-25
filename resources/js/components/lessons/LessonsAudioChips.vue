<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Turtle, Upload, Volume2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import LessonsUploadDialog from '@/components/lessons/LessonsUploadDialog.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { generate, replace } from '@/routes/audio';
import type {
    AudioClipStatus,
    LessonAudioPair,
    LessonLibraryImage,
} from '@/types';

/**
 * The audio state of playable texts: one chip per speed (normal / slow)
 * with pending / done / failed / missing, a preview player, Generate Audio
 * for every missing or failed clip, and Upload your own to replace a clip
 * (TTS-01, TTS-02, TTS-03, TTS-06). Audio is never synthesised at play time
 * (CTRL-05); it is generated here, once, and stored.
 */
type Props = {
    texts: string[];
    audio: Record<string, LessonAudioPair>;
    readOnly?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { readOnly: false });

const busy = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollAttempts = 0;
const uploadOpen = ref(false);
const uploadTarget = ref<{ text: string; speed: 'normal' | 'slow' } | null>(
    null,
);

const missing: LessonAudioPair = {
    normal: { status: 'missing', url: null },
    slow: { status: 'missing', url: null },
};

const rows = computed(() =>
    props.texts
        .filter((text) => text.trim() !== '')
        .map((text) => ({ text, pair: props.audio[text] ?? missing })),
);

const incomplete = computed(() =>
    rows.value.filter(
        ({ pair }) =>
            pair.normal.status !== 'done' || pair.slow.status !== 'done',
    ),
);

const pending = computed(() =>
    rows.value.some(
        ({ pair }) =>
            ['pending', 'running'].includes(pair.normal.status) ||
            ['pending', 'running'].includes(pair.slow.status),
    ),
);

const chip: Record<AudioClipStatus, string> = {
    done: 'bg-success-tint text-success-text',
    pending: 'bg-warning-tint text-warning-text',
    running: 'bg-warning-tint text-warning-text',
    failed: 'bg-danger-tint text-danger-text',
    missing: 'bg-app-alt text-ink-muted',
};

const label: Record<AudioClipStatus, string> = {
    done: 'Ready',
    pending: 'Queued',
    running: 'Generating',
    failed: 'Failed',
    missing: 'Missing',
};

const options = {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
        busy.value = false;
    },
};

function generateAll(): void {
    busy.value = true;
    startPolling();
    router.post(
        generate.url(),
        { texts: incomplete.value.map((row) => row.text) },
        options,
    );
}

/**
 * The generation request only enqueues work. Refresh the lesson block props
 * until both stored files are available, so the admin can immediately hear
 * the result without a manual page reload (TTS-02, PERF-04).
 */
function startPolling(): void {
    stopPolling();
    pollAttempts = 0;
    pollTimer = setInterval(() => {
        pollAttempts += 1;

        if (incomplete.value.length === 0 || pollAttempts >= 60) {
            stopPolling();

            return;
        }

        router.reload({
            only: ['lessonBlocks'],
        });
    }, 2000);
}

function stopPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

onBeforeUnmount(stopPolling);

function startUpload(text: string, speed: 'normal' | 'slow'): void {
    uploadTarget.value = { text, speed };
    uploadOpen.value = true;
}

function onUploaded(image: LessonLibraryImage): void {
    if (uploadTarget.value === null) {
        return;
    }

    busy.value = true;
    router.post(
        replace.url(),
        {
            text: uploadTarget.value.text,
            speed: uploadTarget.value.speed,
            media_id: Number(image.id),
        },
        options,
    );
}
</script>

<template>
    <div :class="cn('grid gap-2', props.class)">
        <div
            v-if="rows.length > 0"
            class="flex items-center justify-between gap-2"
        >
            <p class="text-brand-900 text-[11.5px] font-semibold">
                Text-to-speech audio
            </p>
            <span class="text-ink-faint text-[10.5px]"> Normal + Slow </span>
        </div>

        <div
            v-for="row in rows"
            :key="row.text"
            class="border-line bg-surface grid gap-1.5 rounded-md border px-3 py-2"
        >
            <p class="text-ink truncate text-[12.5px] font-medium">
                {{ row.text }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <template
                    v-for="speed in ['normal', 'slow'] as const"
                    :key="speed"
                >
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex h-6 items-center gap-1 px-2 text-[11px] font-semibold',
                                chip[row.pair[speed].status],
                            )
                        "
                    >
                        <component
                            :is="speed === 'normal' ? Volume2 : Turtle"
                            class="size-3"
                            aria-hidden="true"
                        />
                        {{ speed === 'normal' ? 'Normal' : 'Slow' }}:
                        {{ label[row.pair[speed].status] }}
                    </span>
                    <audio
                        v-if="row.pair[speed].url !== null"
                        :src="row.pair[speed].url ?? undefined"
                        controls
                        preload="none"
                        class="h-8 w-40"
                        :aria-label="`${speed} clip of ${row.text}`"
                    />
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="text-brand-700 hover:bg-brand-50 inline-flex h-6 items-center gap-1 rounded-md px-1.5 text-[11px] font-semibold"
                        @click="startUpload(row.text, speed)"
                    >
                        <Upload class="size-3" aria-hidden="true" />
                        Upload own
                    </button>
                </template>
            </div>
        </div>

        <div
            v-if="!readOnly && rows.length > 0"
            class="flex items-center gap-2"
        >
            <Button
                type="button"
                variant="outline"
                :disabled="busy || incomplete.length === 0"
                data-test="generate-audio-button"
                data-tour="generate-audio"
                class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                @click="generateAll"
            >
                <Volume2 class="size-3.5" aria-hidden="true" />
                {{
                    incomplete.length === 0
                        ? 'All clips ready'
                        : `Generate TTS audio (${incomplete.length})`
                }}
            </Button>
            <span v-if="pending" class="text-ink-faint text-[11.5px]">
                Generating in the background…
            </span>
        </div>

        <LessonsUploadDialog
            v-model:open="uploadOpen"
            kind="audio"
            library="my_images"
            @uploaded="onUploaded"
        />
    </div>
</template>
