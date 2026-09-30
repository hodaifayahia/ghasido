<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ClipboardCheck, SquarePen } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import LessonsActivityList from '@/components/lessons/activities/LessonsActivityList.vue';
import { Button } from '@/components/ui/button';
import { store as storeBlock } from '@/routes/blocks';
import type { LessonBlockRow, LessonsImageLibrary } from '@/types';

/**
 * Quiz / Practice tab (client request 2026-09-29): every quiz question and
 * practice activity of the lesson, added, edited, reordered and deleted
 * right here. "Add Quiz Question" / "Add Practice Activity" use the
 * lesson's first Quiz / Practice block, and add that block first when the
 * lesson has none yet, then open the exercise-type chooser. Every edit to a
 * question writes a new version (DATA-11).
 */
type Props = {
    lessonId: number | null;
    blocks: LessonBlockRow[];
    library: LessonsImageLibrary;
    readOnly: boolean;
};

type Kind = 'quiz' | 'practice';

const props = defineProps<Props>();

const lists = ref<Record<number, InstanceType<typeof LessonsActivityList>>>({});
const adding = ref<Kind | null>(null);

const groups = computed(() =>
    props.blocks.filter(
        (block) => block.type === 'quiz' || block.type === 'practice',
    ),
);
const total = computed(() =>
    groups.value.reduce((sum, block) => sum + block.activities.length, 0),
);

function setList(id: number, list: unknown): void {
    if (list) {
        lists.value[id] = list as InstanceType<typeof LessonsActivityList>;
    } else {
        delete lists.value[id];
    }
}

async function openChooser(kind: Kind): Promise<boolean> {
    const block = props.blocks.find((row) => row.type === kind);

    if (!block) {
        return false;
    }

    await nextTick();
    lists.value[block.id]?.add();

    return true;
}

async function addTo(kind: Kind): Promise<void> {
    if (props.readOnly || props.lessonId === null || adding.value !== null) {
        return;
    }

    if (await openChooser(kind)) {
        return;
    }

    // No block of that kind yet: add it at the end of the lesson, then
    // open its chooser once the page has the new block.
    adding.value = kind;
    router.post(
        storeBlock.url(props.lessonId),
        { type: kind },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => void openChooser(kind),
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="text-ink-slate min-w-0 flex-1 basis-64 text-[12px]">
                {{
                    $tc(
                        '{0} No question or activity in this lesson yet.|{1} One question or activity in this lesson.|[2,*] :count questions and activities in this lesson.',
                        total,
                    )
                }}
                {{
                    $t(
                        'Editing a question keeps the answers employees already gave.',
                    )
                }}
            </p>
            <div v-if="!readOnly" class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 gap-1.5 rounded-md px-3.5 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    :disabled="lessonId === null || adding !== null"
                    data-test="quiz-tab-add-question-button"
                    @click="addTo('quiz')"
                >
                    <ClipboardCheck class="size-4" aria-hidden="true" />
                    {{ $t('Add Quiz Question') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-3.5 text-[12.5px] font-semibold shadow-none"
                    :disabled="lessonId === null || adding !== null"
                    data-test="quiz-tab-add-activity-button"
                    @click="addTo('practice')"
                >
                    <SquarePen class="size-4" aria-hidden="true" />
                    {{ $t('Add Practice Activity') }}
                </Button>
            </div>
        </div>

        <p
            v-if="groups.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate rounded-md border border-dashed px-4 py-8 text-center text-[13px]"
        >
            {{
                readOnly
                    ? $t('This lesson has no quiz or practice yet.')
                    : $t(
                          'No quiz or practice yet. Use the buttons above: the step is added to the lesson and you choose the type of question.',
                      )
            }}
        </p>

        <section
            v-for="block in groups"
            :key="block.id"
            class="border-line bg-surface grid gap-3 rounded-lg border p-4"
            :aria-labelledby="`quiz-tab-block-${block.id}`"
        >
            <h3
                :id="`quiz-tab-block-${block.id}`"
                class="font-heading text-brand-900 flex min-w-0 items-center gap-2 text-[14px] font-semibold"
            >
                <component
                    :is="block.type === 'quiz' ? ClipboardCheck : SquarePen"
                    class="text-brand-600 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="truncate">{{ block.title ?? block.label }}</span>
                <span class="text-ink-faint shrink-0 text-[11.5px] font-medium">
                    {{ $t('Step :number', { number: block.position }) }}
                </span>
            </h3>
            <LessonsActivityList
                :ref="(list) => setList(block.id, list)"
                :block="block"
                :mode="block.type === 'quiz' ? 'quiz' : 'practice'"
                :library="library"
                :read-only="readOnly"
            />
        </section>
    </div>
</template>
