<script setup lang="ts">
import { Check } from '@lucide/vue';
import LanguageFlag from '@/components/meaning/LanguageFlag.vue';
import { cn } from '@/lib/utils';
import type { HelperLanguageOption } from '@/types';

/*
 * The helper languages as flag cards (client request 2026-10-01): flag,
 * the language's own name and its English name. One is chosen; the
 * choice is shown by a ring, a tint and a check (ACC-02).
 */
type Props = {
    options: HelperLanguageOption[];
};

defineProps<Props>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div
        class="grid min-w-0 grid-cols-1 gap-2.5 sm:grid-cols-2"
        role="radiogroup"
        :aria-label="$t('Helper language')"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            :data-test="`helper-language-option-${option.value}`"
            :class="
                cn(
                    'ease-brand focus-visible:ring-brand-600/15 flex min-h-14 min-w-0 items-center gap-3 rounded-lg border px-3.5 py-2.5 text-start transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none',
                    model === option.value
                        ? 'border-brand-600 bg-brand-50'
                        : 'border-line bg-surface hover:border-brand-300',
                )
            "
            @click="model = option.value"
        >
            <LanguageFlag
                :flag="option.flag"
                :code="option.value"
                class="h-6 w-9"
            />
            <span class="grid min-w-0 flex-1">
                <span
                    class="text-ink truncate text-[15px] font-semibold"
                    :lang="option.value"
                    :dir="option.dir"
                >
                    {{ option.native }}
                </span>
                <span
                    v-if="option.native !== option.name"
                    class="text-ink-slate truncate text-xs"
                >
                    {{ $t(option.name) }}
                </span>
            </span>
            <Check
                v-if="model === option.value"
                class="text-brand-600 size-5 shrink-0 stroke-[2.5]"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
