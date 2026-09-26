<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CreateTestDialog from '@/components/tests/CreateTestDialog.vue';
import TestsDirectoryStats from '@/components/tests/TestsDirectoryStats.vue';
import TestsDirectoryTable from '@/components/tests/TestsDirectoryTable.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { Button } from '@/components/ui/button';
import TestsEditorPanel from '@/components/tests/TestsEditorPanel.vue';
import TestsHeaderAccent from '@/components/tests/TestsHeaderAccent.vue';
import TestsListPanel from '@/components/tests/TestsListPanel.vue';
import TestPreviewDialog from '@/components/tests/TestPreviewDialog.vue';
import TestsQuestionBankPanel from '@/components/tests/TestsQuestionBankPanel.vue';
import TestsResultsPanel from '@/components/tests/TestsResultsPanel.vue';
import TestsSidebarPanel from '@/components/tests/TestsSidebarPanel.vue';
import TestsSettingsPanel from '@/components/tests/TestsSettingsPanel.vue';
import TestsToolbar from '@/components/tests/TestsToolbar.vue';
import { dashboard, tests } from '@/routes';
import testActions from '@/routes/tests';
import type {
    CreateTestPayload,
    TestAiGeneratePayload,
    TestEditor,
    TestEditorQuestion,
    TestEditorSavePayload,
    TestMedia,
    TestPreview,
    TestQuestionPayload,
    TestResults,
    TestQuestionBankItem,
    TestSettings,
    TestDirectoryMetric,
    TestsList,
    TestsTab,
    TestsTabKey,
} from '@/types';

type Props = {
    tabs: TestsTab[];
    activeTab: TestsTabKey;
    builderOpen: boolean;
    stats: TestDirectoryMetric[];
    list: TestsList;
    editor: TestEditor;
    preview: TestPreview;
    media: TestMedia;
    settings: TestSettings;
    results: TestResults;
    questionBank: TestQuestionBankItem[];
};

const props = defineProps<Props>();

const builderOpen = ref(props.builderOpen);
const activeTab = ref(props.activeTab);
const createTestOpen = ref(false);
const previewOpen = ref(false);
const editorPanel = ref<InstanceType<typeof TestsEditorPanel> | null>(null);

watch(
    () => props.builderOpen,
    (value) => {
        builderOpen.value = value;
    },
);

watch(
    () => props.activeTab,
    (value) => {
        activeTab.value = value;
    },
);

function changeTab(tab: TestsTabKey): void {
    activeTab.value = tab;
    const query: Record<string, string> = { tab };

    if (props.editor.id !== null) {
        query.test = String(props.editor.id);
    }

    router.get(
        tests.url({ query }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function openTest(id: string): void {
    router.visit(tests.url({ query: { test: id } }), {
        preserveScroll: true,
    });
}

function createTest(payload: CreateTestPayload): void {
    createTestOpen.value = false;

    router.post(
        testActions.store.url(),
        {
            title: payload.title,
            type: payload.type,
            department_id: Number(payload.department),
            time_limit_minutes:
                payload.timeLimit === '' ? null : Number(payload.timeLimit),
        },
        {
            preserveScroll: true,
        },
    );
}

function saveTest(payload: TestEditorSavePayload): void {
    if (!props.editor.updateUrl) return;

    router.patch(props.editor.updateUrl, payload, {
        preserveScroll: true,
    });
}

function saveSettings(settings: TestSettings): void {
    if (editorPanel.value) {
        editorPanel.value.save(settings);

        return;
    }

    if (!props.editor.updateUrl) return;

    router.patch(
        props.editor.updateUrl,
        {
            title: props.editor.title,
            type: props.editor.type,
            department_id: Number(props.editor.department),
            hotel_id: props.editor.hotel ? Number(props.editor.hotel) : null,
            description: props.editor.description,
            time_limit_minutes:
                props.editor.timeLimit === ''
                    ? null
                    : Number(props.editor.timeLimit),
            question_count:
                Number(props.editor.questionCount) ||
                props.editor.questions.length,
            shuffle_questions:
                settings.toggles.find(
                    (toggle) => toggle.key === 'shuffle_questions',
                )?.checked ?? false,
            shuffle_options:
                settings.toggles.find(
                    (toggle) => toggle.key === 'shuffle_options',
                )?.checked ?? false,
            single_attempt:
                settings.toggles.find(
                    (toggle) => toggle.key === 'single_attempt',
                )?.checked ?? false,
            results_visibility: settings.toggles.find(
                (toggle) => toggle.key === 'show_results',
            )?.checked
                ? props.editor.settings.results_visibility === 'hidden'
                    ? 'score'
                    : props.editor.settings.results_visibility
                : 'hidden',
            show_answers:
                settings.toggles.find((toggle) => toggle.key === 'show_answers')
                    ?.checked ?? false,
            motivational_message:
                settings.toggles.find(
                    (toggle) => toggle.key === 'motivational_message',
                )?.checked ?? false,
            show_meaning:
                settings.toggles.find((toggle) => toggle.key === 'show_meaning')
                    ?.checked ?? true,
            pass_mark:
                settings.passMark === '' ? null : Number(settings.passMark),
        },
        { preserveScroll: true },
    );
}

function addQuestion(payload: TestQuestionPayload): void {
    if (!props.editor.questionStoreUrl) return;

    router.post(props.editor.questionStoreUrl, payload, {
        preserveScroll: true,
    });
}

function saveQuestion(
    question: TestEditorQuestion,
    payload: TestQuestionPayload,
): void {
    router.patch(question.updateUrl, payload, {
        preserveScroll: true,
    });
}

function removeQuestion(question: TestEditorQuestion): void {
    router.delete(question.deleteUrl, {
        preserveScroll: true,
    });
}

function openQuestionBankItem(item: TestQuestionBankItem): void {
    if (!item.openUrl) return;

    router.visit(item.openUrl, { preserveScroll: true });
}

function createQuestionFromBank(): void {
    if (props.editor.id !== null) {
        changeTab('tests');

        return;
    }

    createTestOpen.value = true;
}

function attachMedia(
    question: TestEditorQuestion,
    kind: string,
    mediaId: number | null,
): void {
    router.patch(
        question.mediaUrl,
        { kind, media_id: mediaId },
        { preserveScroll: true },
    );
}

function generateAi(payload: TestAiGeneratePayload): void {
    if (!props.editor.ai) return;

    router.post(props.editor.ai.generateUrl, payload, {
        preserveScroll: true,
    });
}

function regenerateAi(): void {
    if (!props.editor.ai) return;

    router.post(props.editor.ai.regenerateUrl, {}, { preserveScroll: true });
}

function generateAllAudio(): void {
    if (!props.editor.ai) return;

    router.post(props.editor.ai.generateAudioUrl, {}, { preserveScroll: true });
}

function generateQuestionAudio(question: TestEditorQuestion): void {
    if (!question.audio) return;

    router.post(question.audio.generateUrl, {}, { preserveScroll: true });
}

function releaseQuestion(question: TestEditorQuestion): void {
    if (!question.releaseUrl) return;

    router.post(question.releaseUrl, {}, { preserveScroll: true });
}

/*
 * Poll while an AI or audio job is in flight so its state reaches a
 * terminal value on screen without a manual refresh (PERF-04).
 */
const busyStates = ['pending', 'running'];
const hasWorkInFlight = computed(
    () =>
        busyStates.includes(props.editor.ai?.status ?? '') ||
        props.editor.questions.some((question) =>
            busyStates.includes(question.audio?.status ?? ''),
        ) ||
        props.results.rows.some((row) =>
            (row.answers ?? []).some((answer) =>
                busyStates.includes(answer.aiStatus ?? ''),
            ),
        ),
);

const poll = useIntervalFn(
    () => {
        router.reload({ only: ['editor', 'preview', 'results', 'stats'] });
    },
    4000,
    { immediate: false },
);

watch(
    hasWorkInFlight,
    (busy) => {
        if (busy) {
            poll.resume();
        } else {
            poll.pause();
        }
    },
    { immediate: true },
);

function publishTest(): void {
    if (!props.editor.publishUrl) return;

    router.post(
        props.editor.publishUrl,
        {},
        {
            preserveScroll: true,
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Pre-test & Post-test',
                href: tests(),
            },
        ],
        topbarTaglineSrc: '/decor/tests-topbar-tagline.png',
    },
});
</script>

<template>
    <Head title="Pre-test & Post-test" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">Pre-test &amp; Post-test</h1>

        <PageHeader
            :title="builderOpen ? 'Create / Edit Test' : 'Pre-test & Post-test'"
            :description="
                builderOpen
                    ? 'Build questions, configure test rules and preview the employee experience.'
                    : 'Create, manage and analyse tests to measure employees\' progress.'
            "
            class="mb-1"
        >
            <template #accent>
                <TestsHeaderAccent />
            </template>
        </PageHeader>

        <TestsToolbar
            v-model:active-tab="activeTab"
            :tabs="tabs"
            @update:active-tab="changeTab"
            @create="createTestOpen = true"
        />

        <template v-if="activeTab === 'tests' && !builderOpen">
            <TestsDirectoryStats :stats="stats" />
            <TestsDirectoryTable
                :list="list"
                @open="openTest"
                @create="createTestOpen = true"
            />
        </template>

        <template v-else-if="activeTab === 'tests'">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Link
                    :href="tests.url()"
                    class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex w-fit items-center gap-1.5 rounded-sm text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    data-test="back-to-test-library"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    Back to Test Library
                </Link>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none"
                    @click="createTestOpen = true"
                >
                    Create another test
                </Button>
            </div>

            <div class="tests-layout grid min-w-0 gap-3">
                <TestsListPanel :list="list" @open="openTest" />
                <TestsEditorPanel
                    ref="editorPanel"
                    :editor="editor"
                    @save="saveTest"
                    @add-question="addQuestion"
                    @save-question="saveQuestion"
                    @delete-question="removeQuestion"
                    @publish="publishTest"
                    @generate-ai="generateAi"
                    @regenerate-ai="regenerateAi"
                    @generate-all-audio="generateAllAudio"
                    @generate-audio="generateQuestionAudio"
                    @release-question="releaseQuestion"
                    @attach-image="
                        (question, mediaId) =>
                            attachMedia(question, 'image', mediaId)
                    "
                />
                <TestsSidebarPanel
                    :preview="preview"
                    :media="media"
                    :settings="settings"
                    :results="results"
                    class="lg:col-span-2 xl:col-span-1"
                    @save="saveSettings"
                    @preview="previewOpen = true"
                    @view-results="changeTab('results')"
                    @attach-media="attachMedia"
                />
            </div>
        </template>

        <TestsQuestionBankPanel
            v-else-if="activeTab === 'question-bank'"
            :items="questionBank"
            @create="createQuestionFromBank"
            @open="openQuestionBankItem"
        />
        <TestsResultsPanel
            v-else-if="activeTab === 'results'"
            :results="results"
        />
        <TestsSettingsPanel
            v-else
            :editor="editor"
            :settings="settings"
            @save="saveSettings"
        />

        <CreateTestDialog
            v-model:open="createTestOpen"
            :departments="list.departments"
            @create="createTest"
        />

        <TestPreviewDialog
            v-if="builderOpen && editor.id"
            v-model:open="previewOpen"
            :preview="preview"
            :title="editor.title"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .tests-layout {
        grid-template-columns: 284px minmax(0, 1fr);
    }
}

@media (min-width: 1280px) {
    .tests-layout {
        align-items: start;
        grid-template-columns: 284px minmax(0, 1fr) 332px;
    }
}
</style>
