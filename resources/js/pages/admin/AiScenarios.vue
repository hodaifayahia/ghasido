<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CircleHelp, Phone, Settings2 } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import AiScenariosDirectoryStats from '@/components/ai-scenarios/AiScenariosDirectoryStats.vue';
import AiScenariosDirectoryTable from '@/components/ai-scenarios/AiScenariosDirectoryTable.vue';
import AiScenariosCategoriesPanel from '@/components/ai-scenarios/AiScenariosCategoriesPanel.vue';
import AiScenariosEditorPanel from '@/components/ai-scenarios/AiScenariosEditorPanel.vue';
import AiScenariosFeedbackPanel from '@/components/ai-scenarios/AiScenariosFeedbackPanel.vue';
import AiScenariosHeaderAccent from '@/components/ai-scenarios/AiScenariosHeaderAccent.vue';
import AiScenariosInstructionsPanel from '@/components/ai-scenarios/AiScenariosInstructionsPanel.vue';
import AiScenariosLibraryPanel from '@/components/ai-scenarios/AiScenariosLibraryPanel.vue';
import AiScenariosPreviewTestPanel from '@/components/ai-scenarios/AiScenariosPreviewTestPanel.vue';
import AiScenariosSidebarPanel from '@/components/ai-scenarios/AiScenariosSidebarPanel.vue';
import AiScenariosToolbar from '@/components/ai-scenarios/AiScenariosToolbar.vue';
import AiScenarioCreationTutorial from '@/components/ai-scenarios/AiScenarioCreationTutorial.vue';
import CreateAiScenarioDialog from '@/components/ai-scenarios/CreateAiScenarioDialog.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { Button } from '@/components/ui/button';
import TtsVoiceStudio from '@/components/tts/TtsVoiceStudio.vue';
import VoiceAgentSettingsDialog from '@/components/ai-scenarios/VoiceAgentSettingsDialog.vue';
import VoiceCall from '@/components/roleplay/VoiceCall.vue';
import { tk } from '@/lib/i18n';
import { aiScenarios, dashboard } from '@/routes';
import aiScenarioActions from '@/routes/ai-scenarios';
import type {
    AiScenarioDirectoryMetric,
    AiScenarioCategories,
    AiScenarioEditor,
    AiScenarioFeedbackTemplates,
    AiScenarioInstructions,
    AiScenarioInstructionsSavePayload,
    AiScenarioLibrary,
    AiScenarioPreview,
    AiScenarioPreviewTest,
    AiScenarioSettings,
    AiScenarioSavePayload,
    AiScenarioTab,
    AiScenarioTabKey,
    CreateAiScenarioPayload,
    TtsSettings,
    VoiceAgentPagePayload,
} from '@/types';

type Props = {
    tabs: AiScenarioTab[];
    activeTab: AiScenarioTabKey;
    builderOpen: boolean;
    stats: AiScenarioDirectoryMetric[];
    library: AiScenarioLibrary;
    editor: AiScenarioEditor;
    preview: AiScenarioPreview;
    settings: AiScenarioSettings;
    categories: AiScenarioCategories;
    instructions: AiScenarioInstructions;
    feedback: AiScenarioFeedbackTemplates;
    previewTest: AiScenarioPreviewTest;
    tts: TtsSettings;
    voiceAgent: VoiceAgentPagePayload;
};

const props = defineProps<Props>();

const activeTab = ref<AiScenarioTabKey>(props.activeTab);
const builderOpen = ref(props.builderOpen);
const createScenarioOpen = ref(false);
const tutorialOpen = ref(false);
const voiceSettingsOpen = ref(false);
const voiceCallOpen = ref(false);
const editorPanel = ref<InstanceType<typeof AiScenariosEditorPanel> | null>(
    null,
);

watch(
    () => props.activeTab,
    (value) => {
        activeTab.value = value;
    },
);

watch(
    () => props.builderOpen,
    (value) => {
        builderOpen.value = value;
    },
);

function openScenario(id: string): void {
    router.visit(aiScenarios.url({ query: { scenario: id } }), {
        preserveScroll: true,
    });
}

function createScenario(payload: CreateAiScenarioPayload): void {
    createScenarioOpen.value = false;

    router.post(
        aiScenarioActions.store.url(),
        {
            title: payload.title,
            department_id: Number(payload.department),
            difficulty: payload.level,
        },
        { preserveScroll: true },
    );
}

function saveScenario(payload: AiScenarioSavePayload): void {
    if (!props.editor.saveUrl) return;

    router.patch(props.editor.saveUrl, payload, { preserveScroll: true });
}

function generateScenario(): void {
    if (!props.editor.generateUrl) return;

    router.post(props.editor.generateUrl, {}, { preserveScroll: true });
}

function applyDraft(): void {
    if (!props.editor.applyDraftUrl) return;

    router.post(props.editor.applyDraftUrl, {}, { preserveScroll: true });
}

function publishScenario(): void {
    if (!props.editor.publishUrl) return;

    router.post(props.editor.publishUrl, {}, { preserveScroll: true });
}

function saveInstructions(payload: AiScenarioInstructionsSavePayload): void {
    if (!props.instructions.saveUrl) return;

    router.patch(props.instructions.saveUrl, payload, { preserveScroll: true });
}

function saveCategories(items: AiScenarioCategories['items']): void {
    if (!props.categories.saveUrl) return;

    router.patch(props.categories.saveUrl, { items }, { preserveScroll: true });
}

function saveFeedback(feedback: AiScenarioFeedbackTemplates): void {
    if (!props.feedback.saveUrl) return;

    router.patch(
        props.feedback.saveUrl,
        { sections: feedback.sections, templates: feedback.templates },
        { preserveScroll: true },
    );
}

function saveFromSidebar(settings: AiScenarioSavePayload['settings']): void {
    editorPanel.value?.save(settings);
}

// "Test Scenario" from the editor tests the scenario being edited, not the
// first one in the list (client report 2026-09-30).
function openPreview(): void {
    activeTab.value = 'preview';
    router.get(
        aiScenarios.url({
            query: {
                tab: 'preview',
                ...(props.editor.id
                    ? { scenario: String(props.editor.id) }
                    : {}),
            },
        }),
        {},
        { preserveScroll: true, replace: true },
    );
}

function openInstructions(): void {
    selectTab('instructions');
}

function selectTab(tab: AiScenarioTabKey): void {
    if (activeTab.value === tab) return;

    activeTab.value = tab;
    router.get(
        aiScenarios.url({ query: { tab } }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

let generationTimer: ReturnType<typeof setInterval> | null = null;

function stopGenerationPolling(): void {
    if (generationTimer !== null) {
        clearInterval(generationTimer);
        generationTimer = null;
    }
}

watch(
    () => props.editor.aiStatus,
    (status) => {
        stopGenerationPolling();

        if (status === 'pending' || status === 'running') {
            generationTimer = setInterval(() => {
                router.reload({ only: ['editor'] });
            }, 1500);
        }
    },
    { immediate: true },
);

onBeforeUnmount(stopGenerationPolling);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: tk('Dashboard'),
                href: dashboard(),
            },
            {
                title: tk('AI Scenarios'),
                href: aiScenarios(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('AI Scenarios')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">{{ $t('AI Scenarios') }}</h1>

        <PageHeader
            :title="
                builderOpen
                    ? $t('Create / Edit Scenario')
                    : $t('AI Role-play Scenarios')
            "
            :description="
                builderOpen
                    ? $t(
                          'Build the hotel conversation, roles and AI coaching rules.',
                      )
                    : $t(
                          'Create and manage realistic conversation scenarios for hotel staff.',
                      )
            "
            class="mb-1"
        >
            <template #accent>
                <AiScenariosHeaderAccent />
            </template>
        </PageHeader>

        <AiScenariosToolbar
            v-model:active-tab="activeTab"
            :tabs="tabs"
            :show-tabs="false"
            @create="createScenarioOpen = true"
            @select="selectTab"
        />

        <template v-if="activeTab === 'scenarios'">
            <template v-if="!builderOpen">
                <AiScenariosDirectoryStats :stats="stats" />
                <AiScenariosDirectoryTable
                    :library="library"
                    @open="openScenario"
                    @create="createScenarioOpen = true"
                    @tutorial="tutorialOpen = true"
                />
            </template>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Link
                        :href="aiScenarios.url()"
                        class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex min-h-11 w-fit items-center gap-1.5 rounded-sm text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:min-h-0"
                        data-test="back-to-scenario-library"
                    >
                        <ArrowLeft class="size-3.5" aria-hidden="true" />
                        {{ $t('Back to Scenario Library') }}
                    </Link>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
                        data-test="ai-scenario-creation-tutorial-editor-button"
                        @click="createScenarioOpen = true"
                    >
                        {{ $t('Create another scenario') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
                        data-test="ai-scenario-creation-tutorial-editor-guide"
                        @click="tutorialOpen = true"
                    >
                        <CircleHelp class="size-3.5" aria-hidden="true" />
                        {{ $t('Visual scenario guide') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
                        data-test="voice-agent-settings-button"
                        @click="voiceSettingsOpen = true"
                    >
                        <Settings2 class="size-3.5" aria-hidden="true" />
                        {{ $t('Voice call settings') }}
                    </Button>
                    <Button
                        v-if="voiceAgent.scenario"
                        type="button"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold text-white md:h-8"
                        data-test="test-voice-call-button"
                        :disabled="!voiceAgent.settings.apiConfigured"
                        :title="
                            voiceAgent.settings.apiConfigured
                                ? undefined
                                : $t('Set DEEPGRAM_API_KEY on the server first')
                        "
                        @click="voiceCallOpen = true"
                    >
                        <Phone class="size-3.5" aria-hidden="true" />
                        {{ $t('Test voice call') }}
                    </Button>
                </div>

                <div class="ai-scenarios-layout grid min-w-0 gap-3">
                    <AiScenariosLibraryPanel
                        :library="library"
                        @open="openScenario"
                    />
                    <AiScenariosEditorPanel
                        ref="editorPanel"
                        :editor="editor"
                        @save="saveScenario"
                        @generate="generateScenario"
                        @apply-draft="applyDraft"
                        @publish="publishScenario"
                        @open-instructions="openInstructions"
                    />
                    <div class="ai-scenarios-right-rail">
                        <AiScenariosSidebarPanel
                            :preview="preview"
                            :settings="settings"
                            @preview="openPreview"
                            @save="saveFromSidebar"
                            @update="saveFromSidebar"
                        />
                        <TtsVoiceStudio :settings="tts" compact />
                    </div>
                </div>
            </template>
        </template>

        <AiScenariosCategoriesPanel
            v-else-if="activeTab === 'categories'"
            :categories="categories"
            @save="saveCategories"
        />

        <AiScenariosInstructionsPanel
            v-else-if="activeTab === 'instructions'"
            :instructions="instructions"
            @save="saveInstructions"
        />

        <AiScenariosFeedbackPanel
            v-else-if="activeTab === 'feedback'"
            :feedback="feedback"
            @save="saveFeedback"
        />

        <template v-else-if="activeTab === 'preview'">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Link
                    :href="aiScenarios.url()"
                    class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex min-h-11 w-fit items-center gap-1.5 rounded-sm text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:min-h-0"
                    data-test="preview-back-to-scenario-library"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    {{ $t('Back to Scenario Library') }}
                </Link>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
                    data-test="voice-agent-settings-preview-button"
                    @click="voiceSettingsOpen = true"
                >
                    <Settings2 class="size-3.5" aria-hidden="true" />
                    {{ $t('Voice call settings') }}
                </Button>
            </div>
            <AiScenariosPreviewTestPanel :preview-test="previewTest" />
        </template>

        <VoiceAgentSettingsDialog
            v-model:open="voiceSettingsOpen"
            :settings="voiceAgent.settings"
            :scenario="builderOpen ? voiceAgent.scenario : null"
        />

        <VoiceCall
            v-if="voiceAgent.scenario"
            v-model:open="voiceCallOpen"
            :start-url="voiceAgent.scenario.startUrl"
            :title="
                $t('Test call: :title', { title: voiceAgent.scenario.title })
            "
            :guest-role="voiceAgent.scenario.guestRole"
            preview
        />

        <CreateAiScenarioDialog
            v-model:open="createScenarioOpen"
            :departments="library.departments"
            @create="createScenario"
        />

        <AiScenarioCreationTutorial v-model:open="tutorialOpen" />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .ai-scenarios-layout {
        grid-template-columns: 286px minmax(0, 1fr);
    }

    .ai-scenarios-right-rail {
        grid-column: 1 / -1;
    }
}

@media (min-width: 1280px) {
    .ai-scenarios-layout {
        align-items: start;
        grid-template-columns: 286px minmax(0, 1fr) 330px;
    }

    .ai-scenarios-right-rail {
        position: sticky;
        top: 1rem;
        grid-column: auto;
        max-height: calc(100dvh - 7rem);
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
    }
}
</style>
