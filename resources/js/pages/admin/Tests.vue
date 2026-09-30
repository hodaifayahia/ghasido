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
import TestsDeleteDialog from '@/components/tests/TestsDeleteDialog.vue';
import TestsImportDialog from '@/components/tests/TestsImportDialog.vue';
import TestsSidebarPanel from '@/components/tests/TestsSidebarPanel.vue';
import TestsToolbar from '@/components/tests/TestsToolbar.vue';
import { useCan } from '@/composables/useCan';
import { tk } from '@/lib/i18n';
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
    TestResults,
    TestDirectoryMetric,
    TestsList,
} from '@/types';

/*
 * The test library, and the builder for one test. The Tests / Question Bank
 * / Results & Analytics / Settings page tabs and the builder's "Test List"
 * panel were removed at the client's request (2026-09-29); results are read
 * in Reports & Export. The builder itself has Questions / Settings / Preview
 * tabs again (client request 2026-09-29).
 */
type Props = {
    builderOpen: boolean;
    stats: TestDirectoryMetric[];
    list: TestsList;
    editor: TestEditor;
    preview: TestPreview;
    media: TestMedia;
    results: TestResults;
};

const props = defineProps<Props>();

const { can } = useCan();
const canManage = computed(() => can('tests.manage'));

const builderOpen = ref(props.builderOpen);
const createTestOpen = ref(false);
const importOpen = ref(false);

watch(
    () => props.builderOpen,
    (value) => {
        builderOpen.value = value;
    },
);

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
            // "All departments": every department sits it unless it has
            // its own (client request 2026-09-29).
            department_id:
                payload.department === 'all-departments'
                    ? 'all'
                    : Number(payload.department),
            level: payload.level,
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

/*
 * Delete a test (client request 2026-09-29). One that has been sat is
 * refused by the dialog and again by the server (DATA-10).
 */
type DeleteTarget = { id: number; title: string; attemptCount: number };

const deleteTarget = ref<DeleteTarget | null>(null);
const deleting = ref(false);
const deleteOpen = computed({
    get: () => deleteTarget.value !== null,
    set: (open: boolean) => {
        if (!open) deleteTarget.value = null;
    },
});

function askDelete(target: DeleteTarget): void {
    deleteTarget.value = target;
}

function askDeleteOpenTest(): void {
    if (props.editor.id === null) return;

    askDelete({
        id: props.editor.id,
        title: props.editor.title,
        attemptCount: props.editor.attemptCount,
    });
}

function confirmDelete(): void {
    const target = deleteTarget.value;
    if (target === null || target.attemptCount > 0) return;

    router.delete(testActions.destroy.url(target.id), {
        preserveScroll: true,
        onStart: () => {
            deleting.value = true;
        },
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: tk('Dashboard'),
                href: dashboard(),
            },
            {
                title: tk('Pre-test & Post-test'),
                href: tests(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Pre-test & Post-test')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">{{ $t('Pre-test & Post-test') }}</h1>

        <PageHeader
            :title="
                builderOpen
                    ? $t('Create / Edit Test')
                    : $t('Pre-test & Post-test')
            "
            :description="
                builderOpen
                    ? $t(
                          'Build questions, configure test rules and preview the employee experience.',
                      )
                    : $t(
                          'Create, manage and analyse tests to measure employees\' progress.',
                      )
            "
            class="mb-1"
        >
            <template #accent>
                <TestsHeaderAccent />
            </template>
        </PageHeader>

        <TestsToolbar
            @create="createTestOpen = true"
            @import="importOpen = true"
        />

        <template v-if="!builderOpen">
            <TestsDirectoryStats :stats="stats" />
            <TestsDirectoryTable
                :list="list"
                :can-delete="canManage"
                @open="openTest"
                @create="createTestOpen = true"
                @delete="
                    (item) =>
                        askDelete({
                            id: Number(item.id),
                            title: item.title,
                            attemptCount: item.attemptCount,
                        })
                "
            />
        </template>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Link
                    :href="tests.url()"
                    class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex w-fit items-center gap-1.5 rounded-sm text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    data-test="back-to-test-library"
                >
                    <ArrowLeft
                        class="size-3.5 rtl:rotate-180"
                        aria-hidden="true"
                    />
                    {{ $t('Back to Test Library') }}
                </Link>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none"
                    @click="createTestOpen = true"
                >
                    {{ $t('Create another test') }}
                </Button>
            </div>

            <!-- The removed "Test List" panel's column now goes to the
                 editor, so the builder spans the full content width. -->
            <div class="tests-layout grid min-w-0 gap-3">
                <TestsEditorPanel
                    :editor="editor"
                    :activities="preview.activities"
                    :can-delete="canManage"
                    @save="saveTest"
                    @delete="askDeleteOpenTest"
                    @publish="publishTest"
                    @generate-ai="generateAi"
                    @regenerate-ai="regenerateAi"
                    @generate-all-audio="generateAllAudio"
                    @generate-audio="generateQuestionAudio"
                    @release-question="releaseQuestion"
                />
                <TestsSidebarPanel
                    :preview="preview"
                    :media="media"
                    :results="results"
                    @attach-media="attachMedia"
                />
            </div>
        </template>

        <CreateTestDialog
            v-model:open="createTestOpen"
            :departments="list.departments"
            @create="createTest"
        />

        <TestsImportDialog
            v-model:open="importOpen"
            :tests="list.items"
            :current-test-id="editor.id"
            @imported="openTest"
        />

        <TestsDeleteDialog
            v-if="canManage"
            v-model:open="deleteOpen"
            :title="deleteTarget?.title ?? ''"
            :attempt-count="deleteTarget?.attemptCount ?? 0"
            :processing="deleting"
            @confirm="confirmDelete"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1280px) {
    .tests-layout {
        align-items: start;
        grid-template-columns: minmax(0, 1fr) 332px;
    }
}
</style>
