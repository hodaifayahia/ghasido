<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { ArrowLeft, CirclePlus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type {
    ActivityTypeSpec,
    BlockActivityMode,
    ItemDraft,
} from '@/components/lessons/activities/activityCatalog';
import {
    activityTypes,
    activityTypeSpec,
    blankDraft,
    draftFromItem,
    itemFromDraft,
    nextItemId,
} from '@/components/lessons/activities/activityCatalog';
import LessonsActivityItemEditor from '@/components/lessons/activities/LessonsActivityItemEditor.vue';
import { toneClass, useMediaLookup } from '@/components/lessons/lessonsBlocks';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/activities';
import type {
    LessonActivityRow,
    LessonBlockRow,
    LessonsImageLibrary,
} from '@/types';

/**
 * Add or edit one activity of a Practice block, or one question of a Quiz
 * block (PRAC-01..07, TEST-05; client report 2026-09-29). A new one starts
 * by choosing its exercise type; then the instruction, the attempts rule
 * and the item(s) with their correct answers. It saves through
 * activities.store (placed in the block in the same request) or
 * activities.update, which writes a new version when the content changes
 * (DATA-11). The type is fixed once saved: a different kind of question is
 * a new activity.
 */
type Props = {
    block: LessonBlockRow;
    /** Null to add a new one. */
    activity: LessonActivityRow | null;
    mode: BlockActivityMode;
    /** The position a new one takes (for its default title). */
    number: number;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const MAX_ITEMS = 6;

const spec = ref<ActivityTypeSpec | null>(null);
const title = ref('');
const prompt = ref('');
const attempts = ref<number | null>(0);
const items = ref<ItemDraft[]>([]);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

const media = useMediaLookup(() => props.block.media);

const isQuiz = computed(() => props.mode === 'quiz');

function start(type: ActivityTypeSpec): void {
    spec.value = type;
    prompt.value = type.defaultPrompt;
    items.value = [blankDraft(type, 'i1')];
    errors.value = {};
}

function reset(): void {
    errors.value = {};

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
    title.value = isQuiz.value ? `Question ${props.number}` : '';
    prompt.value = '';
    attempts.value = isQuiz.value ? 1 : 0;
    items.value = [];
}

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

const heading = computed(() => {
    if (props.activity !== null) {
        return isQuiz.value ? t('Edit question') : t('Edit activity');
    }

    return isQuiz.value ? t('Add a question') : t('Add an activity');
});

const description = computed(() =>
    spec.value === null
        ? t('Choose the type of exercise.')
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
        errors.value[`payload.items.${index}`] ??
        errors.value[`payload.items.${index}.id`]
    );
}

const generalError = computed(
    () =>
        errors.value['payload.items'] ??
        errors.value.payload ??
        errors.value.type ??
        errors.value.block_id,
);

function save(): void {
    const type = spec.value;

    if (props.readOnly || type === null) {
        open.value = false;

        return;
    }

    saving.value = true;
    errors.value = {};

    const payload = {
        items: items.value.map((draft) => itemFromDraft(type, draft)),
    };
    const data = {
        title: title.value.trim() === '' ? null : title.value.trim(),
        prompt: prompt.value.trim(),
        attempts_allowed: attempts.value ?? 0,
        payload: payload as unknown as FormDataConvertible,
    };
    const options = {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
        onError: (bag: Record<string, string>) => {
            errors.value = bag;
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    if (props.activity === null) {
        router.post(
            store.url(),
            { ...data, type: type.value, block_id: props.block.id },
            options,
        );

        return;
    }

    router.patch(update.url(props.activity.id), data, options);
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="heading"
        :description="description"
        size="lg"
    >
        <!-- Step 1: choose the exercise type -->
        <div
            v-if="spec === null"
            class="mt-2 grid gap-2 sm:grid-cols-2"
            role="list"
            :aria-label="$t('Exercise types')"
        >
            <button
                v-for="type in activityTypes"
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

            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_160px]">
                <LessonsField
                    v-model="title"
                    :label="
                        isQuiz ? $t('Question title') : $t('Title (optional)')
                    "
                    :placeholder="$t(spec.label)"
                    :hint="$t('Shown on the learner’s card.')"
                    :error="errors.title"
                />
                <LessonsField
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
                :hint="$t('The line the learner reads above the exercise.')"
                :error="errors.prompt"
                required
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
                                : isQuiz
                                  ? $t('Question and answers')
                                  : $t('Exercise')
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

                <LessonsActivityItemEditor
                    :draft="item"
                    :spec="spec"
                    :number="index + 1"
                    :library="library"
                    :audio="block.audio"
                    :lookup="media.lookup"
                    :error="itemError(index)"
                    :read-only="readOnly"
                    @update:draft="setItem(index, $event)"
                    @remember="media.remember"
                />
            </section>

            <button
                v-if="!isQuiz && !readOnly"
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
                            : isQuiz
                              ? $t('Save question')
                              : $t('Save activity')
                    }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
