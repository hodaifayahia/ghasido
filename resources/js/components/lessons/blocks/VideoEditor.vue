<script setup lang="ts">
import { computed } from 'vue';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import {
    nestedField,
    settingField,
    settingObject,
    useMediaLookup,
} from '@/components/lessons/lessonsBlocks';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * Video block (spec 0003 B.10, photo_6): the poster and optional video file,
 * the controls copy, and the "Example from the Video" sentence whose Arabic
 * stays behind Show Meaning (CTRL-02) and whose text drives the stored audio
 * (CTRL-05).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const controlsTitle = settingField(settings, 'controls_title');
const controlsNote = settingField(settings, 'controls_note');
const exampleTitle = settingField(settings, 'example_title');
const exampleText = nestedField(settings, 'example', 'text');
const exampleArabic = nestedField(settings, 'example', 'arabic');
const exampleNote = nestedField(settings, 'example', 'note');
const tip = settingField(settings, 'tip');

const media = useMediaLookup(() => props.block.media);

const exampleTexts = computed(() => {
    const text = settingObject(settings.value, 'example').text;

    return typeof text === 'string' && text.trim() !== '' ? [text] : [];
});

function onVideo(item: LessonMediaRef | null): void {
    media.remember(item);
    settings.value = { ...settings.value, video: item?.id ?? null };
}

function onPoster(item: LessonMediaRef | null): void {
    media.remember(item);
    settings.value = { ...settings.value, poster: item?.id ?? null };
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            v-model="subtitle"
            :label="$t('Subtitle')"
            type="textarea"
            :rows="2"
        />
        <div class="grid gap-4 md:grid-cols-2">
            <LessonsMediaSlot
                label="Video file"
                kind="video"
                :media="media.lookup(settings.video)"
                @change="onVideo"
            />
            <LessonsMediaSlot
                label="Poster image"
                :media="media.lookup(settings.poster)"
                :tabs="library.tabs"
                :categories="library.categories"
                @change="onPoster"
            />
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <LessonsField
                v-model="controlsTitle"
                :label="$t('Controls title')"
            />
            <LessonsField v-model="controlsNote" :label="$t('Controls note')" />
        </div>
        <LessonsField v-model="exampleTitle" :label="$t('Example title')" />
        <LessonsField v-model="exampleText" :label="$t('Example sentence')" />
        <LessonsField
            v-model="exampleArabic"
            :label="$t('Example Arabic (behind Show Meaning)')"
            dir="rtl"
        />
        <LessonsField v-model="exampleNote" :label="$t('Example note')" />
        <LessonsField
            v-model="tip"
            :label="$t('Tip')"
            type="textarea"
            :rows="2"
        />
        <LessonsAudioChips
            v-if="exampleTexts.length > 0"
            :texts="exampleTexts"
            :audio="block.audio"
            :read-only="readOnly"
        />
    </div>
</template>
