<script setup lang="ts">
import { Bell, Clock, Eye, FileText, Mail, Pencil, Plus } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import MessagesPager from '@/components/messages/MessagesPager.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    MessageAbilities,
    MessageAutomationRule,
    MessageLog,
    MessageLogStatus,
    MessagePagination,
    MessageTemplate,
} from '@/types';

type Props = {
    templates: MessageTemplate[];
    automations: MessageAutomationRule[];
    logs: MessageLog[];
    logsPagination: MessagePagination;
    abilities: MessageAbilities;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    addTemplate: [];
    editTemplate: [template: MessageTemplate];
    previewTemplate: [template: MessageTemplate];
    addRule: [];
    editRule: [rule: MessageAutomationRule];
    toggleRule: [rule: MessageAutomationRule];
    logPage: [page: number];
    openLog: [];
}>();

const logTone: Record<MessageLogStatus, string> = {
    sent: 'bg-success-tint text-success-text',
    scheduled: 'bg-brand-100/70 text-brand-700',
    queued: 'bg-brand-100/70 text-brand-700',
    blocked: 'bg-danger-tint text-danger-text',
    failed: 'bg-danger-tint text-danger-text',
};

const logText: Record<MessageLogStatus, string> = {
    sent: tk('Sent'),
    scheduled: tk('Scheduled'),
    queued: tk('Queued'),
    blocked: tk('Blocked'),
    failed: tk('Failed'),
};

const iconButton =
    'border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-6.5 shrink-0 items-center justify-center rounded-md border focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <PanelCard
            :title="$t('Reminder Templates')"
            title-id="message-templates-title"
            body-class="flex flex-col gap-3"
        >
            <template #icon>
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <FileText
                        class="[&>path:not(:first-child)]:stroke-surface size-4.5 fill-current"
                        aria-hidden="true"
                    />
                </div>
            </template>

            <p
                v-if="templates.length === 0"
                class="text-ink-muted rounded-md border border-dashed px-3 py-6 text-center text-[12.5px]"
            >
                {{ $t('No reminder templates yet.') }}
            </p>

            <article
                v-for="templateItem in templates"
                :key="templateItem.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-brand-900 text-[12.5px] font-semibold">
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
                            @click="emit('previewTemplate', templateItem)"
                        >
                            <Eye class="size-3" aria-hidden="true" />
                        </button>
                        <button
                            v-if="abilities.manageTemplates"
                            type="button"
                            :class="iconButton"
                            :aria-label="
                                $t('Edit :name', { name: templateItem.name })
                            "
                            :data-test="`edit-template-${templateItem.id}-button`"
                            @click="emit('editTemplate', templateItem)"
                        >
                            <Pencil class="size-3" aria-hidden="true" />
                        </button>
                    </div>
                </div>
                <p class="text-ink-slate mt-1 text-[11.5px] leading-4.5">
                    {{ templateItem.audience || $t('Any employee') }}
                </p>
                <p class="text-ink-muted mt-1 text-[11px] leading-4">
                    {{
                        $t('Trigger: :trigger', {
                            trigger: templateItem.trigger || $t('Manual send'),
                        })
                    }}
                </p>
                <p class="text-ink-faint mt-0.5 text-[11px] leading-4">
                    {{ $t('Updated :date', { date: templateItem.updatedAt }) }}
                </p>
            </article>

            <Button
                v-if="abilities.manageTemplates"
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 h-10 rounded-md text-[12.5px] font-semibold shadow-none"
                data-test="add-template-button"
                @click="emit('addTemplate')"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ $t('Add Template') }}
            </Button>
        </PanelCard>

        <PanelCard
            :title="$t('Automation Rules')"
            title-id="message-automation-title"
            body-class="flex flex-col gap-3"
        >
            <template #icon>
                <div
                    class="bg-ai/12 text-ai grid size-8 place-items-center rounded-full"
                >
                    <Bell class="size-4.5" aria-hidden="true" />
                </div>
            </template>

            <template #actions>
                <button
                    v-if="abilities.manageRules"
                    type="button"
                    class="text-brand-600 hover:bg-brand-50 inline-flex min-h-8 items-center gap-1 rounded-md px-2 text-[12px] font-semibold"
                    data-test="add-rule-button"
                    @click="emit('addRule')"
                >
                    <Plus class="size-3.5" aria-hidden="true" />
                    {{ $t('Add Rule') }}
                </button>
            </template>

            <p
                v-if="automations.length === 0"
                class="text-ink-muted rounded-md border border-dashed px-3 py-6 text-center text-[12.5px]"
            >
                {{ $t('No automation rules yet.') }}
            </p>

            <article
                v-for="rule in automations"
                :key="rule.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-center justify-between gap-2">
                    <p
                        class="text-brand-900 min-w-0 truncate text-[12.5px] font-semibold"
                    >
                        {{ rule.name }}
                    </p>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold',
                                    rule.active
                                        ? 'bg-success-tint text-success-text'
                                        : 'bg-danger-tint text-danger-text',
                                )
                            "
                        >
                            {{ rule.active ? $t('Active') : $t('Paused') }}
                        </span>
                        <button
                            v-if="abilities.manageRules"
                            type="button"
                            role="switch"
                            :aria-checked="rule.active"
                            :aria-label="
                                rule.active
                                    ? $t('Pause :name', { name: rule.name })
                                    : $t('Activate :name', { name: rule.name })
                            "
                            :data-test="`toggle-rule-${rule.id}-button`"
                            :class="
                                cn(
                                    'focus-visible:ring-brand-600/15 rounded-pill relative inline-flex h-4.5 w-8 shrink-0 items-center transition-colors focus-visible:ring-3 focus-visible:outline-none',
                                    rule.active
                                        ? 'bg-brand-600'
                                        : 'bg-line-strong',
                                )
                            "
                            @click="emit('toggleRule', rule)"
                        >
                            <span
                                :class="
                                    cn(
                                        'bg-surface shadow-card rounded-pill absolute size-3.5 transition-[inset-inline-start]',
                                        rule.active ? 'start-4' : 'start-0.5',
                                    )
                                "
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            v-if="abilities.manageRules"
                            type="button"
                            :class="iconButton"
                            :aria-label="$t('Edit :name', { name: rule.name })"
                            :data-test="`edit-rule-${rule.id}-button`"
                            @click="emit('editRule', rule)"
                        >
                            <Pencil class="size-3" aria-hidden="true" />
                        </button>
                    </div>
                </div>
                <p class="text-ink-slate mt-1 text-[11.5px] leading-4.5">
                    {{ rule.trigger }}
                </p>
                <p class="text-ink-faint mt-0.5 text-[11px] leading-4">
                    {{ $t('Audience: :audience', { audience: rule.audience }) }}
                </p>
            </article>
        </PanelCard>

        <PanelCard
            :title="$t('Reminder Log')"
            title-id="message-log-title"
            body-class="flex flex-col gap-3"
        >
            <template #icon>
                <div
                    class="bg-warning-tint text-warning grid size-8 place-items-center rounded-full"
                >
                    <Clock class="size-4.5" aria-hidden="true" />
                </div>
            </template>

            <p
                v-if="logs.length === 0"
                class="text-ink-muted rounded-md border border-dashed px-3 py-6 text-center text-[12.5px]"
            >
                {{ $t('No reminders have been sent yet.') }}
            </p>

            <article
                v-for="log in logs"
                :key="log.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p
                            class="text-brand-900 truncate text-[12.5px] font-semibold"
                        >
                            {{ log.recipient }}
                        </p>
                        <p
                            class="text-ink-slate truncate text-[11.5px] leading-4.5"
                        >
                            {{ log.template }}
                        </p>
                    </div>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 px-2 text-[10.5px] font-semibold',
                                logTone[log.status],
                            )
                        "
                    >
                        {{ $t(logText[log.status]) }}
                    </span>
                </div>
                <p class="text-ink-faint mt-1 text-[11px] leading-4">
                    {{ log.sentAt }}
                </p>
            </article>

            <MessagesPager
                v-if="logsPagination.total > logs.length"
                :pagination="logsPagination"
                noun="reminders"
                :label="$t('Reminder log pagination')"
                compact
                @page="emit('logPage', $event)"
            />

            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md text-[12.5px] font-semibold text-white active:scale-[.97]"
                data-test="open-full-log-button"
                @click="emit('openLog')"
            >
                <Mail class="size-4" aria-hidden="true" />
                {{ $t('Open Full Log') }}
            </Button>
        </PanelCard>
    </div>
</template>
