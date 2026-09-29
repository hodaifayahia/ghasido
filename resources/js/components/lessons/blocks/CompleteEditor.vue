<script setup lang="ts">
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
 * Lesson Completed block (spec 0003 B.10): subtitle, image, the two quotes
 * and the encouragement paragraph.
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const quote = settingField(settings, 'quote');
const closingQuote = settingField(settings, 'closing_quote');
const encouragement = settingField(settings, 'encouragement');

const media = useMediaLookup(() => props.block.media);

function onImage(item: LessonMediaRef | null): void {
    media.remember(item);
    settings.value = { ...settings.value, image: item?.id ?? null };
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField translatable v-model="subtitle" :label="$t('Subtitle')" />
        <LessonsMediaSlot
            label="Image"
            :media="media.lookup(settings.image)"
            :tabs="library.tabs"
            :categories="library.categories"
            @change="onImage"
        />
        <div class="grid gap-4 md:grid-cols-2">
            <LessonsField
                translatable
                v-model="quote"
                :label="$t('Quote')"
                type="textarea"
                :rows="2"
            />
            <LessonsField
                v-model="closingQuote"
                :label="$t('Closing quote')"
                type="textarea"
                :rows="2"
            />
        </div>
        <LessonsField
            v-model="encouragement"
            :label="$t('Encouragement')"
            type="textarea"
            :rows="4"
        />
    </div>
</template>
