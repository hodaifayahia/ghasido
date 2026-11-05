<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Eye,
    EyeOff,
    Layers,
    PencilLine,
    Plus,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EssentialsLessonRow from '@/components/essentials-studio/EssentialsLessonRow.vue';
import { topicTone } from '@/components/essentials-studio/essentialsStudioTones';
import EssentialsIconChip from '@/components/learning/essentials/EssentialsIconChip.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { EssentialsStudioLesson, EssentialsStudioTopic } from '@/types';

/*
 * The open English Essentials topic (client request 2026-11-05): who sees
 * it and whether employees see it at all, then its lessons as one ordered
 * list with every action on the row. Writes go through the existing
 * course and lesson routes (show/hide, publish, delete) and the studio's
 * order route; the server authorizes each one (SEC-01).
 */
type Props = {
    topic: EssentialsStudioTopic;
    lessons: EssentialsStudioLesson[];
    manage: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    edit: [];
    addLesson: [];
}>();

const busy = ref(false);
const removing = ref<
    | { kind: 'lesson'; lesson: EssentialsStudioLesson }
    | { kind: 'topic' }
    | null
>(null);
const confirmOpen = computed({
    get: () => removing.value !== null,
    set: (value: boolean) => {
        if (!value) {
            removing.value = null;
        }
    },
});

const options = {
    preserveScroll: true,
    onStart: () => {
        busy.value = true;
    },
    onFinish: () => {
        busy.value = false;
    },
};

function toggleTopic(): void {
    router.patch(
        props.topic.updateUrl,
        { status: props.topic.visible ? 'draft' : 'published' },
        options,
    );
}

function toggleLesson(lesson: EssentialsStudioLesson): void {
    router.post(lesson.publishUrl, {}, options);
}

function move(index: number, direction: -1 | 1): void {
    const order = props.lessons.map((lesson) => lesson.id);
    const target = index + direction;

    if (target < 0 || target >= order.length) {
        return;
    }

    [order[index], order[target]] = [order[target], order[index]];
    router.put(props.topic.reorderUrl, { order }, options);
}

function confirmRemove(): void {
    const target = removing.value;

    if (target === null) {
        return;
    }

    const url =
        target.kind === 'lesson'
            ? target.lesson.destroyUrl
            : props.topic.destroyUrl;

    router.delete(url, {
        ...options,
        data: { from: 'essentials-studio' },
        onSuccess: () => {
            removing.value = null;
        },
    });
}

const publishedCount = computed(
    () => props.lessons.filter((lesson) => lesson.published).length,
);
</script>

<template>
    <section
        class="border-line bg-surface shadow-card grid min-w-0 content-start gap-5 rounded-lg border p-4 md:p-5"
        aria-labelledby="essentials-open-topic"
        data-test="essentials-topic-panel"
    >
        <header
            class="grid gap-4 min-[1500px]:grid-cols-[minmax(0,1fr)_auto] min-[1500px]:items-start"
        >
            <div class="flex min-w-0 items-start gap-4">
                <EssentialsIconChip
                    :icon="topic.icon"
                    :class="
                        cn(
                            'size-14 rounded-2xl text-[18px]',
                            topicTone[topic.tone],
                        )
                    "
                />
                <div class="min-w-0">
                    <h2
                        id="essentials-open-topic"
                        class="font-heading text-brand-900 text-[20px] leading-7 font-semibold"
                    >
                        {{ topic.title }}
                    </h2>
                    <p
                        v-if="topic.description"
                        class="text-ink-slate mt-0.5 text-[13px]"
                    >
                        {{ topic.description }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex items-center gap-1 px-2.5 py-1 text-[11.5px] font-semibold',
                                    topic.visible
                                        ? 'bg-success-tint text-success-text'
                                        : 'bg-warning-tint text-warning-text',
                                )
                            "
                        >
                            <component
                                :is="topic.visible ? Eye : EyeOff"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{
                                topic.visible
                                    ? $t('Visible to employees')
                                    : $t('Hidden from employees')
                            }}
                        </span>
                        <span
                            class="rounded-pill bg-brand-50 text-brand-700 inline-flex items-center gap-1 px-2.5 py-1 text-[11.5px] font-semibold"
                        >
                            <Layers class="size-3.5" aria-hidden="true" />
                            {{
                                topic.audience.length === 0
                                    ? $t('Every department')
                                    : topic.audienceLabels.join(', ')
                            }}
                        </span>
                        <span
                            class="rounded-pill bg-tint-header text-ink-slate inline-flex items-center gap-1 px-2.5 py-1 text-[11.5px] font-semibold"
                        >
                            <Users class="size-3.5" aria-hidden="true" />
                            {{
                                $tc(
                                    ':count employee practised|:count employees practised',
                                    topic.learners,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="manage"
                class="flex flex-wrap gap-2 min-[1500px]:justify-end"
            >
                <Button
                    type="button"
                    :class="
                        cn(
                            'h-11 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold active:scale-[.97] md:h-9',
                            topic.visible
                                ? 'border-line text-ink-slate hover:bg-tint-header border bg-transparent shadow-none'
                                : 'bg-brand-600 hover:bg-brand-700 shadow-btn text-white',
                        )
                    "
                    :disabled="busy"
                    data-test="essentials-topic-visibility"
                    @click="toggleTopic"
                >
                    <component
                        :is="topic.visible ? EyeOff : Eye"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{
                        topic.visible
                            ? $t('Hide from employees')
                            : $t('Show to employees')
                    }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 h-11 gap-1.5 rounded-md px-3 text-[12.5px] font-semibold md:h-9"
                    data-test="essentials-topic-edit"
                    @click="emit('edit')"
                >
                    <PencilLine class="size-4" aria-hidden="true" />
                    {{ $t('Edit topic') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-danger/40 text-danger-text hover:bg-danger-tint h-11 rounded-md px-3 md:h-9"
                    :aria-label="$t('Delete topic')"
                    :title="$t('Delete topic')"
                    @click="removing = { kind: 'topic' }"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </div>
        </header>

        <p
            v-if="!topic.visible && publishedCount > 0"
            class="bg-warning-tint text-warning-text rounded-md px-3 py-2 text-[12.5px]"
        >
            {{
                $t(
                    'Employees cannot see this topic yet. Show it when its lessons are ready.',
                )
            }}
        </p>

        <div class="grid gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3
                    class="font-heading text-brand-900 text-[15px] font-semibold"
                >
                    {{ $t('Lessons') }}
                    <span class="text-ink-slate ms-1 text-[12.5px] font-normal">
                        {{
                            $t(':published of :total published', {
                                published: publishedCount,
                                total: lessons.length,
                            })
                        }}
                    </span>
                </h3>
                <Button
                    v-if="manage"
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:h-9"
                    data-test="essentials-add-lesson"
                    @click="emit('addLesson')"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ $t('Add a lesson') }}
                </Button>
            </div>

            <ol v-if="lessons.length > 0" class="grid list-none gap-2">
                <EssentialsLessonRow
                    v-for="(lesson, index) in lessons"
                    :key="lesson.id"
                    :lesson="lesson"
                    :number="index + 1"
                    :first="index === 0"
                    :last="index === lessons.length - 1"
                    :manage="manage"
                    :busy="busy"
                    @move="move(index, $event)"
                    @toggle="toggleLesson(lesson)"
                    @remove="removing = { kind: 'lesson', lesson }"
                />
            </ol>

            <div
                v-else
                class="border-line-strong bg-app grid justify-items-center gap-2 rounded-lg border border-dashed px-4 py-8 text-center"
            >
                <p class="text-ink-night text-[14px] font-semibold">
                    {{ $t('No lessons in this topic yet') }}
                </p>
                <p class="text-ink-slate max-w-md text-[12.5px]">
                    {{
                        $t(
                            'Start from a beginner shape — words, phrases, a say-it-aloud drill or a short conversation — and fill it in.',
                        )
                    }}
                </p>
                <Button
                    v-if="manage"
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn mt-1 h-11 gap-1.5 rounded-md px-4 font-semibold text-white"
                    @click="emit('addLesson')"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ $t('Add the first lesson') }}
                </Button>
            </div>
        </div>

        <LessonsModal
            v-model:open="confirmOpen"
            :title="
                removing?.kind === 'lesson'
                    ? $t('Delete this lesson?')
                    : $t('Delete this topic?')
            "
            :description="
                removing?.kind === 'lesson'
                    ? removing.lesson.title
                    : topic.title
            "
        >
            <p class="text-ink-slate mt-3 text-[13px] leading-6">
                {{
                    $t(
                        'If employees already used it, it is removed from the list but their answers are kept for reports. Otherwise it is deleted for good.',
                    )
                }}
            </p>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-11 rounded-md px-4 md:h-10"
                    @click="removing = null"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-danger text-danger-text hover:bg-danger-tint h-11 gap-1.5 rounded-md px-4 font-semibold md:h-10"
                    :disabled="busy"
                    data-test="essentials-confirm-delete"
                    @click="confirmRemove"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
            </div>
        </LessonsModal>
    </section>
</template>
