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
 * recorder labels, and the sentences the learner repeats. Each sentence's
 * meaning is a Show Meaning translation of its English in any helper
 * language, written with Translate meaning (CTRL-02; client request
 * 2026-11-05: no fixed Arabic field); its text drives the stored audio
 * (CTRL-05).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

/** `arabic` is the old fixed field, kept only until the English changes. */
type Item = { text: string; arabic?: string | null; image: number | null };

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

function setText(index: number, text: string): void {
    setItems(
        items.value.map((row, i) => {
            if (i !== index) {
                return row;
            }

            // An old Arabic belongs to the English it was written for.
            const next: Item = { ...row, text };
            delete next.arabic;

            return next;
        }),
    );
}

function onItemImage(index: number, item: LessonMediaRef | null): void {
    media.remember(item);
    updateItem(index, { image: item?.id ?? null });
}

function addItem(): void {
    setItems([...items.value, { text: '', image: null }]);
}

function removeItem(index: number): void {
    setItems(items.value.filter((_, i) => i !== index));
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            meaning
            v-model="subtitle"
            :label="$t('Subtitle')"
            type="textarea"
            :rows="2"
        />
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <LessonsField
                    meaning
                    v-model="listenTitle"
                    :label="$t('Listen step title')"
                />
                <LessonsField
                    meaning
                    v-model="listenText"
                    :label="$t('Listen step text')"
                    type="textarea"
                    :rows="2"
                />
            </div>
            <div class="grid gap-2">
                <LessonsField
                    meaning
                    v-model="repeatTitle"
                    :label="$t('Repeat step title')"
                />
                <LessonsField
                    meaning
                    v-model="repeatText"
                    :label="$t('Repeat step text')"
                    type="textarea"
                    :rows="2"
                />
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <LessonsField
                meaning
                v-model="recordLabel"
                :label="$t('Record label')"
            />
            <LessonsField
                meaning
                v-model="recordingLabel"
                :label="$t('Recording label')"
            />
            <LessonsField
                meaning
                v-model="successText"
                :label="$t('Success text')"
            />
        </div>
        <LessonsField
            meaning
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
                            meaning
                            :model-value="item.text"
                            :label="$t('English')"
                            @update:model-value="
                                (value) =>
                                    setText(
                                        index,
                                        typeof value === 'string' ? value : '',
                                    )
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
