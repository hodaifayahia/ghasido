<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    Copy,
    Eye,
    EyeOff,
    GripVertical,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import BlockEditorDialog from '@/components/lessons/blocks/BlockEditorDialog.vue';
import {
    BLOCK_DRAG_TYPE,
    BLOCK_ROW_DRAG_TYPE,
    blockIcon,
    toneClass,
} from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    destroy,
    duplicate,
    reorder,
    store as storeBlock,
    toggle,
} from '@/routes/blocks';
import type {
    BlockTypeKey,
    LessonBlockRow,
    LessonBlockTypeOption,
    LessonScenarioOption,
    LessonsImageLibrary,
} from '@/types';

/**
 * Lesson Blocks — the one addition to the mockup (BLD-03, BLD-07,
 * LESSON-01/02): the lesson's steps in order, with drag handles (and
 * keyboard move buttons), hide / show, duplicate, delete and Edit, which
 * opens the block's editor. A palette tile dropped here is inserted at the
 * drop position. Every change is persisted at once.
 */
type Props = {
    lessonId: number;
    blocks: LessonBlockRow[];
    blockTypes: LessonBlockTypeOption[];
    scenarios: LessonScenarioOption[];
    library: LessonsImageLibrary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const order = ref<number[]>(props.blocks.map((block) => block.id));
const dragging = ref<number | null>(null);
const dropIndex = ref<number | null>(null);
const paletteOver = ref(false);
const busy = ref(false);
const editing = ref<LessonBlockRow | null>(null);
const editorOpen = ref(false);

watch(
    () => props.blocks,
    (blocks) => {
        order.value = blocks.map((block) => block.id);

        if (editing.value !== null) {
            editing.value =
                blocks.find((block) => block.id === editing.value?.id) ?? null;
        }
    },
);

const ordered = computed(() =>
    order.value
        .map((id) => props.blocks.find((block) => block.id === id))
        .filter((block): block is LessonBlockRow => block !== undefined),
);

const visitOptions = {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
        busy.value = false;
    },
};

function persistOrder(): void {
    busy.value = true;
    router.put(
        reorder.url(props.lessonId),
        { order: order.value },
        visitOptions,
    );
}

function move(index: number, delta: number): void {
    const target = index + delta;

    if (target < 0 || target >= order.value.length) {
        return;
    }

    const next = [...order.value];
    const [id] = next.splice(index, 1);

    if (id !== undefined) {
        next.splice(target, 0, id);
        order.value = next;
        persistOrder();
    }
}

function onRowDragStart(event: DragEvent, id: number): void {
    if (!manage.value || event.dataTransfer === null) {
        return;
    }

    dragging.value = id;
    event.dataTransfer.setData(BLOCK_ROW_DRAG_TYPE, String(id));
    event.dataTransfer.effectAllowed = 'move';
}

function onDragOver(event: DragEvent, index: number): void {
    if (!manage.value || event.dataTransfer === null) {
        return;
    }

    const types = Array.from(event.dataTransfer.types);

    if (
        !types.includes(BLOCK_ROW_DRAG_TYPE) &&
        !types.includes(BLOCK_DRAG_TYPE)
    ) {
        return;
    }

    event.preventDefault();
    dropIndex.value = index;
    paletteOver.value = types.includes(BLOCK_DRAG_TYPE);
}

function onDrop(event: DragEvent, index: number): void {
    if (event.dataTransfer === null) {
        return;
    }

    event.preventDefault();

    const paletteType = event.dataTransfer.getData(BLOCK_DRAG_TYPE);

    if (paletteType !== '') {
        busy.value = true;
        router.post(
            storeBlock.url(props.lessonId),
            { type: paletteType as BlockTypeKey, position: index + 1 },
            visitOptions,
        );
        reset();

        return;
    }

    const id = Number(event.dataTransfer.getData(BLOCK_ROW_DRAG_TYPE));
    const from = order.value.indexOf(id);

    if (from === -1) {
        reset();

        return;
    }

    const next = [...order.value];
    next.splice(from, 1);
    next.splice(from < index ? index - 1 : index, 0, id);

    if (next.join(',') !== order.value.join(',')) {
        order.value = next;
        persistOrder();
    }

    reset();
}

function reset(): void {
    dragging.value = null;
    dropIndex.value = null;
    paletteOver.value = false;
}

function toggleBlock(block: LessonBlockRow): void {
    busy.value = true;
    router.post(toggle.url(block.id), {}, visitOptions);
}

function duplicateBlock(block: LessonBlockRow): void {
    busy.value = true;
    router.post(duplicate.url(block.id), {}, visitOptions);
}

function deleteBlock(block: LessonBlockRow): void {
    if (
        !window.confirm(
            t('Remove ":label" from this lesson? Learners’ answers are kept.', {
                label: block.label,
            }),
        )
    ) {
        return;
    }

    busy.value = true;
    router.delete(destroy.url(block.id), visitOptions);
}

function edit(block: LessonBlockRow): void {
    editing.value = block;
    editorOpen.value = true;
}

const iconButton =
    'text-brand-800 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex size-7 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';
</script>

<template>
    <section
        :class="cn('grid grid-cols-[minmax(0,1fr)] gap-2', props.class)"
        aria-labelledby="lesson-blocks-title"
        data-tour="lesson-blocks"
    >
        <div class="flex items-center justify-between gap-3">
            <h2
                id="lesson-blocks-title"
                class="text-brand-900 text-[12px] font-semibold"
            >
                {{ $t('Lesson Blocks') }}
                <span class="text-ink-faint font-medium">
                    · {{ $tc(':count step|:count steps', blocks.length) }}
                </span>
            </h2>
            <span class="text-ink-faint text-[11px]">
                {{ $t('Drag to reorder, or drop a block from the palette') }}
            </span>
        </div>

        <ol
            class="space-y-2"
            :aria-busy="busy"
            @dragleave.self="dropIndex = null"
        >
            <li
                v-for="(block, index) in ordered"
                :key="block.id"
                :draggable="manage"
                :data-test="`lesson-block-${block.id}`"
                :class="
                    cn(
                        'border-line bg-surface flex min-h-11 flex-wrap items-center gap-2 rounded-md border px-2 py-1.5 transition-colors duration-150',
                        dragging === block.id && 'opacity-50',
                        dropIndex === index &&
                            'border-brand-600 ring-brand-600/15 ring-3',
                        !block.isVisible && 'bg-app-alt',
                    )
                "
                @dragstart="onRowDragStart($event, block.id)"
                @dragend="reset"
                @dragover="onDragOver($event, index)"
                @drop="onDrop($event, index)"
            >
                <span
                    :class="
                        cn(
                            'text-ink-faint shrink-0',
                            manage ? 'cursor-grab' : 'cursor-default',
                        )
                    "
                    aria-hidden="true"
                >
                    <GripVertical class="size-4" />
                </span>
                <span
                    class="text-ink-faint w-4 shrink-0 text-center text-[11px] font-semibold"
                >
                    {{ index + 1 }}
                </span>
                <span
                    :class="
                        cn(
                            'grid size-7 shrink-0 place-items-center rounded-md',
                            toneClass(block.tone),
                        )
                    "
                >
                    <component
                        :is="blockIcon(block.icon)"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                </span>
                <span class="min-w-0 flex-1 basis-28">
                    <span
                        :class="
                            cn(
                                'block truncate text-[12.5px] font-medium',
                                block.isVisible
                                    ? 'text-brand-900'
                                    : 'text-ink-faint line-through',
                            )
                        "
                    >
                        {{ block.label }}
                    </span>
                    <span class="text-ink-faint block truncate text-[10.5px]">
                        {{ block.stepLabel }}
                        <template v-if="block.lexiconItems.length > 0">
                            ·
                            {{
                                $tc(
                                    ':count item|:count items',
                                    block.lexiconItems.length,
                                )
                            }}
                        </template>
                        <template v-if="block.activities.length > 0">
                            ·
                            {{
                                $tc(
                                    ':count activity|:count activities',
                                    block.activities.length,
                                )
                            }}
                        </template>
                        <template v-if="!block.isVisible">{{
                            $t('· hidden')
                        }}</template>
                    </span>
                </span>

                <template v-if="manage">
                    <button
                        type="button"
                        :class="iconButton"
                        :disabled="index === 0 || busy"
                        :aria-label="
                            $t('Move :label up', { label: block.label })
                        "
                        @click="move(index, -1)"
                    >
                        <ChevronUp class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        :class="iconButton"
                        :disabled="index === ordered.length - 1 || busy"
                        :aria-label="
                            $t('Move :label down', { label: block.label })
                        "
                        @click="move(index, 1)"
                    >
                        <ChevronDown class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        :class="iconButton"
                        :disabled="busy"
                        :aria-label="
                            block.isVisible
                                ? $t('Hide :label', { label: block.label })
                                : $t('Show :label', { label: block.label })
                        "
                        :data-test="`block-${block.id}-toggle`"
                        @click="toggleBlock(block)"
                    >
                        <component
                            :is="block.isVisible ? Eye : EyeOff"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                    </button>
                    <button
                        type="button"
                        :class="iconButton"
                        :disabled="busy"
                        :aria-label="
                            $t('Duplicate :label', { label: block.label })
                        "
                        :data-test="`block-${block.id}-duplicate`"
                        @click="duplicateBlock(block)"
                    >
                        <Copy class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        :class="
                            cn(
                                iconButton,
                                'text-danger-text hover:bg-danger-tint',
                            )
                        "
                        :disabled="busy"
                        :aria-label="
                            $t('Delete :label', { label: block.label })
                        "
                        :data-test="`block-${block.id}-delete`"
                        @click="deleteBlock(block)"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />
                    </button>
                </template>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-7 gap-1 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none"
                    :data-test="`block-${block.id}-edit`"
                    :data-tour="`${block.type}-edit`"
                    @click="edit(block)"
                >
                    <Pencil class="size-3" aria-hidden="true" />
                    {{ manage ? $t('Edit') : $t('View') }}
                </Button>
            </li>

            <li
                v-if="manage"
                :class="
                    cn(
                        'border-line text-ink-faint flex min-h-10 items-center justify-center rounded-md border border-dashed text-[11.5px]',
                        dropIndex === ordered.length &&
                            'border-brand-600 text-brand-700 bg-brand-50/60',
                    )
                "
                @dragover="onDragOver($event, ordered.length)"
                @drop="onDrop($event, ordered.length)"
            >
                {{
                    ordered.length === 0
                        ? $t(
                              'No steps yet — click or drop a block from the palette.',
                          )
                        : $t('Drop here to add at the end')
                }}
            </li>
        </ol>

        <BlockEditorDialog
            v-if="editing !== null"
            v-model:open="editorOpen"
            :block="editing"
            :block-types="blockTypes"
            :scenarios="scenarios"
            :library="library"
            :read-only="!manage"
        />
    </section>
</template>
