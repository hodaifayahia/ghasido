<script setup lang="ts">
import { AudioLines, FileImage, Video } from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { t, tk } from '@/lib/i18n';
import type { LessonBlockRow, LessonEditor, LessonMediaRef } from '@/types';

/**
 * Materials tab: every image, video and audio file the lesson uses, with
 * the block it belongs to (MED-02, MED-03).
 */
type Props = {
    editor: LessonEditor;
    blocks: LessonBlockRow[];
};

type Material = LessonMediaRef & { usedIn: string };

const props = defineProps<Props>();

const materials = computed((): Material[] => {
    const list: Material[] = [];
    const seen = new Set<number>();

    if (props.editor.coverMediaId !== null && props.editor.coverUrl !== null) {
        list.push({
            id: props.editor.coverMediaId,
            url: props.editor.coverUrl,
            thumbUrl: props.editor.coverUrl,
            alt: props.editor.coverAlt,
            label: t('Lesson cover'),
            kind: 'image',
            usedIn: t('Cover'),
        });
        seen.add(props.editor.coverMediaId);
    }

    for (const block of props.blocks) {
        for (const media of Object.values(block.media)) {
            if (seen.has(media.id)) {
                continue;
            }

            seen.add(media.id);
            list.push({ ...media, usedIn: block.label });
        }
    }

    return list;
});

const kindLabel: Record<string, string> = {
    image: tk('Image'),
    video: tk('Video'),
    audio: tk('Audio'),
    document: tk('Document'),
};

const kindIcon: Record<string, Component> = {
    image: FileImage,
    video: Video,
    audio: AudioLines,
    document: FileImage,
};
</script>

<template>
    <div class="grid gap-3">
        <p class="text-ink-slate text-[12px]">
            {{
                $tc(
                    ':count file is used in this lesson. Replace it from its block editor; the library keeps every upload.|:count files are used in this lesson. Replace one from its block editor; the library keeps every upload.',
                    materials.length,
                )
            }}
        </p>

        <p
            v-if="materials.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate rounded-md border border-dashed px-4 py-8 text-center text-[13px]"
        >
            {{
                $t(
                    "No material yet. Add a cover image or fill a block's media slots.",
                )
            }}
        </p>

        <ul v-else class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
            <li
                v-for="item in materials"
                :key="item.id"
                class="border-line bg-surface flex items-center gap-3 rounded-md border p-2"
            >
                <img
                    v-if="item.kind === 'image'"
                    :src="item.thumbUrl || item.url"
                    :alt="item.alt"
                    loading="lazy"
                    decoding="async"
                    class="border-line h-14 w-20 shrink-0 rounded-sm border object-cover"
                />
                <span
                    v-else
                    class="bg-brand-100 text-brand-700 grid h-14 w-20 shrink-0 place-items-center rounded-sm"
                >
                    <component
                        :is="kindIcon[item.kind]"
                        class="size-5"
                        aria-hidden="true"
                    />
                </span>
                <div class="min-w-0">
                    <p class="text-ink truncate text-[12.5px] font-medium">
                        {{ item.label }}
                    </p>
                    <p class="text-ink-faint truncate text-[11px]">
                        {{ $t(kindLabel[item.kind] ?? item.kind) }} ·
                        {{ item.usedIn }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
