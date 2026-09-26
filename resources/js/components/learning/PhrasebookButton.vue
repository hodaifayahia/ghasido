<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import { store } from '@/routes/learn/phrasebook';

/*
 * ⭐ Save to Phrasebook (PHRASE-01..03), drawn as desginphotos/employ/
 * photo_2 draws it: a 345×52 gold-tint box with a solid gold star and
 * "Save to Phrasebook" 15px semibold. Saving posts to learn.phrasebook.store
 * (a lexicon item id, or a custom text + Arabic); the server answers with a
 * toast and the refreshed props. Removing needs the phrasebook row's URL
 * (`removeUrl`), which the Phrasebook page carries; without it a saved
 * item shows as saved and cannot be un-saved from here.
 */
type Props = {
    saved: boolean;
    lexiconItemId?: number | null;
    text?: string | null;
    arabic?: string | null;
    sourceLessonId?: number | null;
    removeUrl?: string | null;
    size?: 'md' | 'sm';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    lexiconItemId: null,
    text: null,
    arabic: null,
    sourceLessonId: null,
    removeUrl: null,
    size: 'md',
});

const { t } = useI18n();

const busy = ref(false);

const label = computed(() =>
    props.saved ? t('Saved to Phrasebook') : t('Save to Phrasebook'),
);
const canToggle = computed(() => !props.saved || props.removeUrl !== null);

function toggle(): void {
    if (busy.value || !canToggle.value) {
        return;
    }

    busy.value = true;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            busy.value = false;
        },
    };

    if (props.saved && props.removeUrl) {
        router.delete(props.removeUrl, options);

        return;
    }

    router.post(
        store().url,
        {
            lexicon_item_id: props.lexiconItemId,
            text: props.lexiconItemId ? null : props.text,
            arabic: props.lexiconItemId ? null : props.arabic,
            source_lesson_id: props.sourceLessonId,
        },
        options,
    );
}
</script>

<template>
    <button
        type="button"
        :aria-pressed="saved"
        :disabled="busy || !canToggle"
        :class="
            cn(
                'bg-gold-tint text-ink ease-brand hover:bg-gold/30 focus-visible:ring-brand-600/40 flex items-center justify-center gap-3 rounded-md font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] disabled:cursor-default motion-reduce:transition-none',
                size === 'md'
                    ? 'h-[52px] w-full px-6 text-[15px]'
                    : 'min-h-11 px-4 text-sm',
                props.class,
            )
        "
        data-test="save-phrasebook-button"
        @click="toggle"
    >
        <span class="relative flex shrink-0">
            <Star
                :class="
                    cn(
                        'text-gold fill-current',
                        size === 'md' ? 'size-[22px]' : 'size-5',
                    )
                "
                aria-hidden="true"
            />
            <Check
                v-if="saved"
                class="text-ink absolute -end-1 -bottom-1 size-3 stroke-[4]"
                aria-hidden="true"
            />
        </span>
        {{ label }}
    </button>
</template>
