<script setup lang="ts">
import { CircleAlert, Info, LoaderCircle } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import type { MeaningState } from '@/composables/useMeaning';
import { cn } from '@/lib/utils';
import { useHelperLanguage } from '@/composables/useHelperLanguage';

/*
 * The revealed meaning (CTRL-01..03, I18N-01, I18N-03): Arabic first, in
 * Cairo, right-to-left inside the LTR page, then the simple English
 * explanation and the hotel example with its Arabic. Rendered only while
 * `shown`, and never inside a test (CTRL-04): the test payload carries no
 * Arabic at all.
 *
 * `state` is set when the meaning is fetched on the tap (useMeaning; client
 * request 2026-11-05): while it loads, or when nobody has added it yet, the
 * panel says so with an icon and words (ACC-02); the explanation still
 * shows.
 */
type Props = {
    shown: boolean;
    id?: string;
    arabic: string | null;
    explanation?: string | null;
    example?: string | null;
    exampleArabic?: string | null;
    state?: MeaningState;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    explanation: null,
    example: null,
    exampleArabic: null,
    state: 'ready',
});

const emit = defineEmits<{ retry: [] }>();

// `arabic` / `exampleArabic` hold the meaning in the learner's helper
// language (client request 2026-09-30); the server swaps them.
const helper = useHelperLanguage();
</script>

<template>
    <div
        v-if="shown"
        :id="id"
        :class="
            cn(
                'bg-brand-50 animate-fade-up rounded-md px-5 py-4 motion-reduce:animate-none',
                props.class,
            )
        "
    >
        <p
            v-if="state === 'ready' && arabic"
            :lang="helper.code.value"
            :dir="helper.dir.value"
            :class="
                cn(
                    'text-ink text-start text-xl font-semibold',
                    helper.fontClass.value,
                )
            "
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
            v-else-if="state === 'loading' || state === 'idle'"
            role="status"
            class="text-ink-slate flex flex-wrap items-center gap-2 text-sm"
        >
            <LoaderCircle
                class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            <span>{{ $t('Finding the meaning…') }}</span>
        </p>
        <p v-if="explanation" class="text-ink-graphite mt-1 text-base">
            {{ explanation }}
        </p>
        <div v-if="example" class="border-line mt-3 border-t pt-3">
            <p class="text-ink text-base">{{ example }}</p>
            <p
                v-if="exampleArabic"
                :lang="helper.code.value"
                :dir="helper.dir.value"
                :class="
                    cn(
                        'text-ink-graphite mt-1 text-start text-base',
                        helper.fontClass.value,
                    )
                "
            >
                {{ exampleArabic }}
            </p>
        </div>
    </div>
</template>
