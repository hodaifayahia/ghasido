<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/interface-languages';

/*
 * Add an interface language (client request 2026-10-03): pick a common one
 * or type any language's code and names.
 */
const suggestions = [
    { code: 'fr', name: 'French', native: 'Français', dir: 'ltr' },
    { code: 'es', name: 'Spanish', native: 'Español', dir: 'ltr' },
    { code: 'de', name: 'German', native: 'Deutsch', dir: 'ltr' },
    { code: 'it', name: 'Italian', native: 'Italiano', dir: 'ltr' },
    { code: 'tr', name: 'Turkish', native: 'Türkçe', dir: 'ltr' },
    { code: 'pt', name: 'Portuguese', native: 'Português', dir: 'ltr' },
    { code: 'ru', name: 'Russian', native: 'Русский', dir: 'ltr' },
    { code: 'zh', name: 'Chinese', native: '中文', dir: 'ltr' },
    { code: 'ja', name: 'Japanese', native: '日本語', dir: 'ltr' },
    { code: 'id', name: 'Indonesian', native: 'Bahasa Indonesia', dir: 'ltr' },
    { code: 'fa', name: 'Persian', native: 'فارسی', dir: 'rtl' },
    { code: 'ur', name: 'Urdu', native: 'اردو', dir: 'rtl' },
] as const;

type Props = { existing: string[] };

defineProps<Props>();

const form = useForm({
    code: '',
    name: '',
    native_name: '',
    direction: 'ltr',
});

function pick(option: (typeof suggestions)[number]): void {
    form.code = option.code;
    form.name = option.name;
    form.native_name = option.native;
    form.direction = option.dir;
    form.clearErrors();
}

function submit(): void {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const field =
    'border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full min-w-0 rounded-md border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <form
        class="border-line bg-surface shadow-card grid min-w-0 gap-3 rounded-lg border p-4"
        data-test="interface-language-add"
        @submit.prevent="submit"
    >
        <div>
            <h2 class="font-heading text-brand-900 text-[15px] font-semibold">
                {{ $t('Add a language') }}
            </h2>
            <p class="text-ink-slate mt-0.5 text-[12.5px] leading-5">
                {{
                    $t(
                        'Choose one below or type any language. It stays hidden from the language menu until you switch it on.',
                    )
                }}
            </p>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="option in suggestions"
                :key="option.code"
                type="button"
                :disabled="existing.includes(option.code)"
                class="border-line text-ink hover:bg-brand-50 focus-visible:ring-brand-600/15 rounded-pill min-h-9 border px-3 text-[12.5px] font-medium focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40"
                @click="pick(option)"
            >
                <span :dir="option.dir">{{ option.native }}</span>
            </button>
        </div>

        <div class="grid min-w-0 gap-2 sm:grid-cols-[110px_1fr_1fr_130px_auto]">
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11.5px] font-semibold">{{
                    $t('Code')
                }}</span>
                <input
                    v-model="form.code"
                    :class="field"
                    placeholder="fr"
                    maxlength="12"
                    required
                    dir="ltr"
                    data-test="interface-language-code"
                />
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11.5px] font-semibold">{{
                    $t('Name in English')
                }}</span>
                <input
                    v-model="form.name"
                    :class="field"
                    placeholder="French"
                    maxlength="60"
                    required
                    data-test="interface-language-name"
                />
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11.5px] font-semibold">{{
                    $t('Its own name')
                }}</span>
                <input
                    v-model="form.native_name"
                    :class="field"
                    placeholder="Français"
                    maxlength="60"
                    required
                    dir="auto"
                    data-test="interface-language-native"
                />
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11.5px] font-semibold">{{
                    $t('Direction')
                }}</span>
                <select v-model="form.direction" :class="field">
                    <option value="ltr">{{ $t('Left to right') }}</option>
                    <option value="rtl">{{ $t('Right to left') }}</option>
                </select>
            </label>
            <Button
                type="submit"
                class="h-10 self-end"
                :disabled="form.processing"
                data-test="interface-language-add-button"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ $t('Add') }}
            </Button>
        </div>
        <InputError
            :message="
                form.errors.code ?? form.errors.name ?? form.errors.native_name
            "
        />
    </form>
</template>
