<script setup lang="ts">
import { Languages, LoaderCircle, Sparkles } from '@lucide/vue';
import { createReusableTemplate, useMediaQuery } from '@vueuse/core';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { computed, onMounted, ref, useId, watch } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { FieldMeaningState } from '@/composables/useFieldMeaning';
import { useFieldMeaning } from '@/composables/useFieldMeaning';
import { t, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * "Translate meaning to Arabic" (client request 2026-09-29): a small button
 * above any English field of the test and lesson builders. It shows whether
 * the field's current text has a meaning and opens a popover (a bottom
 * sheet below 768px) to draft it with AI, correct it and save it. Saved
 * meanings are hand-written and the AI never replaces them; learners see
 * them only after tapping Show Meaning (CTRL-01..03).
 */
type Props = {
    /** The field's current English text. */
    text: string;
    /** What the field is, already translated ("Question text"). */
    label: string;
    /** Icon and state only, for narrow rows (answer options, objectives). */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { compact: false });

const { meaning, empty, busy, error, draftWithAi, save, refresh } =
    useFieldMeaning(() => props.text);

const id = useId();
const open = ref(false);
const arabic = ref('');
const edited = ref(false);
const savedFlash = ref(false);

const phoneQuery = useMediaQuery('(max-width: 767px)');
const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});
const isPhone = computed(() => mounted.value && phoneQuery.value);

const [DefineBody, ReuseBody] = createReusableTemplate();
const [DefineFace, ReuseFace] = createReusableTemplate();

const state = computed<FieldMeaningState | null>(
    () => meaning.value?.state ?? null,
);

const badgeText: Record<FieldMeaningState, string> = {
    missing: tk('No meaning'),
    drafting: tk('Drafting…'),
    ai: tk('AI draft'),
    manual: tk('By hand'),
    failed: tk('Draft failed'),
};

const badgeTone: Record<FieldMeaningState, string> = {
    missing: 'bg-warning-tint text-warning-text',
    drafting: 'bg-brand-50 text-brand-700',
    ai: 'bg-ai-tint text-brand-800',
    manual: 'bg-success-tint text-success-text',
    failed: 'bg-danger-tint text-danger-text',
};

const dotTone: Record<FieldMeaningState, string> = {
    missing: 'bg-warning',
    drafting: 'bg-brand-400',
    ai: 'bg-ai',
    manual: 'bg-success',
    failed: 'bg-danger',
};

// The textarea follows the stored meaning until the admin starts typing.
watch(
    () => meaning.value?.arabic ?? null,
    (value) => {
        if (!edited.value) {
            arabic.value = value ?? '';
        }
    },
    { immediate: true },
);

watch(open, (value) => {
    if (value) {
        edited.value = false;
        savedFlash.value = false;
        arabic.value = meaning.value?.arabic ?? '';
        refresh();
    }
});

watch(
    () => props.text,
    () => {
        edited.value = false;
    },
);

const canSave = computed(
    () =>
        !busy.value &&
        state.value !== 'drafting' &&
        arabic.value.trim() !== '' &&
        (edited.value || state.value !== 'manual'),
);

const triggerLabel = computed(() =>
    empty.value
        ? t('Type the English text first, then translate its meaning.')
        : t('Translate meaning to Arabic: :field', { field: props.label }),
);

const stateTitle = computed(() =>
    state.value === null
        ? triggerLabel.value
        : `${triggerLabel.value} (${t(badgeText[state.value])})`,
);

async function onDraft(): Promise<void> {
    edited.value = false;
    await draftWithAi();
}

async function onSave(): Promise<void> {
    if (await save(arabic.value)) {
        edited.value = false;
        savedFlash.value = true;
    }
}

function onInput(event: Event): void {
    arabic.value = (event.target as HTMLTextAreaElement).value;
    edited.value = true;
    savedFlash.value = false;
}

const triggerClass = computed(() =>
    cn(
        'focus-visible:ring-brand-600/15 hover:bg-brand-50 text-brand-700 relative inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-md px-1.5 text-[11.5px] font-semibold whitespace-nowrap transition-colors focus-visible:ring-3 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent md:-my-1 md:min-h-6',
        props.compact && 'min-w-11 justify-center md:min-w-6',
        props.class,
    ),
);
</script>

<template>
    <DefineFace>
        <span class="relative inline-flex">
            <Languages class="size-3.5" aria-hidden="true" />
            <span
                v-if="state !== null"
                :class="
                    cn(
                        'ring-surface absolute -end-1 -top-1 size-2 rounded-full ring-2',
                        dotTone[state],
                    )
                "
                aria-hidden="true"
            />
        </span>
        <span v-if="!compact">{{ $t('Translate meaning to Arabic') }}</span>
        <span v-if="state !== null" class="sr-only">
            ({{ $t(badgeText[state]) }})
        </span>
    </DefineFace>

    <DefineBody>
        <div class="grid gap-3">
            <div class="grid gap-1">
                <span
                    class="text-ink-slate text-[11px] font-semibold tracking-[0.02em] uppercase"
                >
                    {{ $t('English') }}
                </span>
                <p
                    dir="ltr"
                    class="border-line bg-app-alt text-ink line-clamp-4 rounded-sm border px-2.5 py-1.5 text-[12.5px] leading-[1.5] break-words"
                >
                    {{ text }}
                </p>
            </div>

            <label class="grid gap-1">
                <span class="flex items-center justify-between gap-2">
                    <span
                        class="text-ink-slate text-[11px] font-semibold tracking-[0.02em] uppercase"
                    >
                        {{ $t('Arabic meaning') }}
                    </span>
                    <span
                        v-if="state !== null"
                        :class="
                            cn(
                                'rounded-pill px-1.5 py-0.5 text-[10.5px] leading-none font-semibold',
                                badgeTone[state],
                            )
                        "
                    >
                        {{ $t(badgeText[state]) }}
                    </span>
                </span>
                <textarea
                    :value="arabic"
                    rows="3"
                    dir="rtl"
                    lang="ar"
                    maxlength="3000"
                    :disabled="state === 'drafting'"
                    placeholder="المعنى بالعربية"
                    data-test="field-meaning-arabic"
                    class="border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 font-arabic w-full resize-y rounded-sm border px-3 py-2 text-end text-[14px] leading-[1.8] focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60"
                    @input="onInput"
                />
            </label>

            <p
                v-if="state === 'drafting'"
                class="text-brand-700 flex items-center gap-1.5 text-[12px] font-medium"
                role="status"
            >
                <LoaderCircle
                    class="size-3.5 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                {{ $t('The AI is drafting the meaning…') }}
            </p>
            <p
                v-else-if="state === 'manual'"
                class="text-ink-slate text-[11.5px]"
            >
                {{ $t('Written by hand. The AI never replaces it.') }}
            </p>
            <p
                v-else-if="state === 'failed'"
                class="text-danger-text text-[11.5px]"
            >
                {{ $t('The AI draft failed. Try again or write it yourself.') }}
            </p>
            <p v-else class="text-ink-slate text-[11.5px]">
                {{
                    $t(
                        'Learners see it only after they tap Show Meaning. Saving marks it as written by hand.',
                    )
                }}
            </p>

            <p v-if="error !== ''" class="text-danger-text text-[12px]">
                {{ error }}
            </p>
            <p
                v-else-if="savedFlash"
                class="text-success-text text-[12px] font-semibold"
                role="status"
            >
                {{ $t('Meaning saved.') }}
            </p>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    v-if="state !== 'manual'"
                    type="button"
                    :disabled="busy || state === 'drafting'"
                    data-test="field-meaning-draft-button"
                    class="border-line text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/15 inline-flex h-11 items-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60 md:h-9"
                    @click="onDraft"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{
                        state === 'ai' || state === 'failed'
                            ? $t('Redraft with AI')
                            : $t('Draft with AI')
                    }}
                </button>
                <button
                    type="button"
                    :disabled="!canSave"
                    data-test="field-meaning-save-button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn focus-visible:ring-brand-600/30 font-heading inline-flex h-11 items-center gap-1.5 rounded-md px-4 text-[12.5px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50 disabled:shadow-none md:h-9"
                    @click="onSave"
                >
                    <LoaderCircle
                        v-if="busy"
                        class="size-3.5 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    {{ $t('Save meaning') }}
                </button>
            </div>
        </div>
    </DefineBody>

    <button
        v-if="empty"
        type="button"
        disabled
        :aria-label="triggerLabel"
        :title="triggerLabel"
        :class="triggerClass"
        data-test="field-meaning-button"
    >
        <Languages class="size-3.5" aria-hidden="true" />
        <span v-if="!compact">{{ $t('Translate meaning to Arabic') }}</span>
    </button>

    <template v-else-if="isPhone">
        <button
            type="button"
            :aria-label="compact ? stateTitle : undefined"
            :title="stateTitle"
            :class="triggerClass"
            data-test="field-meaning-button"
            @click="open = true"
        >
            <ReuseFace />
        </button>
        <Sheet v-model:open="open">
            <SheetContent
                side="bottom"
                class="bg-surface border-line max-h-[92dvh] overflow-y-auto rounded-t-xl px-4 pb-6"
            >
                <div
                    class="bg-line-strong mx-auto mt-3 mb-1 h-1.5 w-10 rounded-full"
                    aria-hidden="true"
                />
                <SheetHeader class="px-0 text-start">
                    <SheetTitle
                        class="font-heading text-brand-900 text-[17px] font-semibold"
                    >
                        {{ $t('Translate meaning to Arabic') }}
                    </SheetTitle>
                    <SheetDescription class="text-ink-slate text-[12.5px]">
                        {{ label }}
                    </SheetDescription>
                </SheetHeader>
                <ReuseBody />
            </SheetContent>
        </Sheet>
    </template>

    <PopoverRoot v-else v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                :aria-label="compact ? stateTitle : undefined"
                :title="stateTitle"
                :aria-controls="`${id}-meaning`"
                :class="triggerClass"
                data-test="field-meaning-button"
            >
                <ReuseFace />
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                :id="`${id}-meaning`"
                side="bottom"
                align="end"
                :side-offset="6"
                :collision-padding="16"
                :aria-label="$t('Translate meaning to Arabic')"
                class="border-line bg-surface shadow-pop data-[state=open]:animate-in data-[state=open]:fade-in-0 z-50 w-[22rem] max-w-[calc(100vw-32px)] rounded-lg border p-4 motion-reduce:animate-none"
                @open-auto-focus.prevent
            >
                <p
                    class="font-heading text-brand-900 mb-3 text-[14px] font-semibold"
                >
                    {{ $t('Translate meaning to Arabic') }}
                    <span class="text-ink-slate block text-[12px] font-normal">
                        {{ label }}
                    </span>
                </p>
                <ReuseBody />
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
