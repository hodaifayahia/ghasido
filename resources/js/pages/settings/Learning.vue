<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tk } from '@/lib/i18n';
import { edit, update } from '@/routes/learning-settings';

/*
 * Settings → Learning (client request 2026-09-30; Super Admin only): the
 * Pre-test score at or above which a learner is offered the next level.
 * The learner still chooses to move up or stay (ADM-02).
 */
type Props = {
    levelUpFrom: number;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('Learning'), href: edit() }],
    },
});

const percent = ref(String(props.levelUpFrom));
const errors = ref<Record<string, string>>({});
const saving = ref(false);

watch(
    () => props.levelUpFrom,
    (value) => (percent.value = String(value)),
);

function save(): void {
    router.patch(
        update.url(),
        { level_up_from: Number(percent.value) },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onError: (bag) => (errors.value = bag),
            onSuccess: () => (errors.value = {}),
        },
    );
}
</script>

<template>
    <Head :title="$t('Learning')" />
    <h1 class="sr-only">{{ $t('Learning') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Learning levels')"
            :description="
                $t(
                    'Learners choose Beginner, Intermediate or Advanced. A strong Pre-test offers them the next level.',
                )
            "
        />

        <form
            class="border-line bg-surface shadow-card grid gap-4 rounded-lg border p-5"
            @submit.prevent="save"
        >
            <div class="flex items-start gap-3">
                <span
                    class="bg-success-tint text-success grid size-10 shrink-0 place-items-center rounded-xl"
                >
                    <ArrowUpCircle class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        class="font-heading text-brand-900 text-base font-semibold"
                    >
                        {{ $t('Move-up threshold') }}
                    </h2>
                    <p class="text-ink-slate text-sm leading-6">
                        {{
                            $t(
                                'A Pre-test score at or above this suggests the next level. Below it, the learner starts the lessons of the level they chose. Advanced is the last level.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="level-up-from">{{ $t('Score (%)') }}</Label>
                <div class="flex items-center gap-2">
                    <Input
                        id="level-up-from"
                        v-model="percent"
                        type="number"
                        min="1"
                        max="100"
                        required
                        class="h-11 w-28"
                        data-test="level-up-from-input"
                    />
                    <span class="text-ink-slate text-sm">%</span>
                </div>
                <InputError :message="errors.level_up_from" />
            </div>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="saving"
                    data-test="save-learning-settings-button"
                >
                    {{ $t('Save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
