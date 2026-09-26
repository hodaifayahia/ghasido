<script setup lang="ts">
import { CircleAlert, LoaderCircle, RotateCcw, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { useContentGeneration } from '@/components/lessons/useContentGeneration';
import { Button } from '@/components/ui/button';
import { image as imageRoute } from '@/routes/lesson-generations';
import type { ContentGenerationMedia } from '@/types';

/**
 * "Generate image" for one editor slot (GEN-01, GEN-04, MED-07; spec 0004).
 * The picture is made by a queued job; this dialog polls until it lands,
 * then hands it to the slot, which stores it like a picked library image.
 * Alt text is required, so the image is never stored without one (MED-07).
 */
type Props = {
    defaultPrompt?: string;
    size?: 'landscape' | 'square';
};

const props = withDefaults(defineProps<Props>(), {
    defaultPrompt: '',
    size: 'landscape',
});
const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    generated: [media: ContentGenerationMedia];
}>();

const prompt = ref(props.defaultPrompt);
const alt = ref('');

const {
    generation,
    error,
    submitting,
    finished,
    failed,
    start,
    retryGeneration,
    reset,
} = useContentGeneration();

const working = computed(
    () => generation.value !== null && !finished.value && !failed.value,
);

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen) {
            reset();
            prompt.value = props.defaultPrompt;
            alt.value = '';
        }
    },
);

watch(finished, (isDone) => {
    const media = generation.value?.media ?? null;

    if (isDone && media !== null) {
        emit('generated', media);
        open.value = false;
    }
});

async function submit(): Promise<void> {
    if (prompt.value.trim().length < 3 || alt.value.trim() === '') {
        return;
    }

    const body = new FormData();
    body.append('prompt', prompt.value.trim());
    body.append('alt', alt.value.trim());
    body.append('size', props.size);

    await start(imageRoute.url(), body);
}

const fieldLabel = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const field =
    'border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t('Generate an image')"
        :description="
            $t(
                'Describe the picture. It is created by the AI, saved in My Images and placed in this slot.',
            )
        "
    >
        <form class="mt-2 grid gap-3" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <label for="ai-image-prompt" :class="fieldLabel">
                    {{ $t('Picture description') }}
                    <span class="text-danger-text">*</span>
                </label>
                <textarea
                    id="ai-image-prompt"
                    v-model="prompt"
                    rows="3"
                    maxlength="1000"
                    required
                    :disabled="working"
                    :placeholder="
                        $t(
                            'e.g. A smiling receptionist hands a key card to a guest at a modern hotel desk',
                        )
                    "
                    :class="[field, 'min-h-20 resize-y py-2 leading-6']"
                    data-test="ai-image-prompt"
                />
            </div>
            <div class="grid gap-1.5">
                <label for="ai-image-alt" :class="fieldLabel">
                    {{ $t('Alt text') }}
                    <span class="text-danger-text">*</span>
                </label>
                <input
                    id="ai-image-alt"
                    v-model="alt"
                    type="text"
                    maxlength="250"
                    required
                    :disabled="working"
                    :placeholder="$t('What the picture shows, in a few words')"
                    :class="[field, 'h-11 sm:h-10']"
                    data-test="ai-image-alt"
                />
            </div>

            <p
                v-if="working"
                class="text-brand-700 bg-brand-50/60 flex items-center gap-2 rounded-md px-3 py-2 text-[12.5px] font-medium"
                aria-live="polite"
            >
                <LoaderCircle
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                {{ $t('Creating the image… this can take a minute.') }}
            </p>

            <p
                v-if="failed || error"
                class="bg-danger-tint text-danger-text flex items-start gap-2 rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ generation?.failedReason ?? error }}
            </p>

            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none sm:h-10"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <!-- After a failure the description can be edited and sent
                     again (Generate), or the same request retried (GEN-04). -->
                <Button
                    v-if="failed"
                    type="button"
                    variant="outline"
                    :disabled="submitting"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none sm:h-10"
                    data-test="ai-image-retry-button"
                    @click="retryGeneration"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                    {{ $t('Retry') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="
                        working ||
                        submitting ||
                        prompt.trim().length < 3 ||
                        alt.trim() === ''
                    "
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] sm:h-10"
                    data-test="ai-image-generate-button"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    {{ $t('Generate') }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
