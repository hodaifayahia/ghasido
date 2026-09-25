<script setup lang="ts">
import { MessageSquare } from '@lucide/vue';
import { computed, useId } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import type { AudioPair } from '@/types';

/*
 * The "Example" card under the featured word (photo_2): a brand-50 header
 * with the chat icon, then a white body with a speaker, the sentence (the
 * featured word in bold) and a small centred Show Meaning (CTRL-01..03).
 */
type Props = {
    text: string;
    word: string;
    audio: AudioPair | null;
    arabic: string | null;
};

const props = defineProps<Props>();

const meaning = useShowMeaning();
const panelId = `vocab-example-${useId()}`;

const segments = computed(() => {
    const word = props.word.trim();

    if (word === '') {
        return [{ text: props.text, bold: false }];
    }

    const parts: { text: string; bold: boolean }[] = [];
    const haystack = props.text.toLowerCase();
    const needle = word.toLowerCase();
    let cursor = 0;

    for (;;) {
        const at = haystack.indexOf(needle, cursor);

        if (at === -1) {
            parts.push({ text: props.text.slice(cursor), bold: false });
            break;
        }

        if (at > cursor) {
            parts.push({ text: props.text.slice(cursor, at), bold: false });
        }

        parts.push({
            text: props.text.slice(at, at + needle.length),
            bold: true,
        });
        cursor = at + needle.length;
    }

    return parts;
});
</script>

<template>
    <div
        class="border-line bg-surface shadow-card overflow-hidden rounded-lg border"
    >
        <div class="bg-brand-50 flex items-center gap-2 px-5 py-3">
            <MessageSquare class="text-brand-600 size-5" aria-hidden="true" />
            <span class="text-brand-700 font-heading text-base font-semibold">
                Example
            </span>
        </div>

        <div class="flex flex-col gap-3 p-5">
            <div class="flex items-start gap-3">
                <AudioButton
                    size="sm"
                    :src="audio?.normal ?? null"
                    :text="text"
                    class="shrink-0"
                />
                <p class="text-ink text-lg leading-8">
                    <template v-for="(seg, index) in segments" :key="index"
                        ><strong v-if="seg.bold" class="font-semibold">{{
                            seg.text
                        }}</strong
                        ><template v-else>{{ seg.text }}</template></template
                    >
                </p>
            </div>

            <template v-if="arabic">
                <ShowMeaningButton
                    :shown="meaning.shown.value"
                    :controls="panelId"
                    size="sm"
                    class="mx-auto"
                    @toggle="meaning.toggle()"
                />
                <ShowMeaningPanel
                    :id="panelId"
                    :shown="meaning.shown.value"
                    :arabic="arabic"
                />
            </template>
        </div>
    </div>
</template>
