<script setup lang="ts">
import { Check, Languages } from '@lucide/vue';
import { useId } from 'vue';
import LanguageFlag from '@/components/meaning/LanguageFlag.vue';
import { cn } from '@/lib/utils';
import type { HelperLanguageOption } from '@/types';

/*
 * "Which languages would help you understand English?" on the sign-up and
 * checkout forms (client request 2026-10-01): the offered helper languages
 * as flag chips (several may be picked), and a box for one that is not
 * offered yet. Optional. The Super Admin sees the answer on the request.
 */
type Props = {
    options: HelperLanguageOption[];
    error?: string;
    otherError?: string;
};

defineProps<Props>();

const languages = defineModel<string[]>('languages', { required: true });
const other = defineModel<string>('other', { required: true });

const id = useId();

function toggle(code: string): void {
    languages.value = languages.value.includes(code)
        ? languages.value.filter((value) => value !== code)
        : [...languages.value, code];
}
</script>

<template>
    <fieldset class="grid min-w-0 gap-3" data-test="signup-helper-languages">
        <legend
            class="text-ink-indigo mb-1 flex items-center gap-2 text-[13px] font-bold tracking-[0.08em] uppercase"
        >
            <Languages class="size-4 shrink-0" aria-hidden="true" />
            {{ $t('Helper languages') }}
        </legend>
        <p class="text-ink-slate text-sm">
            {{
                $t(
                    'Which languages would help your team understand English? Show Meaning explains lessons in them. Optional.',
                )
            }}
        </p>

        <div class="flex min-w-0 flex-wrap gap-2">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="checkbox"
                :aria-checked="languages.includes(option.value)"
                :data-test="`signup-helper-language-${option.value}`"
                :class="
                    cn(
                        'ease-brand focus-visible:ring-brand-600/15 inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none',
                        languages.includes(option.value)
                            ? 'border-brand-600 bg-brand-50 text-brand-800'
                            : 'border-line bg-surface text-ink hover:border-brand-300',
                    )
                "
                @click="toggle(option.value)"
            >
                <LanguageFlag :flag="option.flag" :code="option.value" />
                <span :lang="option.value" :dir="option.dir">{{
                    option.native
                }}</span>
                <Check
                    v-if="languages.includes(option.value)"
                    class="text-brand-600 size-4 shrink-0 stroke-[2.5]"
                    aria-hidden="true"
                />
            </button>
        </div>
        <!-- For a plain <Form> post (the hotel sign-up form). -->
        <input
            v-for="code in languages"
            :key="`hidden-${code}`"
            type="hidden"
            name="helper_languages[]"
            :value="code"
        />
        <p v-if="error" class="text-danger-text text-xs" role="alert">
            {{ error }}
        </p>

        <label :for="`${id}-other`" class="grid min-w-0 gap-1.5">
            <span class="text-ink text-sm font-medium">
                {{ $t('Another language (optional)') }}
            </span>
            <input
                :id="`${id}-other`"
                v-model="other"
                name="helper_language_other"
                type="text"
                maxlength="60"
                autocomplete="off"
                dir="auto"
                :placeholder="$t('For example: Turkish, Spanish')"
                data-test="signup-helper-language-other"
                class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 w-full min-w-0 rounded-md border px-3 text-sm focus-visible:ring-3 focus-visible:outline-none"
            />
        </label>
        <p v-if="otherError" class="text-danger-text text-xs" role="alert">
            {{ otherError }}
        </p>
    </fieldset>
</template>
