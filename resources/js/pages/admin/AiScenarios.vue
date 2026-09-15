<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AiScenariosEditorPanel from '@/components/ai-scenarios/AiScenariosEditorPanel.vue';
import AiScenariosHeaderAccent from '@/components/ai-scenarios/AiScenariosHeaderAccent.vue';
import AiScenariosLibraryPanel from '@/components/ai-scenarios/AiScenariosLibraryPanel.vue';
import AiScenariosSidebarPanel from '@/components/ai-scenarios/AiScenariosSidebarPanel.vue';
import AiScenariosToolbar from '@/components/ai-scenarios/AiScenariosToolbar.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { aiScenarios, dashboard } from '@/routes';
import type {
    AiScenarioEditor,
    AiScenarioLibrary,
    AiScenarioPreview,
    AiScenarioSettings,
    AiScenarioTab,
    AiScenarioTabKey,
} from '@/types';

type Props = {
    tabs: AiScenarioTab[];
    activeTab: AiScenarioTabKey;
    library: AiScenarioLibrary;
    editor: AiScenarioEditor;
    preview: AiScenarioPreview;
    settings: AiScenarioSettings;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'AI Scenarios',
                href: aiScenarios(),
            },
        ],
    },
});
</script>

<template>
    <Head title="AI Scenarios" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">AI Scenarios</h1>

        <PageHeader
            title="AI Role-play Scenarios"
            description="Create and manage realistic conversation scenarios for hotel staff."
            class="mb-1"
        >
            <template #accent>
                <AiScenariosHeaderAccent />
            </template>
        </PageHeader>

        <AiScenariosToolbar :tabs="tabs" :active-tab="activeTab" />

        <div
            class="ai-scenarios-layout grid min-w-0 gap-3"
        >
            <AiScenariosLibraryPanel :library="library" />
            <AiScenariosEditorPanel :editor="editor" />
            <AiScenariosSidebarPanel
                :preview="preview"
                :settings="settings"
                class="lg:col-span-2 xl:col-span-1"
            />
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .ai-scenarios-layout {
        grid-template-columns: 286px minmax(0, 1fr);
    }
}

@media (min-width: 1280px) {
    .ai-scenarios-layout {
        align-items: start;
        grid-template-columns: 286px minmax(0, 1fr) 330px;
    }
}
</style>