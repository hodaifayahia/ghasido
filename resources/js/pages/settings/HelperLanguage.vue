<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import HelperLanguagePicker from '@/components/meaning/HelperLanguagePicker.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { edit, update } from '@/routes/helper-language';

/*
 * Settings → Helper language (client request 2026-10-01), for every role:
 * the language Show Meaning explains English in. Changeable at any time.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('Helper language'), href: edit() }],
    },
});

const page = usePage();
const language = computed(() => page.props.helperLanguage);
const chosen = ref(language.value?.code ?? '');
const saving = ref(false);

watch(
    () => language.value?.code,
    (code) => {
        chosen.value = code ?? '';
    },
);

function save(): void {
    saving.value = true;
    router.put(
        update.url(),
        { language: chosen.value },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Helper language')" />

    <h1 class="sr-only">{{ $t('Helper language') }}</h1>

    <div class="flex min-w-0 flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Helper language')"
            :description="
                $t(
                    'The language Show Meaning uses to explain English words and sentences. You can change it at any time.',
                )
            "
        />

        <HelperLanguagePicker
            v-if="language"
            v-model="chosen"
            :options="language.options"
        />

        <div>
            <Button
                class="min-h-11"
                :disabled="saving || chosen === '' || chosen === language?.code"
                data-test="save-helper-language-settings-button"
                @click="save"
            >
                {{ saving ? $t('Saving…') : $t('Save') }}
            </Button>
        </div>
    </div>
</template>
