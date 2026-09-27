<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Check, CircleCheck, CircleX, X } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Approve or reject an online purchase once the payment is checked (client
 * request 2026-09-27, reference "B. Admin flow, step 3"). Two tiles explain
 * what each choice does; Approve is the brand primary button and Reject a
 * red outline (AGENTS.md §3: no green or red fills). Each asks for a
 * confirmation, and rejecting needs the reason the customer is emailed.
 */
type Props = {
    approveAction: RouteFormDefinition<'post'>;
    rejectAction: RouteFormDefinition<'post'>;
    /** The hotel or person, for the dialog titles. */
    subject: string;
    approveHint: string;
    rejectHint: string;
    approveConfirm: string;
    rejectConfirm: string;
    /** A stable prefix for `data-test` hooks, e.g. `hotel` or `individual`. */
    testId: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    done: [decision: 'approved' | 'rejected'];
}>();

const { t } = useI18n();

const approveOpen = ref(false);
const rejectOpen = ref(false);
const reason = ref('');

function firstError(errors: Record<string, string>): string | undefined {
    return Object.values(errors).find((message) => message !== '');
}

function approved(): void {
    approveOpen.value = false;
    emit('done', 'approved');
}

function rejected(): void {
    rejectOpen.value = false;
    reason.value = '';
    emit('done', 'rejected');
}

const inputClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 py-2 text-[13px] leading-5 focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <div :class="cn('grid gap-2.5', props.class)">
        <div class="grid gap-2">
            <div
                class="border-success/30 bg-success-tint flex items-start gap-3 rounded-md border px-3 py-2.5"
            >
                <CircleCheck
                    class="text-success mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="text-success-text text-[13px] font-semibold">
                        {{ $t('Approve payment') }}
                    </p>
                    <p class="text-success-text/85 text-[12px] leading-5">
                        {{ approveHint }}
                    </p>
                </div>
            </div>
            <div
                class="border-danger/25 bg-danger-tint flex items-start gap-3 rounded-md border px-3 py-2.5"
            >
                <CircleX
                    class="text-danger mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="text-danger-text text-[13px] font-semibold">
                        {{ $t('Reject payment') }}
                    </p>
                    <p class="text-danger-text/85 text-[12px] leading-5">
                        {{ rejectHint }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 font-heading h-11 gap-2 rounded-md px-4 text-[13px] font-semibold text-white active:scale-[.97] motion-reduce:transition-none"
                :data-test="`approve-${testId}-button`"
                @click="approveOpen = true"
            >
                <Check class="size-4" aria-hidden="true" />
                {{ $t('Approve') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                class="border-danger text-danger-text hover:bg-danger-tint bg-surface font-heading h-11 gap-2 rounded-md px-4 text-[13px] font-semibold shadow-none active:scale-[.97] motion-reduce:transition-none"
                :data-test="`reject-${testId}-button`"
                @click="rejectOpen = true"
            >
                <X class="size-4" aria-hidden="true" />
                {{ $t('Reject') }}
            </Button>
        </div>

        <HotelsModal
            v-model:open="approveOpen"
            :title="t('Approve :name?', { name: subject })"
            :description="approveConfirm"
        >
            <Form
                v-bind="approveAction"
                :options="{ preserveScroll: true }"
                class="mt-2 grid gap-4"
                v-slot="{ errors, processing }"
                @success="approved"
            >
                <div
                    class="border-success/30 bg-success-tint flex items-start gap-3 rounded-md border px-3 py-3"
                >
                    <CircleCheck
                        class="text-success mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="text-success-text text-[12.5px] leading-5">
                        {{ approveHint }}
                    </p>
                </div>
                <p
                    v-if="firstError(errors)"
                    role="alert"
                    class="text-danger-text text-[12.5px]"
                >
                    {{ firstError(errors) }}
                </p>
                <div
                    class="flex flex-col-reverse gap-2 md:flex-row md:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                        @click="approveOpen = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97] md:h-10"
                        :data-test="`confirm-approve-${testId}-button`"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        {{ processing ? $t('Approving…') : $t('Yes, approve') }}
                    </Button>
                </div>
            </Form>
        </HotelsModal>

        <HotelsModal
            v-model:open="rejectOpen"
            :title="t('Reject :name?', { name: subject })"
            :description="rejectConfirm"
        >
            <Form
                v-bind="rejectAction"
                :options="{ preserveScroll: true }"
                class="mt-2 grid gap-4"
                v-slot="{ errors, processing }"
                @success="rejected"
            >
                <div class="grid gap-1.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <Label
                            :for="`${testId}-reject-reason`"
                            class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                        >
                            {{ $t('Reason sent to the customer') }}
                        </Label>
                        <span
                            class="text-ink-muted text-[11px] tabular-nums"
                            aria-hidden="true"
                            >{{ reason.length }}/500</span
                        >
                    </div>
                    <textarea
                        :id="`${testId}-reject-reason`"
                        v-model="reason"
                        name="reason"
                        rows="4"
                        required
                        minlength="3"
                        maxlength="500"
                        :aria-invalid="errors.reason ? true : undefined"
                        :aria-describedby="`${testId}-reject-reason-help`"
                        :class="inputClass"
                        :placeholder="
                            $t(
                                'For example: the amount on the receipt does not match the plan.',
                            )
                        "
                        :data-test="`${testId}-reject-reason-input`"
                    />
                    <p
                        :id="`${testId}-reject-reason-help`"
                        class="text-ink-muted text-[11.5px] leading-4"
                    >
                        {{
                            $t(
                                'Write it plainly: the customer reads it in the email. Nothing is deleted.',
                            )
                        }}
                    </p>
                    <p
                        v-if="firstError(errors)"
                        role="alert"
                        class="text-danger-text text-[12.5px]"
                    >
                        {{ firstError(errors) }}
                    </p>
                </div>
                <div
                    class="flex flex-col-reverse gap-2 md:flex-row md:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                        @click="rejectOpen = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing || reason.trim().length < 3"
                        class="border-danger text-danger-text hover:bg-danger-tint bg-surface h-11 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none active:scale-[.97] md:h-10"
                        :data-test="`confirm-reject-${testId}-button`"
                    >
                        <X class="size-4" aria-hidden="true" />
                        {{
                            processing ? $t('Rejecting…') : $t('Reject payment')
                        }}
                    </Button>
                </div>
            </Form>
        </HotelsModal>
    </div>
</template>
