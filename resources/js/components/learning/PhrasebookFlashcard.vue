<script setup lang="ts">
import { useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { PhrasebookEntry } from '@/types';

/*
 * One review card (spec 0005 §3.4): image, the English phrase and both
 * audio speeds lead (LESSON-06); the meaning stays hidden until the learner
 * taps Show Meaning (CTRL-01, CTRL-02). Learning content runs one step
 * larger than admin chrome (AGENTS.md §3 Typography).
 */
type Props = {
    entry: PhrasebookEntry;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const meaning = useShowMeaning(() => props.entry.showMeaning);
const panelId = `review-${useId()}`;
</script>

<template>
    <article
        :class="
            cn(
                'border-line bg-surface shadow-card animate-fade-up flex min-w-0 flex-col items-center gap-4 rounded-lg border p-6 text-center motion-reduce:animate-none md:p-8',
                props.class,
            )
        "
        data-test="phrasebook-flashcard"
    >
        <img
            v-if="entry.image"
            :src="entry.image.url"
            :alt="entry.image.alt ?? ''"
            decoding="async"
            class="h-36 w-full max-w-[280px] rounded-md object-cover"
        />

        <div class="grid gap-1">
            <p
                class="font-heading text-ink-cobalt text-[26px] leading-9 font-semibold"
            >
                {{ entry.text }}
            </p>
            <p v-if="entry.ipa" class="text-ink-slate text-base">
                {{ entry.ipa }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <AudioButton :src="entry.audio.normal" :text="entry.text" />
            <AudioButton
                variant="slow"
                :src="entry.audio.slow"
                :text="entry.text"
            />
        </div>

        <p
            v-if="entry.example"
            class="text-ink bg-app rounded-md px-4 py-2 text-base leading-7"
        >
            “{{ entry.example }}”
        </p>

        <ShowMeaningButton
            v-if="entry.showMeaning"
            :shown="meaning.shown.value"
            :controls="panelId"
            @toggle="meaning.toggle()"
        />

        <ShowMeaningPanel
            v-if="entry.meaning"
            :id="panelId"
            :shown="meaning.shown.value"
            :arabic="entry.meaning.arabic"
            :explanation="entry.meaning.explanation"
            :example="entry.example"
            :example-arabic="entry.meaning.exampleArabic"
            class="w-full text-start"
        />
    </article>
</template>
