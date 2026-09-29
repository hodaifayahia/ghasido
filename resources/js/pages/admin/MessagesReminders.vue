<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DeleteRowDialog from '@/components/common/DeleteRowDialog.vue';
import MessagesLogDialog from '@/components/messages/MessagesLogDialog.vue';
import MessagesRecipientsPanel from '@/components/messages/MessagesRecipientsPanel.vue';
import MessagesRuleDialog from '@/components/messages/MessagesRuleDialog.vue';
import MessagesSendDialog from '@/components/messages/MessagesSendDialog.vue';
import MessagesSidebarPanel from '@/components/messages/MessagesSidebarPanel.vue';
import MessagesStatsRow from '@/components/messages/MessagesStatsRow.vue';
import MessagesTemplateDialog from '@/components/messages/MessagesTemplateDialog.vue';
import MessagesToolbar from '@/components/messages/MessagesToolbar.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import {
    dashboard,
    messagesReminders as messagesRemindersRoute,
} from '@/routes';
import { tk } from '@/lib/i18n';
import {
    destroy as destroyRule,
    toggle,
} from '@/routes/messages-reminders/rules';
import { destroy as destroyTemplate } from '@/routes/messages-reminders/templates';
import type {
    MessageAbilities,
    MessageAutomationRule,
    MessageFilterValues,
    MessageFilters,
    MessageLog,
    MessageMetric,
    MessageOptions,
    MessagePagination,
    MessageRecipient,
    MessageSendSelection,
    MessageTemplate,
} from '@/types';

type Props = {
    stats: MessageMetric[];
    filters: MessageFilters;
    recipients: MessageRecipient[];
    recipientsPagination: MessagePagination;
    templates: MessageTemplate[];
    automations: MessageAutomationRule[];
    logs: MessageLog[];
    logsPagination: MessagePagination;
    options: MessageOptions;
    abilities: MessageAbilities;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: tk('Dashboard'),
                href: dashboard(),
            },
            {
                title: tk('Messages & Reminders'),
                href: messagesRemindersRoute(),
            },
        ],
    },
});

// ------------------------------------------------------------ navigation
//
// The five filters, the recipients page and the log page live in the query
// string, so a refresh holds them (REM-02). Partial reloads keep the shell
// and the cards that did not change still.

type Query = {
    hotel?: string;
    department?: string;
    consent?: string;
    activity?: string;
    search?: string;
    page?: number;
    log_page?: number;
};

const DEFAULTS = {
    hotel: 'all-hotels',
    department: 'all-departments',
    consent: 'consent-granted',
    activity: 'inactive-5-days',
};

const loading = ref(false);
const logLoading = ref(false);

function filterQuery(values: MessageFilterValues): Query {
    const query: Query = {};

    if (values.hotel !== DEFAULTS.hotel) {
        query.hotel = values.hotel;
    }
    if (values.department !== DEFAULTS.department) {
        query.department = values.department;
    }
    if (values.consent !== DEFAULTS.consent) {
        query.consent = values.consent;
    }
    if (values.activity !== DEFAULTS.activity) {
        query.activity = values.activity;
    }
    if (values.search !== '') {
        query.search = values.search;
    }

    return query;
}

function currentQuery(): Query {
    const query = filterQuery(props.filters);

    if (props.recipientsPagination.currentPage > 1) {
        query.page = props.recipientsPagination.currentPage;
    }
    if (props.logsPagination.currentPage > 1) {
        query.log_page = props.logsPagination.currentPage;
    }

    return query;
}

function visit(
    query: Query,
    only: string[],
    busy: typeof loading = loading,
): void {
    router.get(messagesRemindersRoute().url, query, {
        only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            busy.value = true;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}

const RECIPIENT_PROPS = ['filters', 'recipients', 'recipientsPagination'];

function applyFilters(values: MessageFilterValues): void {
    selected.value = [];
    allMatching.value = false;

    const query = filterQuery(values);

    if (props.logsPagination.currentPage > 1) {
        query.log_page = props.logsPagination.currentPage;
    }

    visit(query, [...RECIPIENT_PROPS, 'stats']);
}

function applySearch(term: string): void {
    applyFilters({ ...props.filters, search: term });
}

function goToPage(page: number): void {
    const query = currentQuery();

    if (page > 1) {
        query.page = page;
    } else {
        delete query.page;
    }

    visit(query, RECIPIENT_PROPS);
}

function goToLogPage(page: number): void {
    const query = currentQuery();

    if (page > 1) {
        query.log_page = page;
    } else {
        delete query.log_page;
    }

    visit(query, ['logs', 'logsPagination'], logLoading);
}

// -------------------------------------------------------------- selection

const selected = ref<number[]>([]);
const allMatching = ref(false);

const selectedCount = computed(() =>
    allMatching.value
        ? props.recipientsPagination.total
        : selected.value.length,
);

// ---------------------------------------------------------------- dialogs

const sendOpen = ref(false);
const sendSelection = ref<MessageSendSelection>({ ids: [], all: false });

function openSend(ids?: number[]): void {
    if (ids !== undefined) {
        sendSelection.value = { ids, all: false };
    } else if (allMatching.value || selected.value.length === 0) {
        // Send Group Reminder with nothing ticked means everyone the
        // filters match, resolved on the server (REM-02).
        sendSelection.value = { ids: [], all: true };
    } else {
        sendSelection.value = { ids: [...selected.value], all: false };
    }

    sendOpen.value = true;
}

function onSent(): void {
    selected.value = [];
    allMatching.value = false;
    // The stat cards and the log changed; the table may have too.
    visit(currentQuery(), ['stats', 'logs', 'logsPagination']);
}

const templateOpen = ref(false);
const templateItem = ref<MessageTemplate | null>(null);
const templateReadonly = ref(false);

function openTemplate(
    template: MessageTemplate | null,
    readonly = false,
): void {
    templateItem.value = template;
    templateReadonly.value = readonly;
    templateOpen.value = true;
}

const ruleOpen = ref(false);
const ruleItem = ref<MessageAutomationRule | null>(null);

function openRule(rule: MessageAutomationRule | null): void {
    ruleItem.value = rule;
    ruleOpen.value = true;
}

function toggleRule(rule: MessageAutomationRule): void {
    router.patch(
        toggle.url(rule.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

// Safe delete for a template or a rule: the server refuses while sent
// reminders (or rules) point at it (REM-06, DATA-10). The log is never
// deletable.
type DeleteTarget =
    | { kind: 'template'; item: MessageTemplate }
    | { kind: 'rule'; item: MessageAutomationRule };

const deleteOpen = ref(false);
const deleteTarget = ref<DeleteTarget | null>(null);

const deleteUrl = computed(() => {
    const target = deleteTarget.value;

    if (target === null) {
        return null;
    }

    return target.kind === 'template'
        ? destroyTemplate.url(target.item.id)
        : destroyRule.url(target.item.id);
});

function askDelete(target: DeleteTarget): void {
    deleteTarget.value = target;
    deleteOpen.value = true;
}

const logOpen = ref(false);

// The "all" entries are filters, not audiences a rule can be limited to.
const ruleHotels = computed(() =>
    props.filters.hotels.filter((option) => option.value !== DEFAULTS.hotel),
);
const ruleDepartments = computed(() =>
    props.filters.departments.filter(
        (option) => option.value !== DEFAULTS.department,
    ),
);
</script>

<template>
    <Head :title="$t('Messages & Reminders')" />

    <div class="flex w-full min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">{{ $t('Messages & Reminders') }}</h1>

        <PageHeader
            :title="$t('Messages & Reminders')"
            :description="
                $t(
                    'Send training reminders, manage templates and review consent-aware delivery history.',
                )
            "
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <MessagesStatsRow :stats="stats" />

        <MessagesToolbar
            :filters="filters"
            :can-send="abilities.send"
            :selected-count="selectedCount"
            @filter="applyFilters"
            @send="openSend()"
        />

        <div class="flex w-full min-w-0 flex-col gap-3">
            <!-- Templates, rules and delivery history stay ahead of the full-width recipient list (REM-04, REM-06). -->
            <MessagesSidebarPanel
                class="lg:!grid lg:grid-cols-3 lg:items-start"
                :templates="templates"
                :automations="automations"
                :logs="logs"
                :logs-pagination="logsPagination"
                :abilities="abilities"
                @add-template="openTemplate(null)"
                @edit-template="openTemplate($event)"
                @preview-template="openTemplate($event, true)"
                @add-rule="openRule(null)"
                @edit-rule="openRule($event)"
                @toggle-rule="toggleRule"
                @delete-template="askDelete({ kind: 'template', item: $event })"
                @delete-rule="askDelete({ kind: 'rule', item: $event })"
                @log-page="goToLogPage"
                @open-log="logOpen = true"
            />

            <MessagesRecipientsPanel
                v-model:selected="selected"
                v-model:all-matching="allMatching"
                :recipients="recipients"
                :pagination="recipientsPagination"
                :search="filters.search"
                :can-send="abilities.send"
                :loading="loading"
                @search="applySearch"
                @page="goToPage"
                @send="openSend"
            />
        </div>
    </div>

    <MessagesSendDialog
        v-if="abilities.send"
        v-model:open="sendOpen"
        :selection="sendSelection"
        :total-matching="recipientsPagination.total"
        :filters="filters"
        :templates="templates"
        :channels="options.channels"
        @sent="onSent"
    />

    <MessagesTemplateDialog
        v-model:open="templateOpen"
        :template="templateItem"
        :variables="options.variables"
        :readonly="templateReadonly || !abilities.manageTemplates"
    />

    <MessagesRuleDialog
        v-if="abilities.manageRules"
        v-model:open="ruleOpen"
        :rule="ruleItem"
        :templates="templates"
        :triggers="options.triggers"
        :hotels="ruleHotels"
        :departments="ruleDepartments"
        :inactive-days="options.inactiveDays"
    />

    <DeleteRowDialog
        v-if="abilities.manageTemplates || abilities.manageRules"
        v-model:open="deleteOpen"
        :url="deleteUrl"
        :name="deleteTarget?.item.name ?? ''"
        :kind="
            deleteTarget?.kind === 'rule' ? $t('reminder rule') : $t('template')
        "
    />

    <MessagesLogDialog
        v-model:open="logOpen"
        :logs="logs"
        :pagination="logsPagination"
        :loading="logLoading"
        @page="goToLogPage"
    />
</template>
