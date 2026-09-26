<script setup lang="ts">
import {
    CirclePlus,
    EllipsisVertical,
    FileText,
    Plus,
    Star,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { AiScenarioFeedbackTemplates, AiScenarioStatus } from '@/types';

type Props = {
    feedback: AiScenarioFeedbackTemplates;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    save: [feedback: AiScenarioFeedbackTemplates];
}>();

const templates = ref([...props.feedback.templates]);
const formOpen = ref(false);
const editingId = ref<string | null>(null);
const name = ref('');
const description = ref('');
const tone = ref('Encouraging');
const status = ref<AiScenarioStatus>('draft');
const criteria = ref<
    AiScenarioFeedbackTemplates['templates'][number]['criteria']
>([]);

const blankCriteria =
    (): AiScenarioFeedbackTemplates['templates'][number]['criteria'] => [
        { label: 'Vocabulary', weight: 20 },
        { label: 'Fluency', weight: 20 },
        { label: 'Politeness', weight: 20 },
        { label: 'Task Completion', weight: 20 },
        { label: 'Grammar', weight: 20 },
    ];

watch(
    () => props.feedback.templates,
    (value) => {
        templates.value = [...value];
    },
    { deep: true },
);

function editTemplate(
    template: AiScenarioFeedbackTemplates['templates'][number],
): void {
    formOpen.value = true;
    editingId.value = template.id;
    name.value = template.name;
    description.value = template.description;
    tone.value = template.tone;
    status.value = template.status;
    criteria.value = template.criteria.map((criterion) => ({ ...criterion }));
}

function newTemplate(): void {
    formOpen.value = true;
    editingId.value = null;
    name.value = '';
    description.value = '';
    tone.value = 'Encouraging';
    status.value = 'draft';
    criteria.value = blankCriteria();
}

function cancelEdit(): void {
    formOpen.value = false;
    editingId.value = null;
    formOpen.value = false;
    name.value = '';
    criteria.value = [];
}

function addCriterion(): void {
    criteria.value.push({ label: '', weight: 0 });
}

function removeCriterion(index: number): void {
    if (criteria.value.length <= 1) return;

    criteria.value.splice(index, 1);
}

function saveTemplate(): void {
    const trimmedName = name.value.trim();
    if (trimmedName === '') return;

    const existing = templates.value.find(
        (item) => item.id === editingId.value,
    );
    const template = {
        id: editingId.value ?? 'template-' + Date.now(),
        name: trimmedName,
        description: description.value.trim(),
        tone: tone.value.trim() || 'Encouraging',
        status: status.value,
        isDefault: existing?.isDefault ?? false,
        criteria: criteria.value
            .map((criterion) => ({
                label: criterion.label.trim(),
                weight: Math.min(
                    100,
                    Math.max(0, Number(criterion.weight) || 0),
                ),
            }))
            .filter((criterion) => criterion.label !== ''),
    };
    const index = templates.value.findIndex((item) => item.id === template.id);
    if (index === -1) {
        templates.value.push(template);
    } else {
        templates.value[index] = template;
    }

    emit('save', {
        sections: props.feedback.sections,
        templates: templates.value,
    });
    formOpen.value = false;
    editingId.value = null;
}

function removeTemplate(id: string): void {
    const next = templates.value.filter((template) => template.id !== id);
    if (next.length === 0) return;
    templates.value = next;
    emit('save', { sections: props.feedback.sections, templates: next });
}

const statusTone: Record<AiScenarioStatus, string> = {
    published: 'bg-success-tint text-success-text',
    draft: 'bg-warning-tint text-warning-text',
};
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    {{ $t('Feedback Templates') }}
                </h2>
                <p class="text-ink-slate mt-0.5 text-[12.5px]">
                    {{
                        $t(
                            'Reusable criteria and weights the AI uses to score each role-play attempt.',
                        )
                    }}
                </p>
            </div>
            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                @click="newTemplate"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('New Template') }}
            </Button>
        </div>

        <div
            v-if="formOpen"
            class="border-brand-200 bg-brand-50/35 mt-4 grid gap-3 rounded-lg border p-3 md:grid-cols-2"
        >
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">{{
                    $t('Template name')
                }}</label>
                <Input
                    v-model="name"
                    :placeholder="$t('e.g. Complaint Coaching')"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">{{
                    $t('Feedback tone')
                }}</label>
                <Input
                    v-model="tone"
                    :placeholder="$t('Encouraging')"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5 md:col-span-2">
                <label class="text-brand-900 text-[12px] font-semibold">{{
                    $t('Description')
                }}</label>
                <Input
                    v-model="description"
                    :placeholder="$t('Explain when this template is used.')"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">{{
                    $t('Status')
                }}</label>
                <select
                    v-model="status"
                    class="border-line text-ink bg-surface h-9 rounded-md border px-2 text-[12px]"
                >
                    <option value="draft">{{ $t('Draft') }}</option>
                    <option value="published">{{ $t('Published') }}</option>
                </select>
            </div>
            <div class="flex items-end justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-9 text-[12px] shadow-none"
                    @click="cancelEdit"
                    >{{ $t('Cancel') }}</Button
                >
                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 h-9 text-[12px] font-semibold text-white"
                    @click="saveTemplate"
                    >{{ $t('Save template') }}</Button
                >
            </div>

            <div
                class="border-line bg-surface mt-1 rounded-md border p-3 md:col-span-2"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-brand-900 text-[12px] font-semibold">
                            {{ $t('Scoring criteria and weights') }}
                        </p>
                        <p class="text-ink-slate mt-0.5 text-[11px]">
                            {{
                                $t(
                                    'These weights are sent to the AI with each evaluation.',
                                )
                            }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line h-8 gap-1 px-2.5 text-[11.5px] shadow-none"
                        @click="addCriterion"
                    >
                        <Plus class="size-3.5" aria-hidden="true" />
                        {{ $t('Add criterion') }}
                    </Button>
                </div>
                <div class="mt-2 grid gap-2">
                    <div
                        v-for="(criterion, index) in criteria"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <Input
                            v-model="criterion.label"
                            :placeholder="$t('e.g. Task completion')"
                            class="border-line h-9 min-w-0 flex-1 text-[12px]"
                        />
                        <div class="relative w-24 shrink-0">
                            <Input
                                v-model.number="criterion.weight"
                                type="number"
                                min="0"
                                max="100"
                                class="border-line h-9 pe-7 text-[12px]"
                                :aria-label="$t('Criterion weight')"
                            />
                            <span
                                class="text-ink-faint pointer-events-none absolute inset-y-0 end-2 flex items-center text-[11px]"
                                >%</span
                            >
                        </div>
                        <button
                            v-if="criteria.length > 1"
                            type="button"
                            class="text-danger hover:bg-danger-tint inline-flex size-8 shrink-0 items-center justify-center rounded-md"
                            :aria-label="
                                criterion.label
                                    ? $t('Remove :item', {
                                          item: criterion.label,
                                      })
                                    : $t('Remove criterion')
                            "
                            @click="removeCriterion(index)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-line bg-app-alt mt-4 rounded-md border px-3 py-3">
            <p class="text-brand-900 text-[12px] font-semibold">
                {{ $t('Every attempt returns these sections') }}
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="section in feedback.sections"
                    :key="section"
                    class="rounded-pill bg-brand-50 text-brand-700 inline-flex min-h-6 items-center px-2.5 text-[11px] font-medium"
                >
                    {{ section }}
                </span>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <article
                v-for="template in templates"
                :key="template.id"
                class="border-line bg-surface rounded-lg border p-3"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="bg-brand-50 text-brand-600 grid size-9 shrink-0 place-items-center rounded-xl"
                        >
                            <FileText class="size-4" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p
                                    class="text-brand-900 text-[13.5px] font-semibold"
                                >
                                    {{ template.name }}
                                </p>
                                <span
                                    v-if="template.isDefault"
                                    class="rounded-pill bg-brand-100 text-brand-700 inline-flex min-h-5 items-center gap-1 px-2 text-[10.5px] font-semibold"
                                >
                                    <Star class="size-3" aria-hidden="true" />
                                    {{ $t('Default') }}
                                </span>
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold',
                                            statusTone[template.status],
                                        )
                                    "
                                >
                                    {{
                                        template.status === 'published'
                                            ? $t('Published')
                                            : $t('Draft')
                                    }}
                                </span>
                            </div>
                            <p class="text-ink-slate mt-0.5 text-[12px]">
                                {{ template.description }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-ink-faint hover:bg-brand-50 inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                        @click="editTemplate(template)"
                        :aria-label="
                            $t('More actions for :name', {
                                name: template.name,
                            })
                        "
                    >
                        <EllipsisVertical class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="text-danger hover:bg-danger-tint inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                        :aria-label="
                            $t('Delete :name', { name: template.name })
                        "
                        @click="removeTemplate(template.id)"
                    >
                        <span class="sr-only">{{ $t('Delete') }}</span>
                        ×
                    </button>
                </div>

                <dl class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="criterion in template.criteria"
                        :key="criterion.label"
                        class="border-line bg-app-alt rounded-md border px-2.5 py-2"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-[11.5px]"
                        >
                            <dt class="text-ink font-medium">
                                {{ criterion.label }}
                            </dt>
                            <dd class="text-brand-700 font-semibold">
                                {{ criterion.weight }}%
                            </dd>
                        </div>
                        <div
                            class="bg-tint-track rounded-pill mt-1.5 h-1.5 w-full"
                        >
                            <div
                                class="bg-brand-600 rounded-pill h-1.5"
                                :style="{ width: `${criterion.weight}%` }"
                            />
                        </div>
                    </div>
                </dl>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <span class="text-ink-slate text-[11.5px]">
                        {{ $t('Tone: :tone', { tone: template.tone }) }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="editTemplate(template)"
                    >
                        {{ $t('Edit Template') }}
                    </Button>
                </div>
            </article>
        </div>
    </section>
</template>
