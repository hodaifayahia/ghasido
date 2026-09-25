<script setup lang="ts">
import { Settings2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { TestEditor, TestSettings } from '@/types';

const props = defineProps<{
    editor: TestEditor;
    settings: TestSettings;
}>();

const emit = defineEmits<{
    save: [settings: TestSettings];
}>();

const toggles = ref(props.settings.toggles.map((toggle) => ({ ...toggle })));
const passMark = ref(props.settings.passMark);

watch(
    () => props.settings,
    (settings) => {
        toggles.value = settings.toggles.map((toggle) => ({ ...toggle }));
        passMark.value = settings.passMark;
    },
    { deep: true },
);

function save(): void {
    emit('save', { toggles: toggles.value, passMark: passMark.value });
}
</script>

<template>
    <PanelCard
        title="Test Settings"
        title-id="test-settings-title"
        class="min-w-0"
    >
        <template #icon>
            <div
                class="bg-ai/20 text-ai grid size-8 place-items-center rounded-md"
            >
                <Settings2 class="size-4" aria-hidden="true" />
            </div>
        </template>

        <div
            v-if="editor.id"
            class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]"
        >
            <div class="grid content-start gap-3">
                <p class="text-brand-900 text-sm font-semibold">
                    {{ editor.title }}
                </p>
                <label
                    v-for="toggle in toggles"
                    :key="toggle.key"
                    class="text-brand-900 flex items-center gap-2 text-[13px]"
                >
                    <Checkbox v-model="toggle.checked" class="size-4" />
                    <span>{{ toggle.label }}</span>
                </label>
            </div>
            <div class="grid content-start gap-2">
                <label
                    for="settings-pass-mark"
                    class="text-brand-900 text-xs font-semibold"
                    >Pass mark (%)</label
                >
                <Input
                    id="settings-pass-mark"
                    v-model="passMark"
                    type="number"
                    min="0"
                    max="100"
                    class="border-line h-10"
                />
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 text-xs font-semibold text-white"
                    @click="save"
                >
                    Save Settings
                </Button>
            </div>
        </div>
        <p
            v-else
            class="text-ink-slate border-line rounded-md border border-dashed px-4 py-10 text-center text-sm"
        >
            Open a test from the Tests tab to edit its settings.
        </p>
    </PanelCard>
</template>
