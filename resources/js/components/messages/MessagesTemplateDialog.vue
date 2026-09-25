<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { preview, store, update } from '@/routes/messages-reminders/templates';
import type { MessageTemplate, MessageTemplatePreview } from '@/types';

type Props = {
    /** The template being edited, or null to add one. */
    template: MessageTemplate | null;
    /** The placeholders a template may use, without braces (REM-04). */
    variables: string[];
    /** Read-only: preview only, no form. */
    readonly?: boolean;
};

const props = withDefaults(defineProps<Props>(), { readonly: false });

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const editing = computed(() => props.template !== null);

// The Wayfinder form variant carries method spoofing for PATCH itself, so
// the form never builds a `_method` field by hand (AGENTS.md §5).
const action = computed(() =>
    props.template === null ? store.form() : update.form(props.template.id),
);

// Controlled fields: the variable chips insert into them and the preview
// renders them before anything is saved.
const name = ref('');
const subject = ref('');
const body = ref('');
const audienceLabel = ref('');
const triggerLabel = ref('');
const isActive = ref(true);

const previewResult = ref<MessageTemplatePreview | null>(null);
const previewBusy = ref(false);
const previewError = ref('');

function seed(): void {
    name.value = props.template?.name ?? '';
    subject.value = props.template?.subject ?? '';
    body.value = props.template?.body ?? '';
    audienceLabel.value = props.template?.audience ?? '';
    triggerLabel.value = props.template?.trigger ?? '';
    isActive.value = props.template?.isActive ?? true;
    previewResult.value = null;
    previewError.value = '';
}

watch(open, (isOpen) => {
    if (isOpen) {
        seed();

        if (props.readonly) {
            void loadPreview();
        }
    }
});

const subjectInput = ref<{ $el?: HTMLInputElement } | HTMLInputElement | null>(
    null,
);
const bodyInput = ref<HTMLTextAreaElement | null>(null);
const lastField = ref<'subject' | 'body'>('body');

function subjectElement(): HTMLInputElement | null {
    const target = subjectInput.value;

    if (target === null) {
        return null;
    }

    return target instanceof HTMLInputElement ? target : (target.$el ?? null);
}

/**
 * Insert `{{variable}}` where the cursor last was, in the field last used.
 */
function placeholderToken(variable: string): string {
    return `{{${variable}}}`;
}

function insertVariable(variable: string): void {
    const token = placeholderToken(variable);
    const element =
        lastField.value === 'subject' ? subjectElement() : bodyInput.value;
    const model = lastField.value === 'subject' ? subject : body;

    if (element === null) {
        model.value = `${model.value}${token}`;
        return;
    }

    const start = element.selectionStart ?? model.value.length;
    const end = element.selectionEnd ?? start;

    model.value = `${model.value.slice(0, start)}${token}${model.value.slice(end)}`;

    void Promise.resolve().then(() => {
        element.focus();
        element.setSelectionRange(start + token.length, start + token.length);
    });
}

async function loadPreview(): Promise<void> {
    previewBusy.value = true;
    previewError.value = '';

    try {
        const response = await fetch(
            preview.url({
                query: { subject: subject.value, body: body.value },
            }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(`Preview failed (${response.status}).`);
        }

        previewResult.value = (await response.json()) as MessageTemplatePreview;
    } catch (error) {
        previewError.value =
            error instanceof Error
                ? error.message
                : 'The preview could not be loaded.';
    } finally {
        previewBusy.value = false;
    }
}

const fieldClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';

function onSuccess(): void {
    open.value = false;
    emit('saved');
}

const title = computed(() => {
    if (props.readonly) {
        return `Preview ${props.template?.name ?? 'template'}`;
    }

    return editing.value
        ? `Edit ${props.template?.name ?? 'template'}`
        : 'Add Template';
});
</script>

<template>
    <MessagesModal
        v-model:open="open"
        :title="title"
        :description="
            readonly
                ? 'How this template reads for a sample employee, every variable filled in.'
                : 'Write the subject and body once; the variables are filled in for each employee when a reminder is sent.'
        "
        class="sm:max-w-[640px]"
    >
        <div v-if="readonly" class="mt-2 grid gap-3">
            <p v-if="previewBusy" class="text-ink-slate text-[13px]">
                Rendering the preview…
            </p>
            <p v-else-if="previewError" class="text-danger-text text-[13px]">
                {{ previewError }}
            </p>
            <template v-else-if="previewResult">
                <p class="text-ink-slate text-[12px]">
                    Sample employee:
                    <span class="text-brand-900 font-semibold">
                        {{ previewResult.sample?.name ?? 'none yet' }}
                    </span>
                    <template v-if="previewResult.sample?.hotel">
                        · {{ previewResult.sample.hotel }}
                    </template>
                </p>
                <div class="border-line bg-app rounded-md border px-4 py-3">
                    <p class="text-brand-900 text-[13.5px] font-semibold">
                        {{ previewResult.subject }}
                    </p>
                    <p
                        class="text-ink mt-2 text-[13px] leading-6 whitespace-pre-wrap"
                    >
                        {{ previewResult.body }}
                    </p>
                </div>
            </template>
            <div class="flex justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="open = false"
                >
                    Close
                </Button>
            </div>
        </div>

        <Form
            v-else
            :key="template?.id ?? 'create'"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-1.5">
                <Label for="template-name" :class="labelClass">
                    Template name
                </Label>
                <Input
                    id="template-name"
                    v-model="name"
                    name="name"
                    required
                    maxlength="120"
                    :aria-invalid="errors.name ? true : undefined"
                    data-test="template-name-input"
                    :class="[fieldClass, 'h-10 shadow-none']"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="template-subject" :class="labelClass">
                    Subject
                </Label>
                <Input
                    id="template-subject"
                    ref="subjectInput"
                    v-model="subject"
                    name="subject"
                    required
                    maxlength="200"
                    :aria-invalid="errors.subject ? true : undefined"
                    data-test="template-subject-input"
                    :class="[fieldClass, 'h-10 shadow-none']"
                    @focus="lastField = 'subject'"
                />
                <InputError :message="errors.subject" />
            </div>

            <div class="grid gap-1.5">
                <Label for="template-body" :class="labelClass">Body</Label>
                <textarea
                    id="template-body"
                    ref="bodyInput"
                    v-model="body"
                    name="body"
                    rows="7"
                    required
                    maxlength="5000"
                    :aria-invalid="errors.body ? true : undefined"
                    data-test="template-body-input"
                    :class="[fieldClass, 'py-2 leading-5']"
                    @focus="lastField = 'body'"
                />
                <InputError :message="errors.body" />

                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="text-ink-slate me-1 text-[11.5px]">
                        Insert variable:
                    </span>
                    <button
                        v-for="variable in variables"
                        :key="variable"
                        type="button"
                        class="bg-brand-50 text-brand-700 hover:bg-brand-100 rounded-pill inline-flex min-h-6 items-center px-2.5 font-mono text-[11px] font-semibold"
                        :data-test="`insert-${variable}-chip`"
                        @click="insertVariable(variable)"
                    >
                        {{ placeholderToken(variable) }}
                    </button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="template-audience" :class="labelClass">
                        Audience (shown on the card)
                    </Label>
                    <Input
                        id="template-audience"
                        v-model="audienceLabel"
                        name="audience_label"
                        maxlength="120"
                        placeholder="Inactive employees"
                        data-test="template-audience-input"
                        :class="[fieldClass, 'h-10 shadow-none']"
                    />
                    <InputError :message="errors.audience_label" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="template-trigger" :class="labelClass">
                        Trigger (shown on the card)
                    </Label>
                    <Input
                        id="template-trigger"
                        v-model="triggerLabel"
                        name="trigger_label"
                        maxlength="120"
                        placeholder="5 days without activity"
                        data-test="template-trigger-input"
                        :class="[fieldClass, 'h-10 shadow-none']"
                    />
                    <InputError :message="errors.trigger_label" />
                </div>
            </div>

            <label
                class="text-brand-900 flex min-h-8 items-center gap-2 text-[12.5px] font-medium"
            >
                <input type="hidden" name="is_active" value="0" />
                <input
                    v-model="isActive"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="accent-brand-600 size-4"
                    data-test="template-active-checkbox"
                />
                Available in the Send dialog and to automation rules
            </label>

            <div
                v-if="previewResult || previewError"
                class="border-line bg-app rounded-md border px-4 py-3"
                aria-live="polite"
            >
                <p v-if="previewError" class="text-danger-text text-[13px]">
                    {{ previewError }}
                </p>
                <template v-else-if="previewResult">
                    <p class="text-ink-slate text-[11.5px]">
                        Preview for
                        <span class="text-brand-900 font-semibold">
                            {{
                                previewResult.sample?.name ?? 'no employee yet'
                            }}
                        </span>
                    </p>
                    <p class="text-brand-900 mt-1 text-[13.5px] font-semibold">
                        {{ previewResult.subject }}
                    </p>
                    <p
                        class="text-ink mt-2 text-[13px] leading-6 whitespace-pre-wrap"
                    >
                        {{ previewResult.body }}
                    </p>
                </template>
            </div>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:items-center md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="previewBusy"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:me-auto"
                    data-test="preview-template-button"
                    @click="loadPreview"
                >
                    <Eye class="size-4" aria-hidden="true" />
                    {{ previewBusy ? 'Rendering…' : 'Preview' }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-template-button"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-template-button"
                >
                    {{ editing ? 'Save changes' : 'Add template' }}
                </Button>
            </div>
        </Form>
    </MessagesModal>
</template>
