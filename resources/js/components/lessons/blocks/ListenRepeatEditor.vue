<script setup lang="ts">
import { CirclePlus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import {
    nestedField,
    settingField,
    settingList,
    useMediaLookup,
} from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * Listen & Repeat block (spec 0003 B.10, photo_4): the two step captions, the
 * recorder labels, and the sentences the learner repeats. Each sentence keeps
 * its Arabic behind Show Meaning (CTRL-02); its text drives the stored audio
 * (CTRL-05).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

type Item = { text: string; arabic: string; image: number | null };

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const tip = settingField(settings, 'tip');
const listenTitle = nestedField(settings, 'step_listen', 'title');
const listenText = nestedField(settings, 'step_listen', 'text');
const repeatTitle = nestedField(settings, 'step_repeat', 'title');
const repeatText = nestedField(settings, 'step_repeat', 'text');
const recordLabel = settingField(settings, 'record_label');
const recordingLabel = settingField(settings, 'recording_label');
const successText = settingField(settings, 'success_text');

const media = useMediaLookup(() => props.block.media);

const items = computed(() => settingList<Item>(settings.value, 'items'));

const itemTexts = computed(() =>
    items.value.map((item) => item.text).filter((text) => text.trim() !== ''),
);

function setItems(next: Item[]): void {
    settings.value = { ...settings.value, items: next };
}

function updateItem(index: number, patch: Partial<Item>): void {
    setItems(
        items.value.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    );
}

function onItemImage(index: number, item: LessonMediaRef | null): void {
    media.remember(item);
    updateItem(index, { image: item?.id ?? null });
}

function addItem(): void {
    setItems([...items.value, { text: '', arabic: '', image: null }]);
}

function removeItem(index: number): void {
    setItems(items.value.filter((_, i) => i !== index));
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            translatable
            v-model="subtitle"
            :label="$t('Subtitle')"
            type="textarea"
            :rows="2"
        />
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <LessonsField
                    v-model="listenTitle"
                    :label="$t('Listen step title')"
                />
                <LessonsField
                    v-model="listenText"
                    :label="$t('Listen step text')"
                    type="textarea"
                    :rows="2"
                />
            </div>
            <div class="grid gap-2">
                <LessonsField
                    v-model="repeatTitle"
                    :label="$t('Repeat step title')"
                />
                <LessonsField
                    v-model="repeatText"
                    :label="$t('Repeat step text')"
                    type="textarea"
                    :rows="2"
                />
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <LessonsField v-model="recordLabel" :label="$t('Record label')" />
            <LessonsField
                v-model="recordingLabel"
                :label="$t('Recording label')"
            />
            <LessonsField v-model="successText" :label="$t('Success text')" />
        </div>
        <LessonsField
            translatable
            v-model="tip"
            :label="$t('Tip')"
            type="textarea"
            :rows="2"
        />

        <div class="grid gap-2">
            <div class="flex items-center justify-between gap-3">
                <span
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ $t('Sentences') }}
                </span>
                <Button
                    v-if="!readOnly"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="addItem"
                >
                    <CirclePlus class="size-3.5" aria-hidden="true" />
                    {{ $t('Add sentence') }}
                </Button>
            </div>

            <div
                v-for="(item, index) in items"
                :key="index"
                class="border-line bg-app-alt grid gap-2 rounded-md border p-3"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="grid flex-1 gap-2">
                        <LessonsField
                            :model-value="item.text"
                            :label="$t('English')"
                            @update:model-value="
                                (value) =>
                                    updateItem(index, {
                                        text:
                                            typeof value === 'string'
                                                ? value
                                                : '',
                                    })
                            "
                        />
                        <LessonsField
                            :model-value="item.arabic"
                            :label="$t('Arabic (behind Show Meaning)')"
                            dir="rtl"
                            @update:model-value="
                                (value) =>
                                    updateItem(index, {
                                        arabic:
                                            typeof value === 'string'
                                                ? value
                                                : '',
                                    })
                            "
                        />
                    </div>
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="text-ink-faint hover:bg-danger-tint hover:text-danger-text inline-flex size-9 items-center justify-center rounded-md"
                        :aria-label="
                            $t('Remove sentence :number', { number: index + 1 })
                        "
                        @click="removeItem(index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <LessonsMediaSlot
                    label="Image"
                    compact
                    :media="media.lookup(item.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    @change="(picked) => onItemImage(index, picked)"
                />
            </div>
        </div>

        <LessonsAudioChips
            v-if="itemTexts.length > 0"
            :texts="itemTexts"
            :audio="block.audio"
            :read-only="readOnly"
        />
    </div>
</template>
