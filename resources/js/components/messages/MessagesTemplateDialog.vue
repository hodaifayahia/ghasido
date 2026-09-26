<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Eye, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TransText from '@/components/common/TransText.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import ReminderDraftController from '@/actions/App/Http/Controllers/Admin/Messages/ReminderDraftController';
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

const { t } = useI18n();

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
    draftOpen.value = false;
    draftError.value = '';
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
            throw new Error(
                t('Preview failed (:status).', { status: response.status }),
            );
        }

        previewResult.value = (await response.json()) as MessageTemplatePreview;
    } catch (error) {
        previewError.value =
            error instanceof Error
                ? error.message
                : t('The preview could not be loaded.');
    } finally {
        previewBusy.value = false;
    }
}

// "Draft with AI" (spec 0005 §4.2): the admin says what the reminder is for,
// a queued job writes a subject and body with the platform's placeholders,
// and the dialog polls until it can drop them into the fields. Nothing is
// saved until the admin presses Save (GEN-03).
type DraftState = {
    status: string;
    subject: string | null;
    body: string | null;
    failedReason: string | null;
};

const draftOpen = ref(false);
const draftPurpose = ref('');
const draftTone = ref('friendly');
const drafting = ref(false);
const draftError = ref('');
const draftTones = computed(() => [
    { value: 'friendly', label: t('Friendly') },
    { value: 'encouraging', label: t('Encouraging') },
    { value: 'formal', label: t('Formal') },
]);

function xsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

async function requestDraft(): Promise<void> {
    if (draftPurpose.value.trim().length < 5 || drafting.value) {
        return;
    }

    drafting.value = true;
    draftError.value = '';

    try {
        const response = await fetch(ReminderDraftController.store().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            body: JSON.stringify({
                purpose: draftPurpose.value,
                tone: draftTone.value,
            }),
        });

        if (!response.ok) {
            throw new Error(
                t('The draft could not be requested (:status).', {
                    status: response.status,
                }),
            );
        }

        let state = (await response.json()) as DraftState;

        // Poll the queued job for up to a minute (PERF-04).
        for (
            let tries = 0;
            tries < 40 && state.status !== 'done' && state.status !== 'failed';
            tries++
        ) {
            await wait(1500);
            const poll = await fetch(ReminderDraftController.show().url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            state = (await poll.json()) as DraftState;
        }

        if (
            state.status !== 'done' ||
            state.subject === null ||
            state.body === null
        ) {
            throw new Error(
                state.failedReason ??
                    t(
                        'The draft is taking longer than usual. Try again in a moment.',
                    ),
            );
        }

        subject.value = state.subject;
        body.value = state.body;
        previewResult.value = null;
        draftOpen.value = false;
    } catch (error) {
        draftError.value =
            error instanceof Error
                ? error.message
                : t('The draft could not be written.');
    } finally {
        drafting.value = false;
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
        return props.template
            ? t('Preview :name', { name: props.template.name })
            : t('Preview template');
    }

    if (!editing.value) {
        return t('Add Template');
    }

    return props.template
        ? t('Edit :name', { name: props.template.name })
        : t('Edit template');
});
</script>

<template>
    <MessagesModal
        v-model:open="open"
        :title="title"
        :description="
            readonly
                ? $t(
                      'How this template reads for a sample employee, every variable filled in.',
                  )
                : $t(
                      'Write the subject and body once; the variables are filled in for each employee when a reminder is sent.',
                  )
        "
        class="sm:max-w-[640px]"
    >
        <div v-if="readonly" class="mt-2 grid gap-3">
            <p v-if="previewBusy" class="text-ink-slate text-[13px]">
                {{ $t('Rendering the preview…') }}
            </p>
            <p v-else-if="previewError" class="text-danger-text text-[13px]">
                {{ previewError }}
            </p>
            <template v-else-if="previewResult">
                <p class="text-ink-slate text-[12px]">
                    <TransText text="Sample employee: :name">
                        <template #name>
                            <span class="text-brand-900 font-semibold">
                                {{
                                    previewResult.sample?.name ?? $t('none yet')
                                }}
                            </span>
                        </template>
                    </TransText>
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
                    {{ $t('Close') }}
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
                    {{ $t('Template name') }}
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
                    {{ $t('Subject') }}
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
                <Label for="template-body" :class="labelClass">{{
                    $t('Body')
                }}</Label>
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
                        {{ $t('Insert variable:') }}
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

                <div class="pt-1">
                    <button
                        v-if="!draftOpen"
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/15 inline-flex h-9 items-center gap-2 rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        data-test="template-draft-open-button"
                        @click="draftOpen = true"
                    >
                        <Sparkles class="text-ai size-4" aria-hidden="true" />
                        {{ $t('Draft with AI') }}
                    </button>

                    <div
                        v-else
                        class="bg-brand-50 grid gap-2 rounded-md p-3"
                        data-test="template-draft-panel"
                    >
                        <Label for="template-draft-purpose" :class="labelClass">
                            {{ $t('What is this reminder for?') }}
                        </Label>
                        <textarea
                            id="template-draft-purpose"
                            v-model="draftPurpose"
                            rows="2"
                            maxlength="300"
                            :placeholder="
                                $t(
                                    'For example: learners who have not practised for a week',
                                )
                            "
                            :class="[fieldClass, 'py-2 leading-5']"
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <label
                                for="template-draft-tone"
                                class="text-ink-slate text-[12px]"
                                >{{ $t('Tone') }}</label
                            >
                            <select
                                id="template-draft-tone"
                                v-model="draftTone"
                                :class="[fieldClass, 'h-9 w-auto']"
                            >
                                <option
                                    v-for="tone in draftTones"
                                    :key="tone.value"
                                    :value="tone.value"
                                >
                                    {{ tone.label }}
                                </option>
                            </select>
                            <button
                                type="button"
                                :disabled="
                                    drafting || draftPurpose.trim().length < 5
                                "
                                class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 ms-auto inline-flex h-9 items-center gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60"
                                data-test="template-draft-button"
                                @click="requestDraft"
                            >
                                <Sparkles class="size-4" aria-hidden="true" />
                                {{
                                    drafting
                                        ? $t('Writing…')
                                        : $t('Write draft')
                                }}
                            </button>
                        </div>
                        <p
                            v-if="drafting"
                            class="text-ink-slate text-[11.5px]"
                            aria-live="polite"
                        >
                            {{
                                $t(
                                    'Writing a draft. It will replace the subject and body above, and nothing is saved until you press Save.',
                                )
                            }}
                        </p>
                        <InputError :message="draftError" />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="template-audience" :class="labelClass">
                        {{ $t('Audience (shown on the card)') }}
                    </Label>
                    <Input
                        id="template-audience"
                        v-model="audienceLabel"
                        name="audience_label"
                        maxlength="120"
                        :placeholder="$t('Inactive employees')"
                        data-test="template-audience-input"
                        :class="[fieldClass, 'h-10 shadow-none']"
                    />
                    <InputError :message="errors.audience_label" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="template-trigger" :class="labelClass">
                        {{ $t('Trigger (shown on the card)') }}
                    </Label>
                    <Input
                        id="template-trigger"
                        v-model="triggerLabel"
                        name="trigger_label"
                        maxlength="120"
                        :placeholder="$t('5 days without activity')"
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
                {{ $t('Available in the Send dialog and to automation rules') }}
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
                    <TransText
                        tag="p"
                        text="Preview for :name"
                        class="text-ink-slate text-[11.5px]"
                    >
                        <template #name>
                            <span class="text-brand-900 font-semibold">
                                {{
                                    previewResult.sample?.name ??
                                    $t('no employee yet')
                                }}
                            </span>
                        </template>
                    </TransText>
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
                    {{ previewBusy ? $t('Rendering…') : $t('Preview') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-template-button"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-template-button"
                >
                    {{ editing ? $t('Save changes') : $t('Add template') }}
                </Button>
            </div>
        </Form>
    </MessagesModal>
</template>
