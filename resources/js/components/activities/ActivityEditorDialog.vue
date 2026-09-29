<script setup lang="ts">
import { ArrowLeft, CirclePlus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type {
    ActivityTypeSpec,
    BlockActivityMode,
    ItemDraft,
} from '@/components/activities/activityCatalog';
import {
    activityTypeSpec,
    blankDraft,
    builderTypes,
    draftFromItem,
    itemFromDraft,
    nextItemId,
} from '@/components/activities/activityCatalog';
import type { AudioChipOptions } from '@/components/activities/activityEditor';
import ActivityItemEditor from '@/components/activities/ActivityItemEditor.vue';
import { toneClass, useMediaLookup } from '@/components/lessons/lessonsBlocks';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    ActivityPayload,
    ActivityTypeKey,
    LessonActivityRow,
    LessonAudioPair,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * The one activity editor (client report 2026-09-29): a lesson Practice
 * activity, a lesson Quiz question and a Pre/Post-test question are all
 * made here, with the same ten types and the same per-type fields (PRAC-05,
 * WRITE-05). A new one starts by choosing its type (or opens straight on
 * the type the caller picked); then the instruction and the item(s) with
 * their answers. The parent posts `save` to its own route, which validates
 * the payload again and writes a new version when the content changes
 * (DATA-11). The type is fixed once saved: a different kind of question is
 * a new activity.
 */
export type ActivitySaveData = {
    type: ActivityTypeKey;
    title: string | null;
    prompt: string;
    attempts_allowed: number;
    payload: ActivityPayload;
};

type Props = {
    /** practice: several items; quiz / test: one question. */
    mode: BlockActivityMode;
    /** Null to add a new one. */
    activity: LessonActivityRow | null;
    /** A new one opens straight on this type. */
    initialType?: ActivityTypeKey | null;
    /** The position a new one takes (for its default title). */
    number: number;
    library: LessonsImageLibrary;
    media: Record<string, LessonMediaRef>;
    audio: Record<string, LessonAudioPair>;
    chips?: AudioChipOptions;
    readOnly: boolean;
    saving: boolean;
    errors: Record<string, string>;
};

const props = withDefaults(defineProps<Props>(), {
    initialType: null,
    chips: () => ({}),
});

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    save: [data: ActivitySaveData];
}>();

const MAX_ITEMS = 6;

const spec = ref<ActivityTypeSpec | null>(null);
const title = ref('');
const prompt = ref('');
const attempts = ref<number | null>(0);
const items = ref<ItemDraft[]>([]);

const media = useMediaLookup(() => props.media);

const isPractice = computed(() => props.mode === 'practice');
const isTest = computed(() => props.mode === 'test');

function start(type: ActivityTypeSpec): void {
    spec.value = type;
    prompt.value = type.defaultPrompt;
    items.value = [blankDraft(type, 'i1')];
}

function reset(): void {
    if (props.activity !== null) {
        const type = activityTypeSpec(props.activity.type);
        spec.value = type;
        title.value = props.activity.title ?? '';
        prompt.value = props.activity.prompt;
        attempts.value = props.activity.attemptsAllowed;
        items.value = props.activity.payload.items.map((item) =>
            draftFromItem(type, item),
        );

        if (items.value.length === 0) {
            items.value = [blankDraft(type, 'i1')];
        }

        return;
    }

    spec.value = null;
    title.value = isPractice.value ? '' : `Question ${props.number}`;
    prompt.value = '';
    attempts.value = isPractice.value ? 0 : 1;
    items.value = [];

    if (props.initialType !== null) {
        start(activityTypeSpec(props.initialType));
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

const heading = computed(() => {
    if (props.activity !== null) {
        return isPractice.value ? t('Edit activity') : t('Edit question');
    }

    return isPractice.value ? t('Add an activity') : t('Add a question');
});

const description = computed(() =>
    spec.value === null
        ? t('Choose the type of question.')
        : `${t(spec.value.label)} · ${t(spec.value.description)}`,
);

function addItem(): void {
    if (spec.value !== null && items.value.length < MAX_ITEMS) {
        items.value = [
            ...items.value,
            blankDraft(spec.value, nextItemId(items.value)),
        ];
    }
}

function removeItem(index: number): void {
    items.value = items.value.filter((_, position) => position !== index);
}

function setItem(index: number, draft: ItemDraft): void {
    items.value = items.value.map((item, position) =>
        position === index ? draft : item,
    );
}

function itemError(index: number): string | undefined {
    return (
        props.errors[`payload.items.${index}`] ??
        props.errors[`payload.items.${index}.id`]
    );
}

const generalError = computed(
    () =>
        props.errors['payload.items'] ??
        props.errors.payload ??
        props.errors.type ??
        props.errors.block_id,
);

function save(): void {
    const type = spec.value;

    if (props.readOnly || type === null) {
        open.value = false;

        return;
    }

    emit('save', {
        type: type.value,
        title: title.value.trim() === '' ? null : title.value.trim(),
        prompt: prompt.value.trim(),
        attempts_allowed: attempts.value ?? 0,
        payload: {
            items: items.value.map((draft) => itemFromDraft(type, draft)),
        },
    });
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="heading"
        :description="description"
        size="lg"
    >
        <!-- Step 1: choose the type -->
        <div
            v-if="spec === null"
            class="mt-2 grid gap-2 sm:grid-cols-2"
            role="list"
            :aria-label="$t('Question types')"
        >
            <button
                v-for="type in builderTypes"
                :key="type.value"
                type="button"
                role="listitem"
                :data-test="`activity-type-${type.value}`"
                class="border-line bg-surface hover:bg-brand-50/60 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 flex min-h-16 items-start gap-3 rounded-md border p-3 text-start transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none"
                @click="start(type)"
            >
                <span
                    :class="
                        cn(
                            'grid size-9 shrink-0 place-items-center rounded-xl',
                            toneClass(type.tone),
                        )
                    "
                >
                    <component
                        :is="type.icon"
                        class="size-4.5"
                        aria-hidden="true"
                    />
                </span>
                <span class="grid min-w-0 gap-0.5">
                    <span class="text-brand-900 text-[13px] font-semibold">
                        {{ $t(type.label) }}
                    </span>
                    <span class="text-ink-slate text-[11.5px] leading-snug">
                        {{ $t(type.description) }}
                    </span>
                </span>
            </button>
        </div>

        <!-- Step 2: the activity itself -->
        <div v-else class="mt-2 grid gap-4">
            <button
                v-if="activity === null && !readOnly"
                type="button"
                class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex items-center gap-1.5 self-start rounded-md text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                data-test="activity-change-type"
                @click="spec = null"
            >
                <ArrowLeft class="size-3.5 rtl:rotate-180" aria-hidden="true" />
                {{ $t('Choose another type') }}
            </button>

            <div
                :class="
                    cn(
                        'grid gap-4',
                        !isTest && 'md:grid-cols-[minmax(0,1fr)_160px]',
                    )
                "
            >
                <LessonsField
                    v-model="title"
                    :label="
                        isPractice
                            ? $t('Title (optional)')
                            : $t('Question title')
                    "
                    :placeholder="$t(spec.label)"
                    :hint="
                        isTest
                            ? $t('Only admins see it.')
                            : $t('Shown on the learner’s card.')
                    "
                    :error="errors.title"
                />
                <LessonsField
                    v-if="!isTest"
                    :model-value="attempts"
                    :label="$t('Attempts allowed')"
                    type="number"
                    :min="0"
                    :max="99"
                    :hint="$t('0 = unlimited')"
                    :error="errors.attempts_allowed"
                    @update:model-value="
                        attempts = typeof $event === 'number' ? $event : null
                    "
                />
            </div>

            <LessonsField
                v-model="prompt"
                :label="$t('Instruction')"
                :hint="$t('The line the learner reads above the question.')"
                :error="errors.prompt"
                required
                data-test="activity-prompt"
            />

            <section
                v-for="(item, index) in items"
                :key="item.id"
                class="border-line bg-brand-50/30 grid gap-3 rounded-lg border p-3 md:p-4"
                :data-test="`activity-item-${index + 1}`"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3
                        class="font-heading text-brand-900 text-[14px] font-semibold"
                    >
                        {{
                            items.length > 1
                                ? $t('Item :number', { number: index + 1 })
                                : isPractice
                                  ? $t('Exercise')
                                  : $t('Question and answers')
                        }}
                    </h3>
                    <button
                        v-if="items.length > 1 && !readOnly"
                        type="button"
                        class="text-danger-text hover:bg-danger-tint focus-visible:ring-danger/15 inline-flex h-8 items-center gap-1 rounded-md px-2 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        @click="removeItem(index)"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />
                        {{ $t('Remove item') }}
                    </button>
                </div>

                <ActivityItemEditor
                    :draft="item"
                    :spec="spec"
                    :number="index + 1"
                    :library="library"
                    :audio="audio"
                    :lookup="media.lookup"
                    :chips="chips"
                    :error="itemError(index)"
                    :read-only="readOnly"
                    @update:draft="setItem(index, $event)"
                    @remember="media.remember"
                />
            </section>

            <button
                v-if="isPractice && !readOnly"
                type="button"
                class="border-line text-brand-700 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-9 items-center gap-1.5 self-start rounded-md border border-dashed px-3 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40"
                :disabled="items.length >= MAX_ITEMS"
                data-test="activity-add-item"
                @click="addItem"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add another item') }}
            </button>

            <p
                v-if="generalError"
                class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                {{ generalError }}
            </p>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="activity-editor-cancel"
                    @click="open = false"
                >
                    {{ readOnly ? $t('Close') : $t('Cancel') }}
                </Button>
                <Button
                    v-if="!readOnly"
                    type="button"
                    :disabled="saving"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-activity-button"
                    @click="save"
                >
                    {{
                        saving
                            ? $t('Saving…')
                            : isPractice
                              ? $t('Save activity')
                              : $t('Save question')
                    }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
