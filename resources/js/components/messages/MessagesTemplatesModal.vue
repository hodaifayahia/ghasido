<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import MessagesDeleteDialog from '@/components/messages/MessagesDeleteDialog.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import {
    messagesListDeleteButton as deleteButton,
    messagesListIconButton as iconButton,
} from '@/components/messages/messagesListStyles';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { destroy } from '@/routes/messages-reminders/templates';
import type { MessageAutomationRule, MessageTemplate } from '@/types';

type Props = {
    templates: MessageTemplate[];
    /** To tell which templates a rule still sends (they cannot be deleted). */
    automations: MessageAutomationRule[];
    /** Add, edit and delete (ReminderTemplatePolicy); preview is open to all. */
    canManage: boolean;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    add: [];
    edit: [template: MessageTemplate];
    preview: [template: MessageTemplate];
}>();

const { t, tc } = useI18n();

/** Rule names per template id, for the "used by" line and the refusal. */
const rulesByTemplate = computed(() => {
    const map = new Map<number, string[]>();

    for (const rule of props.automations) {
        map.set(rule.templateId, [
            ...(map.get(rule.templateId) ?? []),
            rule.name,
        ]);
    }

    return map;
});

function rulesOf(template: MessageTemplate): string[] {
    return rulesByTemplate.value.get(template.id) ?? [];
}

// ---------------------------------------------------------------- delete

const deleting = ref<MessageTemplate | null>(null);
const confirmOpen = ref(false);
const processing = ref(false);

const blocked = computed(() => {
    if (deleting.value === null) {
        return '';
    }

    const rules = rulesOf(deleting.value);

    return rules.length === 0
        ? ''
        : t(
              'This template is still used by these automation rules: :rules. Point them at another template or delete them first.',
              { rules: rules.join(', ') },
          );
});

function askDelete(template: MessageTemplate): void {
    deleting.value = template;
    confirmOpen.value = true;
}

function confirmDelete(): void {
    const template = deleting.value;

    if (template === null) {
        return;
    }

    router.delete(destroy.url(template.id), {
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
        :title="$t('Reminder Templates')"
        :description="
            $t(
                'Reusable reminder messages. Variables are filled in for each employee when the reminder is sent.',
            )
        "
        class="sm:max-w-[620px]"
    >
        <template v-if="canManage" #actions>
            <div class="flex justify-end">
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="add-template-button"
                    @click="emit('add')"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ $t('Add Template') }}
                </Button>
            </div>
        </template>

        <p
            v-if="templates.length === 0"
            class="text-ink-muted rounded-md border border-dashed px-3 py-8 text-center text-[12.5px]"
        >
            {{ $t('No reminder templates yet.') }}
        </p>

        <ul v-else class="flex flex-col gap-2" data-test="templates-list">
            <li
                v-for="templateItem in templates"
                :key="templateItem.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-start justify-between gap-2">
                    <p
                        class="text-brand-900 min-w-0 text-[13px] leading-5 font-semibold"
                    >
                        {{ templateItem.name }}
                        <span
                            v-if="!templateItem.isActive"
                            class="bg-danger-tint text-danger-text rounded-pill ms-1 inline-flex min-h-4 items-center px-1.5 align-middle text-[10px] font-semibold"
                        >
                            {{ $t('Inactive') }}
                        </span>
                    </p>
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            :class="iconButton"
                            :aria-label="
                                $t('Preview :name', { name: templateItem.name })
                            "
                            :data-test="`preview-template-${templateItem.id}-button`"
                            @click="emit('preview', templateItem)"
                        >
                            <Eye class="size-3.5" aria-hidden="true" />
                        </button>
                        <template v-if="canManage">
                            <button
                                type="button"
                                :class="iconButton"
                                :aria-label="
                                    $t('Edit :name', {
                                        name: templateItem.name,
                                    })
                                "
                                :data-test="`edit-template-${templateItem.id}-button`"
                                @click="emit('edit', templateItem)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                :class="deleteButton"
                                :aria-label="
                                    $t('Delete :name', {
                                        name: templateItem.name,
                                    })
                                "
                                :data-test="`delete-template-${templateItem.id}-button`"
                                @click="askDelete(templateItem)"
                            >
                                <Trash2 class="size-3.5" aria-hidden="true" />
                            </button>
                        </template>
                    </div>
                </div>
                <p class="text-ink-slate mt-1 text-[12px] leading-4.5">
                    {{ templateItem.subject }}
                </p>
                <p class="text-ink-muted mt-1 text-[11.5px] leading-4">
                    {{ templateItem.audience || $t('Any employee') }}
                    ·
                    {{
                        $t('Trigger: :trigger', {
                            trigger: templateItem.trigger || $t('Manual send'),
                        })
                    }}
                </p>
                <p class="text-ink-faint mt-0.5 text-[11px] leading-4">
                    {{ $t('Updated :date', { date: templateItem.updatedAt }) }}
                    <template v-if="rulesOf(templateItem).length > 0">
                        ·
                        {{
                            tc(
                                'Used by :count automation rule|Used by :count automation rules',
                                rulesOf(templateItem).length,
                            )
                        }}
                    </template>
                </p>
            </li>
        </ul>
    </MessagesModal>

    <MessagesDeleteDialog
        v-model:open="confirmOpen"
        :title="
            $t('Delete :name?', {
                name: deleting?.name ?? '',
            })
        "
        :description="
            $t(
                'The template is removed. Reminders already sent with it stay in the log with the text they had.',
            )
        "
        :blocked="blocked"
        :processing="processing"
        confirm-test="confirm-delete-template-button"
        @confirm="confirmDelete"
    />
</template>
