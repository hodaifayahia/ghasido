<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { useHelperLanguage } from '@/composables/useHelperLanguage';

/*
 * The revealed meaning (CTRL-01..03, I18N-01, I18N-03): Arabic first, in
 * Cairo, right-to-left inside the LTR page, then the simple English
 * explanation and the hotel example with its Arabic. Rendered only while
 * `shown`, and never inside a test (CTRL-04): the test payload carries no
 * Arabic at all.
 */
type Props = {
    shown: boolean;
    id?: string;
    arabic: string | null;
    explanation?: string | null;
    example?: string | null;
    exampleArabic?: string | null;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    explanation: null,
    example: null,
    exampleArabic: null,
});

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
            v-if="arabic"
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
