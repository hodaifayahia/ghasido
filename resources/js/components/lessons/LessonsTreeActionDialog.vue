<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import courseRoutes from '@/routes/courses';
import lessonRoutes from '@/routes/lessons';
import unitRoutes from '@/routes/units';
import type { LessonsTreeTarget } from '@/types';

/**
 * Rename or delete a course, unit or lesson from the Course Structure tree
 * (client request 2026-10-05, CMS-01). The server decides what a delete
 * does: content no learner touched is deleted; anything holding learner
 * answers is removed from the library and from learners while every answer
 * is kept for reports (DATA-10).
 */
type Props = {
    target: LessonsTreeTarget | null;
    mode: 'rename' | 'delete';
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const title = ref('');
const error = ref('');
const processing = ref(false);

watch(
    () => [open.value, props.target] as const,
    ([isOpen, target]) => {
        if (isOpen && target !== null) {
            title.value = target.name;
            error.value = '';
        }
    },
    { immediate: true },
);

const maxLength = computed(() => (props.target?.kind === 'lesson' ? 100 : 120));

const heading = computed(() => {
    const name = props.target?.name ?? '';

    if (props.mode === 'rename') {
        return {
            course: t('Rename course'),
            unit: t('Rename unit'),
            lesson: t('Rename lesson'),
        }[props.target?.kind ?? 'lesson'];
    }

    return t('Delete “:title”?', { title: name });
});

const description = computed(() => {
    if (props.mode === 'rename') {
        return t('The new title is saved at once. Learners see it too.');
    }

    return {
        course: t(
            'The course, its units and its lessons will be deleted. Lessons learners already used are removed from the library instead, and their answers are kept for reports.',
        ),
        unit: t(
            'The unit and its lessons will be deleted. Lessons learners already used are removed from the library instead, and their answers are kept for reports.',
        ),
        lesson: t(
            'The lesson and its steps will be deleted. If learners already used it, it is removed from the library instead, and their answers are kept for reports.',
        ),
    }[props.target?.kind ?? 'lesson'];
});

function finish(): void {
    processing.value = false;
}

function submitRename(): void {
    const target = props.target;
    const value = title.value.trim();

    if (target === null || processing.value) {
        return;
    }

    if (value === '') {
        error.value = t('Enter a title.');

        return;
    }

    if (value === target.name) {
        open.value = false;

        return;
    }

    const url = {
        course: () => courseRoutes.update.url(target.id),
        unit: () => unitRoutes.update.url(target.id),
        lesson: () => lessonRoutes.update.url(target.id),
    }[target.kind]();

    router.patch(
        url,
        target.kind === 'lesson'
            ? { title: value, _notify: true }
            : { title: value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                processing.value = true;
            },
            onSuccess: () => {
                open.value = false;
            },
            onError: (errors) => {
                error.value = errors.title ?? t('Could not save');
            },
            onFinish: finish,
        },
    );
}

function submitDelete(): void {
    const target = props.target;

    if (target === null || processing.value) {
        return;
    }

    const url = {
        course: () => courseRoutes.destroy.url(target.id),
        unit: () => unitRoutes.destroy.url(target.id),
        lesson: () =>
            lessonRoutes.destroy.url(target.id, {
                query: { from_tree: '1' },
            }),
    }[target.kind]();

    router.delete(url, {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: finish,
    });
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="heading"
        :description="description"
    >
        <form
            v-if="mode === 'rename'"
            class="mt-4 grid gap-4"
            @submit.prevent="submitRename"
        >
            <div class="grid gap-1.5">
                <label
                    for="tree-rename-title"
                    class="text-brand-900 text-[12px] font-semibold"
                >
                    {{ $t('Title') }}
                </label>
                <Input
                    id="tree-rename-title"
                    v-model="title"
                    :maxlength="maxLength"
                    autocomplete="off"
                    data-test="tree-rename-input"
                    class="border-line text-ink bg-surface h-11 rounded-md px-3 text-[14px] shadow-none md:h-10 md:text-[13px]"
                />
                <InputError :message="error" />
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="tree-rename-save"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:h-10"
                >
                    {{ $t('Save') }}
                </Button>
            </div>
        </form>

        <div v-else class="mt-4 grid gap-4">
            <p
                class="bg-success-tint text-success-text flex items-start gap-2 rounded-md px-3 py-2 text-[12px] leading-5"
            >
                <ShieldCheck
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ $t('Learner answers are never deleted.') }}
            </p>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    data-test="tree-delete-confirm"
                    class="border-danger text-danger hover:bg-danger-tint hover:text-danger-text bg-surface h-11 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                    @click="submitDelete"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
