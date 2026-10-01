<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import HelperLanguagePicker from '@/components/meaning/HelperLanguagePicker.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/*
 * Asked once on sign-in, for every role (client request 2026-10-01): which
 * language Show Meaning should explain English in. Shown until the user
 * saves a choice (`helperLanguage.chosen`), after the one-time welcome
 * splash, and never on the first-login form, which asks it itself.
 * Settings → Helper language changes it later.
 */
const page = usePage();
const language = computed(() => page.props.helperLanguage);

const needed = computed(
    () =>
        language.value !== null &&
        !language.value.chosen &&
        language.value.options.length > 0 &&
        !page.component.includes('FirstLogin'),
);

const ready = ref(false);
const open = computed({
    get: () => ready.value && needed.value,
    set: (value: boolean) => {
        // Closing without saving keeps the default until the next visit.
        if (!value) {
            ready.value = false;
        }
    },
});

const chosen = ref(language.value?.code ?? '');
const saving = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    // The welcome splash plays first (5 s) on the very first sign-in.
    const delay = page.props.auth.user?.show_welcome ? 5600 : 400;
    timer = setTimeout(() => {
        ready.value = true;
        chosen.value = language.value?.code ?? '';
    }, delay);
});

onUnmounted(() => {
    if (timer !== null) {
        clearTimeout(timer);
    }
});

function save(): void {
    if (language.value === null || chosen.value === '') {
        return;
    }

    saving.value = true;
    router.put(
        language.value.updateUrl,
        { language: chosen.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[92dvh] overflow-y-auto sm:max-w-lg"
            data-test="helper-language-dialog"
        >
            <DialogHeader>
                <span
                    class="bg-brand-50 text-brand-600 mb-1 grid size-11 place-items-center rounded-xl"
                    aria-hidden="true"
                >
                    <Languages class="size-6" />
                </span>
                <DialogTitle class="font-heading text-brand-900 text-xl">
                    {{ $t('Choose your helper language') }}
                </DialogTitle>
                <DialogDescription class="text-ink-slate">
                    {{
                        $t(
                            'When you tap Show Meaning, English is explained in this language. You can change it any time in Settings.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <HelperLanguagePicker
                v-if="language"
                v-model="chosen"
                :options="language.options"
            />

            <DialogFooter>
                <Button
                    class="min-h-11 w-full sm:w-auto"
                    :disabled="saving || chosen === ''"
                    data-test="save-helper-language-dialog-button"
                    @click="save"
                >
                    {{ saving ? $t('Saving…') : $t('Save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
