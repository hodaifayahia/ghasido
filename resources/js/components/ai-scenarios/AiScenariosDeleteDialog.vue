<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { destroy } from '@/routes/ai-scenarios';
import type { AiScenarioLibraryItem } from '@/types';

/**
 * Confirm deleting a role-play scenario from the library (CMS-01). It always
 * leaves the lesson steps that offer it. A scenario learners practised is
 * removed from the library and from learners, and every transcript and
 * score is kept for reports (RP-11, DATA-10); the dialog says which.
 */
type Props = {
    scenario: AiScenarioLibraryItem | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const processing = ref(false);

const attempts = computed(() => props.scenario?.learnerAttempts ?? 0);
const lessonSteps = computed(() => props.scenario?.lessonSteps ?? 0);

function confirm(): void {
    if (props.scenario === null || processing.value) {
        return;
    }

    router.delete(destroy.url(Number(props.scenario.id)), {
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
                            title: scenario?.title ?? '',
                        })
                    }}
                </DialogTitle>
                <DialogDescription
                    v-if="attempts > 0"
                    class="text-ink-slate text-[13px] leading-5"
                >
                    {{
                        $tc(
                            'Learners already made :count role-play attempt on this scenario.|Learners already made :count role-play attempts on this scenario.',
                            attempts,
                        )
                    }}
                    {{
                        $t(
                            'To protect research data, the scenario will be removed from the library and hidden from learners, but every transcript and score is kept for reports.',
                        )
                    }}
                </DialogDescription>
                <DialogDescription
                    v-else
                    class="text-ink-slate text-[13px] leading-5"
                >
                    {{
                        $t(
                            'No learner has practised it yet, so the scenario and your preview runs will be permanently deleted. This cannot be undone.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="lessonSteps > 0"
                class="bg-warning-tint text-warning-text rounded-md px-3 py-2 text-[12px] leading-5"
            >
                {{
                    $tc(
                        'It will also be taken out of :count lesson step that offers it.|It will also be taken out of :count lesson steps that offer it.',
                        lessonSteps,
                    )
                }}
            </p>

            <p
                v-if="attempts > 0"
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
                    data-test="confirm-delete-scenario-button"
                    @click="confirm"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{
                        attempts > 0
                            ? $t('Remove scenario')
                            : $t('Delete scenario')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
