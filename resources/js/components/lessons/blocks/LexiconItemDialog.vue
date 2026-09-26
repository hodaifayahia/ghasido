<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Sparkles, Volume2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { useMediaLookup } from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { applyDraft, audio, generate, store, update } from '@/routes/lexicon';
import type {
    LessonBlockRow,
    LessonMediaRef,
    LessonsImageLibrary,
    LexiconItemRow,
} from '@/types';

/**
 * Add or edit one word / expression (CMS-06, CTRL-03, GEN-01, GEN-03, GEN-04,
 * TTS-01..03). The admin fills the fields by hand, or asks the AI for a draft
 * and applies it explicitly (nothing AI is auto-published); audio is generated
 * once and served from the stored file (CTRL-05).
 */
type Props = {
    block: LessonBlockRow;
    kind: 'word' | 'expression';
    item: LexiconItemRow | null;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

type FormState = {
    english_text: string;
    part_of_speech: string;
    ipa: string;
    arabic_meaning: string;
    simple_explanation: string;
    hotel_example: string;
    hotel_example_arabic: string;
    image_media_id: number | null;
    show_meaning_enabled: boolean;
};

function blank(): FormState {
    return {
        english_text: '',
        part_of_speech: '',
        ipa: '',
        arabic_meaning: '',
        simple_explanation: '',
        hotel_example: '',
        hotel_example_arabic: '',
        image_media_id: null,
        show_meaning_enabled: true,
    };
}

const form = ref<FormState>(blank());
const errors = ref<Record<string, string>>({});
const busy = ref(false);

const media = useMediaLookup(() => props.block.media);

const isEdit = computed(() => props.item !== null);
const draft = computed(() => props.item?.aiDraft ?? null);
const aiPending = computed(
    () =>
        props.item?.aiStatus === 'pending' ||
        props.item?.aiStatus === 'running',
);

function fromItem(): void {
    const i = props.item;

    form.value = i
        ? {
              english_text: i.englishText,
              part_of_speech: i.partOfSpeech ?? '',
              ipa: i.ipa ?? '',
              arabic_meaning: i.arabicMeaning ?? '',
              simple_explanation: i.simpleExplanation ?? '',
              hotel_example: i.hotelExample ?? '',
              hotel_example_arabic: i.hotelExampleArabic ?? '',
              image_media_id: i.imageId,
              show_meaning_enabled: i.showMeaningEnabled,
          }
        : blank();
    errors.value = {};
}

watch(open, (isOpen) => {
    if (isOpen) {
        fromItem();
    }
});

watch(
    () => props.item,
    () => {
        if (open.value) {
            fromItem();
        }
    },
    { deep: true },
);

const audioTexts = computed(() =>
    [form.value.english_text, form.value.hotel_example].filter(
        (text) => text.trim() !== '',
    ),
);

const options = {
    preserveState: true,
    preserveScroll: true,
    onError: (bag: Record<string, string>): void => {
        errors.value = bag;
    },
    onFinish: (): void => {
        busy.value = false;
    },
};

function payload(): Record<string, string | number | boolean | null> {
    const f = form.value;

    return {
        english_text: f.english_text,
        part_of_speech: f.part_of_speech || null,
        ipa: f.ipa || null,
        arabic_meaning: f.arabic_meaning || null,
        simple_explanation: f.simple_explanation || null,
        hotel_example: f.hotel_example || null,
        hotel_example_arabic: f.hotel_example_arabic || null,
        image_media_id: f.image_media_id,
        show_meaning_enabled: f.show_meaning_enabled,
    };
}

function save(): void {
    if (props.readOnly) {
        return;
    }

    busy.value = true;
    errors.value = {};

    if (isEdit.value && props.item) {
        router.patch(update.url(props.item.id), payload(), {
            ...options,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    router.post(
        store.url(),
        { ...payload(), kind: props.kind, block_id: props.block.id },
        {
            ...options,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}

function generateDraft(): void {
    if (props.item === null) {
        return;
    }

    busy.value = true;
    router.post(generate.url(props.item.id), {}, options);
}

function applyAiDraft(): void {
    if (props.item === null || draft.value === null) {
        return;
    }

    busy.value = true;
    router.post(
        applyDraft.url(props.item.id),
        {
            arabic_meaning: draft.value.arabic_meaning,
            simple_explanation: draft.value.simple_explanation,
            hotel_example: draft.value.hotel_example,
            hotel_example_arabic: draft.value.hotel_example_arabic,
            ipa: draft.value.ipa,
            part_of_speech: draft.value.part_of_speech,
        },
        options,
    );
}

function generateAudio(): void {
    if (props.item === null) {
        return;
    }

    busy.value = true;
    router.post(audio.url(props.item.id), {}, options);
}

function onImage(item: LessonMediaRef | null): void {
    media.remember(item);
    form.value.image_media_id = item?.id ?? null;
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="
            isEdit
                ? kind === 'expression'
                    ? $t('Edit expression')
                    : $t('Edit word')
                : kind === 'expression'
                  ? $t('Add expression')
                  : $t('Add word')
        "
        :description="
            $t(
                'The English is always shown; the Arabic and explanation stay behind Show Meaning.',
            )
        "
        size="lg"
    >
        <div class="mt-2 grid gap-4">
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_140px]">
                <LessonsField
                    data-tour="lexicon-english-field"
                    v-model="form.english_text"
                    :label="$t('English')"
                    :error="errors.english_text"
                />
                <LessonsField
                    v-model="form.part_of_speech"
                    :label="$t('Part of speech')"
                />
            </div>
            <LessonsField
                v-model="form.ipa"
                :label="$t('Pronunciation (IPA)')"
            />

            <LessonsMediaSlot
                label="Image"
                :media="media.lookup(form.image_media_id)"
                :tabs="library.tabs"
                :categories="library.categories"
                @change="onImage"
            />

            <div class="grid gap-4 md:grid-cols-2">
                <LessonsField
                    data-tour="lexicon-meaning-field"
                    v-model="form.arabic_meaning"
                    :label="$t('Arabic meaning')"
                    type="textarea"
                    :rows="2"
                    dir="rtl"
                    :error="errors.arabic_meaning"
                />
                <LessonsField
                    v-model="form.simple_explanation"
                    :label="$t('Simple explanation')"
                    type="textarea"
                    :rows="2"
                    :error="errors.simple_explanation"
                />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <LessonsField
                    v-model="form.hotel_example"
                    :label="$t('Hotel example')"
                    type="textarea"
                    :rows="2"
                    :error="errors.hotel_example"
                />
                <LessonsField
                    v-model="form.hotel_example_arabic"
                    :label="$t('Hotel example (Arabic)')"
                    type="textarea"
                    :rows="2"
                    dir="rtl"
                />
            </div>

            <label class="flex items-center gap-2 text-[12.5px]">
                <Checkbox
                    v-model="form.show_meaning_enabled"
                    :disabled="readOnly"
                />
                <span class="text-ink">{{
                    $t('Show Meaning available for this item')
                }}</span>
            </label>

            <div
                v-if="isEdit"
                class="border-ai/30 bg-ai-tint/50 grid gap-2 rounded-md border p-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span
                        class="text-ai flex items-center gap-1.5 text-[12px] font-semibold"
                    >
                        <Sparkles class="size-3.5" aria-hidden="true" />
                        {{ $t('AI assistant') }}
                    </span>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="!readOnly"
                            type="button"
                            variant="outline"
                            :disabled="busy || aiPending"
                            class="border-ai/40 text-ai hover:bg-ai-tint h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                            @click="generateDraft"
                        >
                            {{
                                aiPending
                                    ? $t('Generating…')
                                    : $t('Generate with AI')
                            }}
                        </Button>
                        <Button
                            v-if="!readOnly && draft"
                            type="button"
                            :disabled="busy"
                            class="bg-ai h-8 gap-1 rounded-md px-3 text-[12px] font-semibold text-white shadow-none hover:opacity-90"
                            @click="applyAiDraft"
                        >
                            {{ $t('Apply draft') }}
                        </Button>
                    </div>
                </div>
                <p
                    v-if="!draft && !aiPending"
                    class="text-ink-muted text-[12px]"
                >
                    {{
                        $t(
                            'Ask the AI to suggest the Arabic meaning, a simple explanation and a hotel example. Nothing is saved until you apply it.',
                        )
                    }}
                </p>
                <div v-else-if="draft" class="grid gap-1 text-[12px]">
                    <p
                        v-if="draft.arabic_meaning"
                        dir="rtl"
                        lang="ar"
                        class="font-arabic"
                    >
                        {{ draft.arabic_meaning }}
                    </p>
                    <p v-if="draft.simple_explanation" class="text-ink-muted">
                        {{ draft.simple_explanation }}
                    </p>
                    <p v-if="draft.hotel_example" class="text-ink-muted italic">
                        “{{ draft.hotel_example }}”
                    </p>
                </div>
            </div>

            <div v-if="isEdit" class="grid gap-2">
                <div class="flex items-center justify-between gap-2">
                    <span
                        class="text-brand-900 flex items-center gap-1.5 text-[12px] font-semibold tracking-[0.02em]"
                    >
                        <Volume2 class="size-3.5" aria-hidden="true" />
                        {{ $t('Audio') }}
                    </span>
                    <Button
                        v-if="!readOnly"
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="generateAudio"
                    >
                        {{ $t('Generate audio') }}
                    </Button>
                </div>
                <LessonsAudioChips
                    v-if="item && audioTexts.length > 0"
                    :texts="audioTexts"
                    :audio="item.audio"
                    read-only
                />
            </div>

            <p
                v-if="errors._"
                class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                {{ errors._ }}
            </p>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="open = false"
                >
                    {{ readOnly ? $t('Close') : $t('Cancel') }}
                </Button>
                <Button
                    v-if="!readOnly"
                    type="button"
                    :disabled="busy"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-tour="save-lexicon-item"
                    @click="save"
                >
                    {{ busy ? $t('Saving…') : $t('Save') }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
