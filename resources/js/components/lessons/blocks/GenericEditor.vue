<script setup lang="ts">
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import {
    settingField,
    useMediaLookup,
} from '@/components/lessons/lessonsBlocks';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * Text / Note / Image / Audio blocks (spec 0003 B.10, the generic contract):
 * a body, its Arabic (shown only behind Show Meaning, CTRL-02), an image and
 * a playable sentence with its two clips (CTRL-05).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const body = settingField(settings, 'body');
const arabic = settingField(settings, 'arabic');
const audioText = settingField(settings, 'audio_text');

const media = useMediaLookup(() => props.block.media);

function onImage(item: LessonMediaRef | null): void {
    media.remember(item);
    settings.value = { ...settings.value, image: item?.id ?? null };
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            v-model="body"
            :label="block.type === 'note' ? 'Note' : 'Text'"
            type="textarea"
            :rows="4"
        />
        <LessonsField
            v-model="arabic"
            label="Arabic (behind Show Meaning)"
            type="textarea"
            :rows="2"
            dir="rtl"
        />
        <LessonsMediaSlot
            v-if="block.type !== 'audio'"
            label="Image"
            :media="media.lookup(settings.image)"
            :tabs="library.tabs"
            :categories="library.categories"
            @change="onImage"
        />
        <div class="grid gap-2">
            <LessonsField
                v-model="audioText"
                label="Sentence to play"
                hint="Audio is generated once per sentence and served from the stored file."
            />
            <LessonsAudioChips
                v-if="typeof audioText === 'string' && audioText !== ''"
                :texts="[audioText]"
                :audio="block.audio"
                :read-only="readOnly"
            />
        </div>
    </div>
</template>
