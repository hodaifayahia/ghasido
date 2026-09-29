<script setup lang="ts">
import { computed } from 'vue';
import { tk } from '@/lib/i18n';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import type {
    LessonAudioPair,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';
import type { AudioChipOptions } from './activityEditor';

/**
 * What the learner hears with a question (client report 2026-09-29): a
 * sentence played from stored text-to-speech, generated once (CTRL-05,
 * TTS-02), and / or the admin's own clip — uploaded, recorded in the
 * browser or chosen from the library.
 */
type Props = {
    audioText: string;
    media: LessonMediaRef | null;
    required: boolean;
    audio: Record<string, LessonAudioPair>;
    library: LessonsImageLibrary;
    chips: AudioChipOptions;
    readOnly: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:audioText': [value: string];
    change: [media: LessonMediaRef | null];
}>();

/** Translated inside LessonsMediaSlot. */
const clipLabel = tk('Your own audio clip');

const texts = computed(() =>
    props.audioText.trim() === '' ? [] : [props.audioText.trim()],
);
</script>

<template>
    <fieldset
        class="border-line grid gap-3 rounded-md border border-dashed p-3"
    >
        <legend
            class="text-brand-900 px-1 text-[12px] font-semibold tracking-[0.02em]"
        >
            {{
                required
                    ? $t('Audio the learner hears')
                    : $t('Audio the learner hears (optional)')
            }}
        </legend>
        <div class="grid gap-2">
            <LessonsField
                :model-value="audioText"
                :label="$t('Sentence to play (generated voice)')"
                :hint="
                    $t(
                        'Write it, then generate the audio once. Or add your own clip below.',
                    )
                "
                @update:model-value="
                    emit('update:audioText', String($event ?? ''))
                "
            />
            <LessonsAudioChips
                v-if="texts.length > 0"
                :texts="texts"
                :audio="audio"
                :read-only="readOnly"
                :lesson-id="chips.lessonId"
                :reload-only="chips.reloadOnly"
            />
        </div>
        <LessonsMediaSlot
            :label="clipLabel"
            kind="audio"
            :media="media"
            :tabs="library.tabs"
            :categories="library.categories"
            compact
            @change="emit('change', $event)"
        />
    </fieldset>
</template>
