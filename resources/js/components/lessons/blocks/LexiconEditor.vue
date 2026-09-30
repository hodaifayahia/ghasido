<script setup lang="ts">
import { CirclePlus, Languages, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LexiconItemDialog from '@/components/lessons/blocks/LexiconItemDialog.vue';
import { settingField } from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonsImageLibrary,
    LexiconItemRow,
} from '@/types';

/**
 * Vocabulary / Useful Expressions block (spec 0003 B.10, photo_2/photo_3): the
 * block's intro copy plus the word and expression rows. Each row is its own
 * lexicon record, edited in place with an AI draft and stored audio (CMS-06,
 * CTRL-02, GEN-01, TTS-01..03).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const sideTitle = settingField(settings, 'side_title');
const tip = settingField(settings, 'tip');

const kind = computed<'word' | 'expression'>(() =>
    props.block.type === 'expressions' ? 'expression' : 'word',
);

const dialogOpen = ref(false);
const editingItem = ref<LexiconItemRow | null>(null);

function openAdd(): void {
    editingItem.value = null;
    dialogOpen.value = true;
}

function openEdit(item: LexiconItemRow): void {
    editingItem.value = item;
    dialogOpen.value = true;
}

// After a save / AI draft / audio reload, the block's items are new objects;
// keep the open dialog pointed at the fresh row so it shows the latest state.
watch(
    () => props.block.lexiconItems,
    (items) => {
        if (editingItem.value !== null) {
            editingItem.value =
                items.find((row) => row.id === editingItem.value?.id) ??
                editingItem.value;
        }
    },
);

function texts(item: LexiconItemRow): string[] {
    return [item.englishText, item.hotelExample ?? ''].filter(
        (text) => text.trim() !== '',
    );
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
        <LessonsField
            meaning
            v-model="sideTitle"
            :label="$t('Side panel title')"
            :hint="$t('e.g. “Related Words”.')"
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
                    {{ $t('Words & expressions') }}
                </span>
                <Button
                    v-if="!readOnly"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    data-tour="add-lexicon-item"
                    @click="openAdd"
                >
                    <CirclePlus class="size-3.5" aria-hidden="true" />
                    {{
                        kind === 'expression'
                            ? $t('Add expression')
                            : $t('Add word')
                    }}
                </Button>
            </div>
            <p
                v-if="block.lexiconItems.length === 0"
                class="text-ink-muted text-[12.5px]"
            >
                {{
                    $t(
                        'No words or expressions are attached to this block yet.',
                    )
                }}
            </p>
            <div
                v-for="item in block.lexiconItems"
                :key="item.id"
                class="border-line bg-surface grid gap-2 rounded-md border p-3"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-ink text-[13.5px] font-semibold">
                        {{ item.englishText }}
                    </span>
                    <span
                        v-if="item.partOfSpeech"
                        class="text-ink-faint text-[11.5px] italic"
                    >
                        {{ item.partOfSpeech }}
                    </span>
                    <span
                        v-if="item.showMeaningEnabled"
                        class="bg-brand-50 text-brand-700 rounded-pill inline-flex items-center gap-1 px-2 py-0.5 text-[10.5px] font-semibold"
                    >
                        <Languages class="size-3" aria-hidden="true" />
                        {{ $t('Show Meaning') }}
                    </span>
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="text-brand-700 hover:bg-brand-50 ms-auto inline-flex h-7 items-center gap-1 rounded-md px-2 text-[11.5px] font-semibold"
                        :data-test="`lexicon-${item.id}-edit`"
                        @click="openEdit(item)"
                    >
                        <Pencil class="size-3" aria-hidden="true" />
                        {{ $t('Edit') }}
                    </button>
                </div>
                <p
                    v-if="item.arabicMeaning"
                    class="font-arabic text-ink text-[13px]"
                    dir="rtl"
                    lang="ar"
                >
                    {{ item.arabicMeaning }}
                </p>
                <p
                    v-if="item.simpleExplanation"
                    class="text-ink-muted text-[12px]"
                >
                    {{ item.simpleExplanation }}
                </p>
                <LessonsAudioChips
                    v-if="texts(item).length > 0"
                    :texts="texts(item)"
                    :audio="item.audio"
                    :read-only="readOnly"
                />
            </div>
        </div>

        <LexiconItemDialog
            v-model:open="dialogOpen"
            :block="block"
            :kind="kind"
            :item="editingItem"
            :library="library"
            :read-only="readOnly"
        />
    </div>
</template>
