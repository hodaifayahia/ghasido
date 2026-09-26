<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import RecorderButton from '@/components/learning/RecorderButton.vue';
import { Spinner } from '@/components/ui/spinner';
import { usePronunciationCheck } from '@/composables/usePronunciationCheck';
import { cn } from '@/lib/utils';
import type { PronunciationWord } from '@/types';
import { WORD_STATUS, weakWordHint } from './pronunciationStatus';

/*
 * The drill for one weak word (spec 0006 §2 finding 2): out of its sentence
 * the recogniser cannot guess the word from context, so this is the most
 * accurate check. Listen to the word alone at both speeds (its own stored
 * clips, generated after the sentence check; browser speech meanwhile),
 * then say it on its own. The recording is kept and checked like a
 * sentence (DATA-02).
 */
type Props = {
    word: PronunciationWord;
    lessonId: number;
    blockId: number;
    item: number;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{ close: [] }>();

const drill = usePronunciationCheck();

const verdict = computed(() => drill.result.value?.words[0] ?? null);

function onRecorded(payload: {
    blob: Blob;
    durationMs: number;
    mimeType: string;
}): void {
    void drill.check(
        {
            lessonId: props.lessonId,
            blockId: props.blockId,
            item: props.item,
            word: props.word.index,
        },
        payload,
    );
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex flex-col gap-4 rounded-lg border p-4',
                props.class,
            )
        "
        data-test="pronunciation-drill"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-ink-slate text-sm">
                    {{ $t('Practise this word') }}
                </p>
                <p class="font-heading text-ink text-2xl font-bold break-words">
                    {{ word.text }}
                </p>
                <p
                    v-if="word.status !== 'correct'"
                    class="text-ink-slate mt-1 text-sm"
                >
                    {{ weakWordHint(word.status, word.heard, word.sound) }}
                </p>
                <p v-if="word.tip" class="text-ink mt-1 text-sm">
                    {{ word.tip }}
                </p>
            </div>
            <button
                type="button"
                :aria-label="$t('Close the word practice')"
                class="text-ink-slate hover:bg-tint-grid focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full focus-visible:ring-3 focus-visible:outline-none"
                @click="emit('close')"
            >
                <X class="size-5" aria-hidden="true" />
            </button>
        </div>

        <div class="flex flex-wrap gap-3">
            <AudioButton
                :src="word.audio?.normal ?? null"
                variant="normal"
                :text="word.text"
                class="h-14 min-w-0 flex-1"
            />
            <AudioButton
                :src="word.audio?.slow ?? null"
                variant="slow"
                :text="word.text"
                class="h-14 min-w-0 flex-1"
            />
        </div>

        <RecorderButton
            :key="word.index"
            size="md"
            :max-seconds="6"
            @recorded="onRecorded"
            @reset="drill.reset()"
        />

        <p
            v-if="drill.busy.value && !drill.result.value?.settled"
            class="text-ink-slate flex items-center gap-2 text-sm"
            aria-live="polite"
        >
            <Spinner class="size-4" />
            {{ $t('Checking “:word”…', { word: word.text }) }}
        </p>
        <p
            v-else-if="drill.error.value"
            class="bg-danger-tint text-danger-text rounded-md px-4 py-3 text-sm font-semibold"
            role="alert"
        >
            {{ drill.error.value }}
        </p>
        <div
            v-else-if="verdict"
            :class="
                cn(
                    'flex items-start gap-2 rounded-md px-4 py-3 text-sm font-semibold',
                    WORD_STATUS[verdict.status].chip,
                )
            "
            aria-live="polite"
            data-test="pronunciation-drill-verdict"
        >
            <component
                :is="WORD_STATUS[verdict.status].icon"
                class="mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <span>
                {{
                    verdict.status === 'correct'
                        ? $t('Great! “:word” was clear.', { word: word.text })
                        : $t(':status. :hint Try again.', {
                              status: $t(WORD_STATUS[verdict.status].label),
                              hint: weakWordHint(
                                  verdict.status,
                                  verdict.heard,
                                  verdict.sound,
                              ),
                          })
                }}
            </span>
        </div>
    </section>
</template>
