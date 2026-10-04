<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Clock, GraduationCap, Lock, Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { cn } from '@/lib/utils';

/*
 * "My level" (client decisions 2026-09-30 and 2026-10-04): the learner
 * chooses a level, which then stays locked until they finish its
 * Post-test, so their path and its Pre/Post-test results stay meaningful.
 * Before that, a level chosen by mistake is changed by a request with a
 * reason, which the Super Admin approves or refuses.
 */
type Props = { class?: HTMLAttributes['class'] };

const props = defineProps<Props>();

const page = usePage();
const level = computed(() => page.props.learnerLevel);
const chosen = ref(level.value?.current ?? '');
const saving = ref(false);

watch(
    () => level.value?.current,
    (value) => (chosen.value = value ?? ''),
);

function save(): void {
    if (level.value === null || chosen.value === '' || saving.value) {
        return;
    }

    router.put(
        level.value.updateUrl,
        { level: chosen.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
        },
    );
}

// ------------------------------------------------------ change request

const asking = ref(false);
const requested = ref('');
const reason = ref('');
const errors = ref<Record<string, string>>({});

const otherLevels = computed(
    () =>
        level.value?.options.filter(
            (option) => option.value !== level.value?.current,
        ) ?? [],
);

function sendRequest(): void {
    if (level.value === null || saving.value) {
        return;
    }

    router.post(
        level.value.requestUrl,
        { level: requested.value, reason: reason.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onError: (bag) => (errors.value = bag),
            onSuccess: () => {
                errors.value = {};
                asking.value = false;
                requested.value = '';
                reason.value = '';
            },
        },
    );
}

const field =
    'border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 rounded-md border px-3 text-base focus-visible:ring-3 focus-visible:outline-none';
const primary =
    'bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 items-center gap-2 rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50';
</script>

<template>
    <section
        v-if="level"
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
        data-test="my-level"
    >
        <div class="flex items-center gap-3">
            <span
                class="bg-ai-tint text-ai grid size-10 shrink-0 place-items-center rounded-xl"
            >
                <GraduationCap class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h2 class="font-heading text-brand-900 text-base font-semibold">
                    {{ $t('My level') }}
                    <span
                        v-if="level.currentLabel"
                        class="rounded-pill bg-brand-50 text-brand-700 ms-1 px-2.5 py-0.5 align-middle text-xs font-semibold"
                    >
                        {{ $t(level.currentLabel) }}
                    </span>
                </h2>
                <p class="text-ink-slate text-sm">
                    {{
                        level.canChange
                            ? level.current
                                ? $t(
                                      'You finished the Post-test of your level. You can now choose your next level.',
                                  )
                                : $t(
                                      'Choose your level. Your lessons and tests follow it.',
                                  )
                            : $t(
                                  'Your level is locked until you finish its Post-test, so your progress and test results stay meaningful.',
                              )
                    }}
                </p>
            </div>
        </div>

        <!-- Free to choose: first choice, or after the Post-test. -->
        <div v-if="level.canChange" class="flex flex-wrap items-center gap-2">
            <label class="sr-only" for="my-level-select">
                {{ $t('My level') }}
            </label>
            <select
                id="my-level-select"
                v-model="chosen"
                :class="cn(field, 'h-11 min-w-44')"
            >
                <option value="" disabled>{{ $t('Choose your level') }}</option>
                <option
                    v-for="option in level.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <button
                type="button"
                :disabled="saving || chosen === '' || chosen === level.current"
                :class="primary"
                data-test="save-level-button"
                @click="save"
            >
                {{ $t('Save level') }}
            </button>
        </div>

        <!-- Locked: a request waiting, or the button to send one. -->
        <template v-else>
            <p
                v-if="level.pendingRequest"
                class="bg-warning-tint text-warning-text flex items-start gap-2 rounded-md px-3 py-2.5 text-sm"
                role="status"
                data-test="level-request-pending"
            >
                <Clock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>
                    {{
                        $t(
                            'Your request to move to :level is waiting for an answer from the GHASIDO team.',
                            { level: $t(level.pendingRequest.toLabel) },
                        )
                    }}
                </span>
            </p>

            <template v-else>
                <p
                    v-if="level.lastRefusal"
                    class="bg-danger-tint text-danger-text rounded-md px-3 py-2.5 text-sm"
                >
                    {{
                        $t('Your request to move to :level was not approved.', {
                            level: $t(level.lastRefusal.toLabel),
                        })
                    }}
                    <span v-if="level.lastRefusal.note" class="block pt-1">{{
                        level.lastRefusal.note
                    }}</span>
                </p>

                <button
                    v-if="!asking"
                    type="button"
                    class="border-line text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/40 inline-flex h-11 w-fit items-center gap-2 rounded-md border px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
                    data-test="request-level-change-button"
                    @click="asking = true"
                >
                    <Lock class="size-4" aria-hidden="true" />
                    {{ $t('Request Level Change') }}
                </button>

                <form
                    v-else
                    class="grid min-w-0 gap-3"
                    data-test="level-request-form"
                    @submit.prevent="sendRequest"
                >
                    <label class="grid min-w-0 gap-1.5">
                        <span class="text-brand-900 text-sm font-semibold">{{
                            $t('New level')
                        }}</span>
                        <select
                            v-model="requested"
                            required
                            :class="cn(field, 'h-11 w-full sm:w-60')"
                            data-test="level-request-level"
                        >
                            <option value="" disabled>
                                {{ $t('Choose your level') }}
                            </option>
                            <option
                                v-for="option in otherLevels"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="grid min-w-0 gap-1.5">
                        <span class="text-brand-900 text-sm font-semibold">{{
                            $t('Why do you want to change?')
                        }}</span>
                        <textarea
                            v-model="reason"
                            rows="3"
                            minlength="10"
                            maxlength="1000"
                            required
                            dir="auto"
                            :placeholder="
                                $t(
                                    'For example: the lessons are too hard for me, or too easy, or I chose this level by mistake.',
                                )
                            "
                            :class="cn(field, 'min-h-24 w-full resize-y py-2')"
                            data-test="level-request-reason"
                        />
                        <InputError :message="errors.level ?? errors.reason" />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            :class="primary"
                            :disabled="
                                saving ||
                                requested === '' ||
                                reason.trim().length < 10
                            "
                            data-test="level-request-send"
                        >
                            <Send class="size-4" aria-hidden="true" />
                            {{ $t('Send request') }}
                        </button>
                        <button
                            type="button"
                            class="text-ink-slate hover:bg-brand-50 h-11 rounded-md px-4 text-sm font-semibold"
                            @click="asking = false"
                        >
                            {{ $t('Cancel') }}
                        </button>
                    </div>
                </form>
            </template>
        </template>
    </section>
</template>
