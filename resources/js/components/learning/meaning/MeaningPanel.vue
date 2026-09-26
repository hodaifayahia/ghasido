<script setup lang="ts">
import { CircleAlert, Info, LoaderCircle } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import type { MeaningState } from '@/composables/useMeaning';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';

/*
 * The revealed Arabic under a text (CTRL-01..03, I18N-03): Cairo, right to
 * left inside the LTR page, only while `shown`. Loading, "not added yet"
 * and failure are said with an icon and words, never colour alone (ACC-02).
 */
type Props = {
    shown: boolean;
    state: MeaningState;
    arabic: string | null;
    id?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { id: undefined });

const emit = defineEmits<{ retry: [] }>();

// The Arabic echo beside an English status line only helps the English
// interface; in the Arabic one the line itself is already Arabic.
const { locale } = useI18n();
</script>

<template>
    <div
        v-if="shown"
        :id="id"
        role="status"
        aria-live="polite"
        :class="
            cn(
                'bg-brand-50 animate-fade-up mt-2 rounded-md px-4 py-3 motion-reduce:animate-none',
                props.class,
            )
        "
    >
        <p
            v-if="state === 'ready' && arabic"
            lang="ar"
            dir="rtl"
            class="font-arabic text-ink text-start text-lg leading-[1.9] font-semibold"
        >
            {{ arabic }}
        </p>
        <p
            v-else-if="state === 'missing'"
            class="text-ink-slate flex flex-wrap items-center gap-2 text-sm"
        >
            <Info class="size-4 shrink-0" aria-hidden="true" />
            <span>{{
                $t('The meaning of this text has not been added yet.')
            }}</span>
            <span v-if="locale === 'en'" lang="ar" dir="rtl" class="font-arabic"
                >لم تُضف الترجمة بعد.</span
            >
        </p>
        <p
            v-else-if="state === 'error'"
            class="text-danger-text flex flex-wrap items-center gap-2 text-sm"
        >
            <CircleAlert class="size-4 shrink-0" aria-hidden="true" />
            <span>{{ $t('The meaning is not available right now.') }}</span>
            <button
                type="button"
                class="text-brand-600 min-h-8 font-semibold underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none"
                @click.stop.prevent="emit('retry')"
            >
                {{ $t('Try again') }}
            </button>
        </p>
        <p
            v-else
            class="text-ink-slate flex flex-wrap items-center gap-2 text-sm"
        >
            <LoaderCircle
                class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            <span>{{ $t('Finding the meaning…') }}</span>
            <span v-if="locale === 'en'" lang="ar" dir="rtl" class="font-arabic"
                >جارٍ الترجمة…</span
            >
        </p>
    </div>
</template>
