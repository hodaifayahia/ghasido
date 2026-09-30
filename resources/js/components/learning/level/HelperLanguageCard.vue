<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The learner's Show Meaning language (client request 2026-09-30): Arabic,
 * French or any language the Super Admin offers. Saved on the account.
 * Renders nothing when only one language is on.
 */
type Props = { class?: HTMLAttributes['class'] };

const props = defineProps<Props>();

const page = usePage();
const language = computed(() => page.props.helperLanguage);
const chosen = ref(language.value?.code ?? '');
const saving = ref(false);

watch(
    () => language.value?.code,
    (value) => (chosen.value = value ?? ''),
);

function save(): void {
    const url = language.value?.updateUrl;

    if (!url || chosen.value === '' || saving.value) {
        return;
    }

    router.put(
        url,
        { language: chosen.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <section
        v-if="language?.updateUrl && language.options.length > 1"
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
        data-test="helper-language"
    >
        <div class="flex items-center gap-3">
            <span
                class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
            >
                <Languages class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h2 class="font-heading text-brand-900 text-base font-semibold">
                    {{ $t('Language for word meanings') }}
                </h2>
                <p class="text-ink-slate text-sm">
                    {{
                        $t(
                            'Show Meaning explains English words in this language.',
                        )
                    }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="sr-only" for="helper-language-select">
                {{ $t('Language for word meanings') }}
            </label>
            <select
                id="helper-language-select"
                v-model="chosen"
                class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 min-w-44 rounded-md border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
            >
                <option
                    v-for="option in language.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <button
                type="button"
                :disabled="saving || chosen === language.code"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 items-center rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50"
                data-test="save-helper-language-button"
                @click="save"
            >
                {{ $t('Save language') }}
            </button>
        </div>
    </section>
</template>
