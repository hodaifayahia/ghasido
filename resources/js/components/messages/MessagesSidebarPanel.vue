<script setup lang="ts">
import { Bell, Clock, FileText, Mail } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type {
    MessageAutomationRule,
    MessageLog,
    MessageLogStatus,
    MessageTemplate,
} from '@/types';

type Props = {
    templates: MessageTemplate[];
    automations: MessageAutomationRule[];
    logs: MessageLog[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const logTone: Record<MessageLogStatus, string> = {
    sent: 'bg-success-tint text-success-text',
    scheduled: 'bg-brand-100/70 text-brand-700',
    blocked: 'bg-danger-tint text-danger-text',
};

const logText: Record<MessageLogStatus, string> = {
    sent: 'Sent',
    scheduled: 'Scheduled',
    blocked: 'Blocked',
};
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <PanelCard
            title="Reminder Templates"
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

            <article
                v-for="templateItem in templates"
                :key="templateItem.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <p class="text-brand-900 text-[12.5px] font-semibold">
                    {{ templateItem.name }}
                </p>
                <p class="text-ink-slate mt-1 text-[11.5px] leading-4.5">
                    {{ templateItem.audience }}
                </p>
                <p class="text-ink-muted mt-1 text-[11px] leading-4">
                    Trigger: {{ templateItem.trigger }}
                </p>
                <p class="text-ink-faint mt-0.5 text-[11px] leading-4">
                    Updated {{ templateItem.updatedAt }}
                </p>
            </article>

            <Button
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 h-10 rounded-md text-[12.5px] font-semibold shadow-none"
                @click="notifyComingSoon('Reminder templates')"
            >
                Edit Templates
            </Button>
        </PanelCard>

        <PanelCard
            title="Automation Rules"
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

            <article
                v-for="rule in automations"
                :key="rule.id"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-brand-900 text-[12.5px] font-semibold">
                        {{ rule.name }}
                    </p>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 px-2 text-[10.5px] font-semibold',
                                rule.active
                                    ? 'bg-success-tint text-success-text'
                                    : 'bg-danger-tint text-danger-text',
                            )
                        "
                    >
                        {{ rule.active ? 'Active' : 'Paused' }}
                    </span>
                </div>
                <p class="text-ink-slate mt-1 text-[11.5px] leading-4.5">
                    {{ rule.trigger }}
                </p>
                <p class="text-ink-faint mt-0.5 text-[11px] leading-4">
                    Audience: {{ rule.audience }}
                </p>
            </article>
        </PanelCard>

        <PanelCard
            title="Reminder Log"
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
                        {{ logText[log.status] }}
                    </span>
                </div>
                <p class="text-ink-faint mt-1 text-[11px] leading-4">
                    {{ log.sentAt }}
                </p>
            </article>

            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md text-[12.5px] font-semibold text-white"
                @click="notifyComingSoon('Reminder history')"
            >
                <Mail class="size-4" aria-hidden="true" />
                Open Full Log
            </Button>
        </PanelCard>
    </div>
</template>
