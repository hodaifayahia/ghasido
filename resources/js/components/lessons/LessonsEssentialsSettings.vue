<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/composables/useCan';
import type { LessonsEssentialsPanel } from '@/types';

/*
 * English Essentials topic settings (client request 2026-10-18): which
 * departments see the topic — every department, or the ones ticked — and
 * the icon it shows on the learner's Essentials list. Saved on the course,
 * so every lesson of the topic follows it.
 */
const page = usePage<{ essentials?: LessonsEssentialsPanel }>();
const panel = computed(() => page.props.essentials ?? null);
const topic = computed(() => panel.value?.topic ?? null);

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const everyone = ref(true);
const chosen = ref<number[]>([]);
const icon = ref('sprout');
const saving = ref(false);

watch(
    topic,
    (value) => {
        everyone.value = (value?.audience.length ?? 0) === 0;
        chosen.value = [...(value?.audience ?? [])];
        icon.value = value?.icon ?? 'sprout';
    },
    { immediate: true },
);

function toggle(id: number, on: boolean): void {
    chosen.value = on
        ? [...new Set([...chosen.value, id])]
        : chosen.value.filter((value) => value !== id);
}

function onIcon(value: AcceptableValue): void {
    if (typeof value === 'string') {
        icon.value = value;
    }
}

const invalid = computed(() => !everyone.value && chosen.value.length === 0);

function save(): void {
    if (topic.value === null || invalid.value) {
        return;
    }

    saving.value = true;
    router.patch(
        topic.value.updateUrl,
        {
            audience_department_ids: everyone.value ? [] : chosen.value,
            icon: icon.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <section
        v-if="panel && topic"
        class="border-line bg-surface grid gap-3 rounded-md border px-4 py-3"
        aria-labelledby="essentials-settings-title"
        data-test="essentials-settings"
    >
        <div>
            <h3
                id="essentials-settings-title"
                class="font-heading text-ink text-[13.5px] font-semibold"
            >
                {{ $t('English Essentials topic') }}
            </h3>
            <p class="text-ink-faint text-[11.5px]">
                {{
                    $t(
                        'Applies to every lesson of this topic. Its progress never counts toward the Post-test or the certificate.',
                    )
                }}
            </p>
        </div>

        <fieldset class="grid gap-2" :disabled="!manage">
            <legend class="text-ink-slate mb-1 text-[12.5px]">
                {{ $t('Who sees it') }}
            </legend>
            <label class="text-ink flex items-center gap-2 text-[13px]">
                <Checkbox
                    :model-value="everyone"
                    data-test="essentials-all-departments"
                    @update:model-value="everyone = $event === true"
                />
                {{ $t('All departments') }}
            </label>
            <div
                v-if="!everyone"
                class="grid gap-2 ps-6 sm:grid-cols-2"
                data-test="essentials-department-list"
            >
                <label
                    v-for="department in panel.departments"
                    :key="department.value"
                    class="text-ink flex items-center gap-2 text-[13px]"
                >
                    <Checkbox
                        :model-value="chosen.includes(Number(department.value))"
                        @update:model-value="
                            toggle(Number(department.value), $event === true)
                        "
                    />
                    {{ department.label }}
                </label>
            </div>
            <p v-if="invalid" class="text-danger-text text-[12px]" role="alert">
                {{ $t('Tick at least one department, or All departments.') }}
            </p>
        </fieldset>

        <div class="grid max-w-72 gap-1.5">
            <span class="text-ink-slate text-[12.5px]">{{
                $t('Icon on the learner list')
            }}</span>
            <Select
                :model-value="icon"
                :disabled="!manage"
                @update:model-value="onIcon"
            >
                <SelectTrigger
                    class="border-line h-10 rounded-sm text-[13px]"
                    data-test="essentials-icon"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent class="border-line shadow-pop">
                    <SelectItem
                        v-for="option in panel.icons"
                        :key="option.value"
                        :value="option.value"
                        class="text-[13px]"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="manage" class="flex justify-end">
            <Button
                type="button"
                :disabled="saving || invalid"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                data-test="save-essentials-settings-button"
                @click="save"
            >
                {{ saving ? $t('Saving…') : $t('Save topic settings') }}
            </Button>
        </div>
    </section>
</template>
