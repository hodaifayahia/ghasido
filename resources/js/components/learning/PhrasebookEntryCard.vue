<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref, useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { PhrasebookEntry } from '@/types';

/*
 * One saved word or expression (PHRASE-01..05): playable at both speeds,
 * its meaning behind Show Meaning (CTRL-02), where it came from, and
 * Remove. Removal posts to the row's own URL and the server confirms.
 */
type Props = {
    entry: PhrasebookEntry;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const meaning = useShowMeaning(() => props.entry.showMeaning);
const panelId = `phrase-${useId()}`;
const removing = ref(false);

function remove(): void {
    removing.value = true;
    router.delete(props.entry.removeUrl, {
        preserveScroll: true,
        onFinish: () => {
            removing.value = false;
        },
    });
}
</script>

<template>
    <article
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-4',
                props.class,
            )
        "
    >
        <div class="flex items-start gap-3">
            <img
                v-if="entry.image"
                :src="entry.image.url"
                :alt="entry.image.alt ?? ''"
                loading="lazy"
                decoding="async"
                class="size-16 shrink-0 rounded-sm object-cover"
            />
            <div class="min-w-0 flex-1">
                <p class="text-ink text-lg leading-7 font-semibold">
                    {{ entry.text }}
                </p>
                <p v-if="entry.ipa" class="text-ink-slate text-sm">
                    {{ entry.ipa }}
                </p>
                <p v-if="entry.lesson" class="text-ink-slate mt-1 text-xs">
                    From {{ entry.lesson.title }}
                </p>
            </div>
            <button
                type="button"
                :disabled="removing"
                aria-label="Remove from My Phrasebook"
                class="text-danger hover:bg-danger-tint focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-50"
                data-test="remove-phrase-button"
                @click="remove"
            >
                <Trash2 class="size-5" aria-hidden="true" />
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <AudioButton
                size="sm"
                :src="entry.audio.normal"
                :text="entry.text"
            />
            <AudioButton
                size="sm"
                variant="slow"
                :src="entry.audio.slow"
                :text="entry.text"
            />
            <ShowMeaningButton
                v-if="entry.showMeaning"
                size="sm"
                :shown="meaning.shown.value"
                :controls="panelId"
                class="ms-auto"
                @toggle="meaning.toggle()"
            />
        </div>

        <ShowMeaningPanel
            v-if="entry.meaning"
            :id="panelId"
            :shown="meaning.shown.value"
            :arabic="entry.meaning.arabic"
            :explanation="entry.meaning.explanation"
            :example="entry.example"
            :example-arabic="entry.meaning.exampleArabic"
        />
    </article>
</template>
