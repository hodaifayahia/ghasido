<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CircleCheck, Pencil, Send, Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { intlLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { reply as replyRoute } from '@/routes/learn/messages';
import type { LearnerMessage } from '@/types';

/*
 * The learner's answer to a message sent with "Allow replies" (client
 * request 2026-11-07): an optional 1-5 star rating when the sender asked
 * for one, and a short text. After sending, the answer is shown with an
 * "Edit reply" button. Phone first: full-width field, 44px targets, the
 * state said in words and an icon, never colour alone (ACC-02, ACC-03).
 */
type Props = {
    message: LearnerMessage;
};

const props = defineProps<Props>();

const MAX = 2000;

const editing = ref(props.message.reply === null);
const body = ref(props.message.reply?.body ?? '');
const rating = ref<number | null>(props.message.reply?.rating ?? null);

const remaining = computed(() => MAX - body.value.length);

const repliedOn = computed(() => {
    const value = props.message.reply?.repliedAt;

    return value
        ? new Date(value).toLocaleDateString(intlLocale(), {
              day: 'numeric',
              month: 'short',
          })
        : '';
});

function choose(value: number): void {
    rating.value = rating.value === value ? null : value;
}

function startEditing(): void {
    body.value = props.message.reply?.body ?? '';
    rating.value = props.message.reply?.rating ?? null;
    editing.value = true;
}

function onSuccess(): void {
    editing.value = false;
}

const fieldId = computed(() => `reply-${props.message.id}`);
</script>

<template>
    <div class="border-line mt-4 border-t pt-4">
        <!-- The answer as sent, with a way to change it. -->
        <div
            v-if="!editing && message.reply !== null"
            class="bg-success-tint/60 rounded-md p-3"
            data-test="message-reply-sent"
        >
            <p
                class="text-success-text flex items-center gap-1.5 text-sm font-semibold"
            >
                <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
                {{ $t('Your reply was sent') }}
                <span class="text-ink-slate text-xs font-normal">{{
                    repliedOn
                }}</span>
            </p>
            <p
                v-if="message.reply.rating !== null"
                class="mt-1.5 flex items-center gap-0.5"
            >
                <span class="sr-only">{{
                    $t('Rated :rating of 5', { rating: message.reply.rating })
                }}</span>
                <Star
                    v-for="n in 5"
                    :key="n"
                    :class="
                        cn(
                            'size-4',
                            n <= (message.reply.rating ?? 0)
                                ? 'fill-gold text-gold'
                                : 'text-line-strong',
                        )
                    "
                    aria-hidden="true"
                />
            </p>
            <p
                v-if="message.reply.body"
                class="text-ink mt-1.5 text-sm leading-6 break-words whitespace-pre-line"
            >
                {{ message.reply.body }}
            </p>
            <button
                type="button"
                class="text-brand-600 focus-visible:ring-brand-600/40 mt-1 inline-flex min-h-11 items-center gap-1.5 rounded-md px-2 text-sm font-semibold underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                data-test="edit-message-reply-button"
                @click="startEditing"
            >
                <Pencil class="size-4" aria-hidden="true" />
                {{ $t('Edit reply') }}
            </button>
        </div>

        <Form
            v-else
            v-bind="replyRoute.form({ reminder: message.id })"
            :options="{ preserveScroll: true }"
            class="grid gap-3"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <fieldset v-if="message.askRating" class="grid gap-1.5">
                <legend class="text-ink-night mb-1.5 text-sm font-semibold">
                    {{ $t('Your rating') }}
                    <span class="text-ink-slate font-normal">{{
                        $t('(optional)')
                    }}</span>
                </legend>
                <input type="hidden" name="rating" :value="rating ?? ''" />
                <div class="flex flex-wrap items-center gap-1">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        :aria-pressed="rating !== null && n <= rating"
                        :aria-label="$t(':count of 5 stars', { count: n })"
                        class="hover:bg-gold-tint focus-visible:ring-brand-600/40 grid size-11 place-items-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
                        :data-test="`reply-rating-${n}-button`"
                        @click="choose(n)"
                    >
                        <Star
                            :class="
                                cn(
                                    'size-7 transition-transform duration-100 motion-reduce:transition-none',
                                    rating !== null && n <= rating
                                        ? 'fill-gold text-gold scale-105'
                                        : 'text-line-strong',
                                )
                            "
                            aria-hidden="true"
                        />
                    </button>
                    <span
                        v-if="rating !== null"
                        class="text-ink-slate ms-1 text-sm"
                        aria-live="polite"
                    >
                        {{ $t(':rating of 5', { rating: rating }) }}
                    </span>
                </div>
                <InputError :message="errors.rating" />
            </fieldset>

            <div class="grid gap-1.5">
                <label
                    :for="fieldId"
                    class="text-ink-night text-sm font-semibold"
                >
                    {{ $t('Your reply') }}
                </label>
                <textarea
                    :id="fieldId"
                    v-model="body"
                    name="body"
                    rows="3"
                    :maxlength="MAX"
                    :placeholder="$t('Write your opinion or suggestions…')"
                    :aria-invalid="errors.body ? true : undefined"
                    :aria-describedby="`${fieldId}-count`"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-24 w-full rounded-sm border px-3 py-2 text-base leading-6 focus-visible:ring-3 focus-visible:outline-none"
                    data-test="message-reply-textarea"
                />
                <p
                    :id="`${fieldId}-count`"
                    class="text-ink-faint text-end text-xs"
                >
                    {{ $t(':count characters left', { count: remaining }) }}
                </p>
                <InputError :message="errors.body" />
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    v-if="message.reply !== null"
                    type="button"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/40 inline-flex min-h-11 items-center justify-center rounded-md border px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    data-test="cancel-message-reply-button"
                    @click="editing = false"
                >
                    {{ $t('Cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:outline-none active:scale-[.97] disabled:opacity-60"
                    data-test="send-message-reply-button"
                >
                    <Send class="size-4" aria-hidden="true" />
                    {{
                        message.reply !== null
                            ? $t('Update reply')
                            : $t('Send reply')
                    }}
                </button>
            </div>
        </Form>
    </div>
</template>
