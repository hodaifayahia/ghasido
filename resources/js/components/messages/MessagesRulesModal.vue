<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import MessagesDeleteDialog from '@/components/messages/MessagesDeleteDialog.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import {
    messagesListDeleteButton as deleteButton,
    messagesListIconButton as iconButton,
} from '@/components/messages/messagesListStyles';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/messages-reminders/rules';
import type { MessageAutomationRule } from '@/types';

type Props = {
    automations: MessageAutomationRule[];
    /** Add, edit, switch and delete (AutomationRulePolicy). */
    canManage: boolean;
};

defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    add: [];
    edit: [rule: MessageAutomationRule];
    toggle: [rule: MessageAutomationRule];
}>();

// ---------------------------------------------------------------- delete

const deleting = ref<MessageAutomationRule | null>(null);
const confirmOpen = ref(false);
const processing = ref(false);

function askDelete(rule: MessageAutomationRule): void {
    deleting.value = rule;
    confirmOpen.value = true;
}

function confirmDelete(): void {
    const rule = deleting.value;

    if (rule === null) {
        return;
    }

    router.delete(destroy.url(rule.id), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            processing.value = true;
        },
        onFinish: () => {
            processing.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <MessagesModal
        v-model:open="open"
        list
        :title="$t('Automation Rules')"
        :description="
            $t(
                'Reminders sent automatically once a day to employees who match a rule. Consent is always checked before an email goes out.',
            )
        "
        class="sm:max-w-[620px]"
    >
        <template v-if="canManage" #actions>
            <div class="flex justify-end">
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="add-rule-button"
                    @click="emit('add')"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ $t('Add Rule') }}
                </Button>
            </div>
        </template>

        <p
            v-if="automations.length === 0"
            class="text-ink-muted rounded-md border border-dashed px-3 py-8 text-center text-[12.5px]"
        >
            {{ $t('No automation rules yet.') }}
        </p>

        <ul v-else class="flex flex-col gap-2" data-test="rules-list">
            <li
                v-for="rule in automations"
                :key="rule.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p
                            class="text-brand-900 text-[13px] leading-5 font-semibold"
                        >
                            {{ rule.name }}
                        </p>
                        <span
                            :class="
                                cn(
                                    'rounded-pill mt-0.5 inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold',
                                    rule.active
                                        ? 'bg-success-tint text-success-text'
                                        : 'bg-danger-tint text-danger-text',
                                )
                            "
                        >
                            {{ rule.active ? $t('Active') : $t('Paused') }}
                        </span>
                    </div>
                    <div
                        v-if="canManage"
                        class="flex shrink-0 items-center gap-1.5"
                    >
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="rule.active"
                            :aria-label="
                                rule.active
                                    ? $t('Pause :name', { name: rule.name })
                                    : $t('Activate :name', { name: rule.name })
                            "
                            :data-test="`toggle-rule-${rule.id}-button`"
                            class="focus-visible:ring-brand-600/15 inline-flex min-h-11 items-center focus-visible:ring-3 focus-visible:outline-none md:min-h-7"
                            @click="emit('toggle', rule)"
                        >
                            <span
                                :class="
                                    cn(
                                        'rounded-pill relative inline-flex h-4.5 w-8 shrink-0 items-center transition-colors',
                                        rule.active
                                            ? 'bg-brand-600'
                                            : 'bg-line-strong',
                                    )
                                "
                                aria-hidden="true"
                            >
                                <span
                                    :class="
                                        cn(
                                            'bg-surface shadow-card rounded-pill absolute size-3.5 transition-[inset-inline-start]',
                                            rule.active
                                                ? 'start-4'
                                                : 'start-0.5',
                                        )
                                    "
                                />
                            </span>
                        </button>
                        <button
                            type="button"
                            :class="iconButton"
                            :aria-label="$t('Edit :name', { name: rule.name })"
                            :data-test="`edit-rule-${rule.id}-button`"
                            @click="emit('edit', rule)"
                        >
                            <Pencil class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :class="deleteButton"
                            :aria-label="
                                $t('Delete :name', { name: rule.name })
                            "
                            :data-test="`delete-rule-${rule.id}-button`"
                            @click="askDelete(rule)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
                <p class="text-ink-slate mt-1 text-[12px] leading-4.5">
                    {{ rule.trigger }}
                </p>
                <p class="text-ink-muted mt-0.5 text-[11.5px] leading-4">
                    {{
                        $t('Template: :template', {
                            template: rule.templateName,
                        })
                    }}
                </p>
                <p class="text-ink-faint mt-0.5 text-[11px] leading-4">
                    {{ $t('Audience: :audience', { audience: rule.audience }) }}
                    <template v-if="rule.lastRunAt">
                        ·
                        {{ $t('Last run :date', { date: rule.lastRunAt }) }}
                    </template>
                </p>
            </li>
        </ul>
    </MessagesModal>

    <MessagesDeleteDialog
        v-model:open="confirmOpen"
        :title="$t('Delete :name?', { name: deleting?.name ?? '' })"
        :description="
            $t(
                'The rule stops sending at once. Reminders it already sent stay in the log.',
            )
        "
        :processing="processing"
        confirm-test="confirm-delete-rule-button"
        @confirm="confirmDelete"
    />
</template>
