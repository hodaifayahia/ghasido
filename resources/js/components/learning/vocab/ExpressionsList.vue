<script setup lang="ts">
import { Eye, MessageSquare } from '@lucide/vue';
import { reactive } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import TipCard from '@/components/learning/TipCard.vue';
import { cn } from '@/lib/utils';
import type { LexiconEntry } from '@/types';

/*
 * The "More Useful Expressions" side card (photo_3): a chat-icon header, a
 * row per other expression (thumb, sentence + IPA, a Normal and a Slow
 * speaker and an eye), and the tip strip. Tapping a row swaps the feature
 * in; the eye reveals that expression's Arabic inline (CTRL-02).
 */
type Props = {
    items: LexiconEntry[];
    title: string;
    tip: string | null;
};

defineProps<Props>();

const emit = defineEmits<{ select: [id: number] }>();

const revealed = reactive(new Set<number>());

function toggle(id: number): void {
    if (revealed.has(id)) {
        revealed.delete(id);
    } else {
        revealed.add(id);
    }
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4">
        <div class="border-line bg-surface shadow-card rounded-lg border p-5">
            <div class="mb-3 flex items-center gap-2">
                <MessageSquare
                    class="text-brand-600 size-5"
                    aria-hidden="true"
                />
                <h3 class="text-brand-700 font-heading text-base font-semibold">
                    {{ title }}
                </h3>
            </div>

            <ul class="flex list-none flex-col gap-2">
                <li v-for="item in items" :key="item.id">
                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="focus-visible:ring-brand-600/40 flex min-w-0 flex-1 items-center gap-3 rounded-md py-1 text-start focus-visible:ring-3 focus-visible:outline-none"
                            @click="emit('select', item.id)"
                        >
                            <img
                                v-if="item.image"
                                :src="item.image.url"
                                :alt="item.image.alt ?? ''"
                                loading="lazy"
                                decoding="async"
                                class="h-[54px] w-16 shrink-0 rounded-md object-cover"
                            />
                            <span class="min-w-0">
                                <span
                                    class="text-ink block text-[15px] leading-snug font-semibold"
                                >
                                    {{ item.text }}
                                </span>
                                <span
                                    v-if="item.ipa"
                                    class="text-ink-slate block text-xs"
                                >
                                    {{ item.ipa }}
                                </span>
                            </span>
                        </button>

                        <AudioButton
                            size="sm"
                            :src="item.audio.normal"
                            :text="item.text"
                            class="shrink-0"
                        />
                        <AudioButton
                            size="sm"
                            variant="slow"
                            :src="item.audio.slow"
                            :text="item.text"
                            class="shrink-0"
                        />

                        <button
                            v-if="item.showMeaning && item.meaning"
                            type="button"
                            :aria-label="
                                $t('Show the meaning of :text', {
                                    text: item.text,
                                })
                            "
                            :aria-pressed="revealed.has(item.id)"
                            :class="
                                cn(
                                    'ease-brand focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none',
                                    revealed.has(item.id)
                                        ? 'bg-brand-100 text-brand-700'
                                        : 'bg-tint-grid text-ink-slate hover:bg-brand-50',
                                )
                            "
                            @click="toggle(item.id)"
                        >
                            <Eye class="size-5" aria-hidden="true" />
                        </button>
                    </div>

                    <ShowMeaningPanel
                        v-if="item.meaning"
                        :shown="revealed.has(item.id)"
                        :arabic="item.meaning.arabic"
                        :explanation="item.meaning.explanation"
                        class="mt-2"
                    />
                </li>
            </ul>
        </div>

        <TipCard v-if="tip" :text="tip" />
    </div>
</template>
