<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, GraduationCap } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { cn } from '@/lib/utils';
import { update } from '@/routes/learn/training-department';

/*
 * The manager's department chooser (client decision 2026-09-23). A manager
 * learns as an employee but has no department of their own, so they pick one
 * to train in before the learner routes open. No client mockup covers this
 * page; it is built from tokens and the approved chrome (AGENTS.md §0.2).
 */
type TrainingDepartmentOption = { id: number; name: string };

type Props = {
    departments: TrainingDepartmentOption[];
    currentDepartmentId: number | null;
};

const props = defineProps<Props>();

const submitting = ref<number | null>(null);

function choose(id: number): void {
    if (submitting.value !== null) {
        return;
    }

    submitting.value = id;
    router.post(
        update().url,
        { department_id: id },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Choose a department')" />
    <h1 class="sr-only">{{ $t('Choose a department to train in') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('Choose a department to train in')"
            :description="
                $t(
                    'Pick the department whose training you want to work through. You can switch department at any time.',
                )
            "
        />

        <div
            v-if="departments.length > 0"
            class="grid max-w-4xl gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
            <button
                v-for="department in departments"
                :key="department.id"
                type="button"
                :disabled="submitting !== null"
                :class="
                    cn(
                        'border-line bg-surface shadow-card ease-brand hover:shadow-hover focus-visible:ring-brand-600/40 flex items-center gap-3 rounded-lg border p-5 text-start transition-[transform,box-shadow] duration-150 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-70',
                        department.id === currentDepartmentId &&
                            'border-brand-600 ring-brand-600/15 ring-3',
                    )
                "
                :data-test="`training-department-${department.id}`"
                @click="choose(department.id)"
            >
                <span
                    class="bg-brand-50 text-brand-600 grid size-11 shrink-0 place-items-center rounded-xl"
                >
                    <GraduationCap class="size-5" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span
                        class="font-heading text-brand-800 block truncate text-base font-semibold"
                    >
                        {{ department.name }}
                    </span>
                    <span class="text-ink-slate text-[13px]">
                        {{
                            department.id === currentDepartmentId
                                ? $t('Currently training here')
                                : $t('Start training')
                        }}
                    </span>
                </span>
                <Check
                    v-if="department.id === currentDepartmentId"
                    class="text-brand-600 size-5 shrink-0"
                    aria-hidden="true"
                />
            </button>
        </div>

        <div
            v-else
            class="border-line bg-surface shadow-card max-w-2xl rounded-lg border p-6"
        >
            <p class="text-ink text-sm leading-6">
                {{
                    $t(
                        "There is no published training for your hotel's departments yet. Your platform administrator will let you know when a course is ready.",
                    )
                }}
            </p>
        </div>
    </div>
</template>
