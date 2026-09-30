<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldCheck, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/lessons';
import type { LessonDirectoryRow } from '@/types';

/**
 * Confirm deleting a lesson from the directory (CMS-01). The server decides
 * what happens: a lesson no learner touched is deleted; one with learner
 * progress or answers is removed from the library and from learners while
 * every answer is kept for reports (DATA-10). The dialog says which, up
 * front, from the row's learner record count.
 */
type Props = {
    lesson: LessonDirectoryRow | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const processing = ref(false);

function confirm(): void {
    if (props.lesson === null || processing.value) {
        return;
    }

    router.delete(destroy.url(props.lesson.id), {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="bg-surface border-line shadow-pop rounded-lg p-6 sm:max-w-[460px]"
        >
            <DialogHeader class="pe-8 text-start">
                <DialogTitle
                    class="font-heading text-brand-900 text-[17px] font-semibold"
                >
                    {{
                        $t('Delete “:title”?', {
                            title: lesson?.title ?? '',
                        })
                    }}
                </DialogTitle>
                <DialogDescription
                    v-if="lesson && lesson.learnerRecords > 0"
                    class="text-ink-slate text-[13px] leading-5"
                >
                    {{
                        $tc(
                            'Learners already have :count record in this lesson (progress or answers).|Learners already have :count records in this lesson (progress or answers).',
                            lesson.learnerRecords,
                        )
                    }}
                    {{
                        $t(
                            'To protect research data, the lesson will be removed from the library and hidden from learners, but every answer is kept for reports.',
                        )
                    }}
                </DialogDescription>
                <DialogDescription
                    v-else
                    class="text-ink-slate text-[13px] leading-5"
                >
                    {{
                        $tc(
                            'The lesson and its :count step will be permanently deleted.|The lesson and its :count steps will be permanently deleted.',
                            lesson?.steps ?? 0,
                        )
                    }}
                    {{
                        $t(
                            'No learner has started it yet. Activities used only here are deleted too. This cannot be undone.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="lesson && lesson.learnerRecords > 0"
                class="bg-success-tint text-success-text flex items-start gap-2 rounded-md px-3 py-2 text-[12px] leading-5"
            >
                <ShieldCheck
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ $t('Learner answers are never deleted.') }}
            </p>

            <DialogFooter class="gap-2 sm:justify-end">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="outline"
                    class="border-danger text-danger hover:bg-danger-tint hover:text-danger-text bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    :disabled="processing"
                    data-test="confirm-delete-lesson-button"
                    @click="confirm"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{
                        lesson && lesson.learnerRecords > 0
                            ? $t('Remove lesson')
                            : $t('Delete lesson')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
