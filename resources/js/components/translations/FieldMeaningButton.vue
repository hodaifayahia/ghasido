<script setup lang="ts">
import { Check, Languages, LoaderCircle, Plus } from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { toast } from 'vue-sonner';
import { JsonRequestError } from '@/components/lessons/lessonsHttp';
import { useFieldMeaning } from '@/composables/useFieldMeaning';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/*
 * The Translation button beside a builder field's label (user request
 * 2026-09-26): the admin writes the Arabic of that field's English text, and
 * the learner's Show Meaning button on top of the same text reveals it
 * (CTRL-01..03). Saved as a hand-written meaning, which the AI never
 * replaces. A tick says the text already has a meaning.
 */
type Props = {
    /** The field's current English text. */
    text: string | null | undefined;
    readOnly?: boolean;
    /** Icon only, for tight rows (answer options, objective rows). */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    readOnly: false,
    compact: false,
});

const { meaning, translatable, save } = useFieldMeaning(() => props.text);

const open = ref(false);
const draft = ref('');
const saving = ref(false);
const error = ref<string | null>(null);

const hasMeaning = computed(
    () =>
        meaning.value !== null &&
        (meaning.value.state === 'manual' || meaning.value.state === 'ai') &&
        (meaning.value.arabic ?? '') !== '',
);

const note = computed((): string => {
    switch (meaning.value?.state) {
        case 'manual':
            return t('Written by hand. Learners see it under Show Meaning.');
        case 'ai':
            return t('Drafted by AI. Edit it and save to make it yours.');
        case 'drafting':
            return t('The AI is drafting it. You can also write it now.');
        default:
            return t('No meaning yet. Learners are told it is not added.');
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        draft.value = meaning.value?.arabic ?? '';
        error.value = null;
    }
});

async function submit(): Promise<void> {
    const arabic = draft.value.trim();

    if (arabic === '' || saving.value || props.readOnly) {
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        await save(arabic);
        toast.success(t('Meaning saved.'));
        open.value = false;
    } catch (caught) {
        error.value =
            caught instanceof JsonRequestError
                ? (Object.values(caught.errors)[0] ??
                  t('The meaning could not be saved. Try again.'))
                : t('The meaning could not be saved. Try again.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                :disabled="!translatable"
                :title="
                    translatable
                        ? $t('Arabic meaning')
                        : $t('Write the English text first.')
                "
                data-test="field-meaning-button"
                :class="
                    cn(
                        'focus-visible:ring-brand-600/15 focus-visible:border-brand-600 rounded-pill inline-flex h-7 shrink-0 items-center gap-1 border px-2.5 text-[11.5px] font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                        compact && 'gap-0.5 px-1.5',
                        hasMeaning
                            ? 'border-success/30 bg-success-tint text-success-text hover:bg-success-tint/70'
                            : 'border-line bg-brand-50 text-brand-700 hover:bg-brand-100',
                        props.class,
                    )
                "
            >
                <Languages class="size-3.5" aria-hidden="true" />
                <span :class="compact && 'sr-only'">{{
                    $t('Translation')
                }}</span>
                <LoaderCircle
                    v-if="translatable && meaning === null"
                    class="size-3 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <Check
                    v-else-if="hasMeaning"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <Plus v-else class="size-3.5" aria-hidden="true" />
                <span class="sr-only">{{
                    hasMeaning ? $t('Meaning added') : $t('No meaning yet')
                }}</span>
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                align="start"
                :side-offset="6"
                :collision-padding="12"
                class="border-line bg-surface shadow-pop animate-fade-up z-[60] grid w-[min(340px,calc(100vw-24px))] gap-2.5 rounded-lg border p-4 motion-reduce:animate-none"
            >
                <p class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Arabic meaning') }}
                </p>
                <p
                    dir="ltr"
                    lang="en"
                    class="bg-app-alt text-ink-slate line-clamp-3 rounded-sm px-2.5 py-1.5 text-start text-[12px] leading-[1.5]"
                >
                    {{ text }}
                </p>
                <textarea
                    v-model="draft"
                    dir="rtl"
                    lang="ar"
                    rows="3"
                    :readonly="readOnly"
                    :aria-label="$t('Arabic meaning')"
                    :placeholder="$t('Write the Arabic here')"
                    data-test="field-meaning-arabic"
                    class="border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 font-arabic w-full rounded-sm border px-3 py-2 text-end text-[14px] leading-[1.9] focus-visible:ring-3 focus-visible:outline-none"
                    @keydown.ctrl.enter.prevent="submit"
                    @keydown.meta.enter.prevent="submit"
                />
                <p class="text-ink-muted text-[11.5px]">{{ note }}</p>
                <p
                    v-if="error"
                    role="alert"
                    class="text-danger-text bg-danger-tint rounded-sm px-2.5 py-1.5 text-[12px]"
                >
                    {{ error }}
                </p>
                <div class="flex items-center justify-end gap-2">
                    <PopoverClose
                        class="text-ink-slate hover:bg-app-alt focus-visible:ring-brand-600/15 h-9 rounded-md px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    >
                        {{ $t('Cancel') }}
                    </PopoverClose>
                    <button
                        v-if="!readOnly"
                        type="button"
                        :disabled="draft.trim() === '' || saving"
                        data-test="field-meaning-save"
                        class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 inline-flex h-9 items-center gap-1.5 rounded-md px-4 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50"
                        @click="submit"
                    >
                        <LoaderCircle
                            v-if="saving"
                            class="size-3.5 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        {{ $t('Save') }}
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
