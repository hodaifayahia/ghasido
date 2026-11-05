<script setup lang="ts">
import { CirclePlus, Trash2 } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import {
    settingField,
    settingList,
    useMediaLookup,
} from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tk } from '@/lib/i18n';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * Dialogue block (spec 0003 B.10, photo_5): the scene photo and staff avatar,
 * the caption and tip, and the ordered conversation lines. Each line has a
 * speaker and its English, whose meaning is a Show Meaning translation in
 * any helper language written with Translate meaning (CTRL-02; client
 * request 2026-11-05: no fixed Arabic field); the line texts drive the
 * stored audio (CTRL-05).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

/** `arabic` is the old fixed field, kept only until the English changes. */
type Line = { speaker: string; text: string; arabic?: string | null };

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const situationCaption = settingField(settings, 'situation_caption');
const tip = settingField(settings, 'tip');

const media = useMediaLookup(() => props.block.media);

const lines = computed(() => settingList<Line>(settings.value, 'lines'));

const lineTexts = computed(() =>
    lines.value.map((line) => line.text).filter((text) => text.trim() !== ''),
);

const speakers = [
    { value: 'staff', label: tk('Staff') },
    { value: 'guest', label: tk('Guest') },
];

function onImage(item: LessonMediaRef | null): void {
    media.remember(item);
    settings.value = { ...settings.value, image: item?.id ?? null };
}

function onAvatar(item: LessonMediaRef | null): void {
    media.remember(item);
    settings.value = { ...settings.value, staff_avatar: item?.id ?? null };
}

function setLines(next: Line[]): void {
    settings.value = { ...settings.value, lines: next };
}

function updateLine(index: number, patch: Partial<Line>): void {
    setLines(
        lines.value.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    );
}

function setText(index: number, text: string): void {
    setLines(
        lines.value.map((row, i) => {
            if (i !== index) {
                return row;
            }

            // An old Arabic belongs to the English it was written for.
            const next: Line = { ...row, text };
            delete next.arabic;

            return next;
        }),
    );
}

function onSpeaker(index: number, value: AcceptableValue): void {
    if (typeof value === 'string') {
        updateLine(index, { speaker: value });
    }
}

function addLine(): void {
    setLines([...lines.value, { speaker: 'staff', text: '' }]);
}

function removeLine(index: number): void {
    setLines(lines.value.filter((_, i) => i !== index));
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
            <LessonsMediaSlot
                label="Scene image"
                :media="media.lookup(settings.image)"
                :tabs="library.tabs"
                :categories="library.categories"
                @change="onImage"
            />
            <LessonsMediaSlot
                label="Staff avatar"
                :media="media.lookup(settings.staff_avatar)"
                :tabs="library.tabs"
                :categories="library.categories"
                @change="onAvatar"
            />
        </div>
        <LessonsField
            meaning
            v-model="situationCaption"
            :label="$t('Situation caption')"
        />
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
                    {{ $t('Conversation lines') }}
                </span>
                <Button
                    v-if="!readOnly"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    data-tour="add-dialogue-line"
                    @click="addLine"
                >
                    <CirclePlus class="size-3.5" aria-hidden="true" />
                    {{ $t('Add line') }}
                </Button>
            </div>

            <div
                v-for="(line, index) in lines"
                :key="index"
                class="border-line bg-app-alt grid gap-2 rounded-md border p-3"
            >
                <div class="flex items-center justify-between gap-3">
                    <Select
                        :model-value="line.speaker"
                        :disabled="readOnly"
                        @update:model-value="onSpeaker(index, $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-9 w-32 rounded-sm text-[13px] shadow-none"
                            :aria-label="
                                $t('Speaker of line :number', {
                                    number: index + 1,
                                })
                            "
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="speaker in speakers"
                                :key="speaker.value"
                                :value="speaker.value"
                                class="text-[13px]"
                            >
                                {{ $t(speaker.label) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="text-ink-faint hover:bg-danger-tint hover:text-danger-text inline-flex size-9 items-center justify-center rounded-md"
                        :aria-label="
                            $t('Remove line :number', { number: index + 1 })
                        "
                        @click="removeLine(index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <LessonsField
                    meaning
                    :model-value="line.text"
                    data-tour="dialogue-english-field"
                    :label="$t('English')"
                    type="textarea"
                    :rows="2"
                    @update:model-value="
                        (value) =>
                            setText(
                                index,
                                typeof value === 'string' ? value : '',
                            )
                    "
                />
            </div>
        </div>

        <LessonsAudioChips
            v-if="lineTexts.length > 0"
            :texts="lineTexts"
            :audio="block.audio"
            :read-only="readOnly"
        />
    </div>
</template>
