<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * "My level": the learner's saved level, changeable at any time (client
 * decision 2026-09-30). The lessons and tests they see follow it.
 */
type Props = { class?: HTMLAttributes['class'] };

const props = defineProps<Props>();

const page = usePage();
const level = computed(() => page.props.learnerLevel);
const chosen = ref(level.value?.current ?? '');
const saving = ref(false);

watch(
    () => level.value?.current,
    (value) => (chosen.value = value ?? ''),
);

function save(): void {
    if (level.value === null || chosen.value === '' || saving.value) {
        return;
    }

    router.put(
        level.value.updateUrl,
        { level: chosen.value },
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
        v-if="level"
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
        data-test="my-level"
    >
        <div class="flex items-center gap-3">
            <span
                class="bg-ai-tint text-ai grid size-10 shrink-0 place-items-center rounded-xl"
            >
                <GraduationCap class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h2 class="font-heading text-brand-900 text-base font-semibold">
                    {{ $t('My level') }}
                </h2>
                <p class="text-ink-slate text-sm">
                    {{
                        $t(
                            'Your lessons and tests follow your level. You can change it at any time.',
                        )
                    }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="sr-only" for="my-level-select">
                {{ $t('My level') }}
            </label>
            <select
                id="my-level-select"
                v-model="chosen"
                class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 min-w-44 rounded-md border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
            >
                <option value="" disabled>{{ $t('Choose your level') }}</option>
                <option
                    v-for="option in level.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <button
                type="button"
                :disabled="saving || chosen === '' || chosen === level.current"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 items-center rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50"
                data-test="save-level-button"
                @click="save"
            >
                {{ $t('Save level') }}
            </button>
        </div>
    </section>
</template>
