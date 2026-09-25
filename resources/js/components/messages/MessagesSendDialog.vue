<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/messages-reminders';
import type {
    MessageFilterValues,
    MessageSelectOption,
    MessageSendSelection,
    MessageTemplate,
} from '@/types';

type Props = {
    selection: MessageSendSelection;
    /** How many employees the current filters match, for "select all". */
    totalMatching: number;
    filters: MessageFilterValues;
    templates: MessageTemplate[];
    channels: MessageSelectOption[];
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    sent: [];
}>();

const activeTemplates = computed(() =>
    props.templates.filter((template) => template.isActive),
);

const recipientCount = computed(() =>
    props.selection.all ? props.totalMatching : props.selection.ids.length,
);

const summary = computed(() => {
    if (props.selection.all) {
        return `Everyone matching the current filters: ${props.totalMatching} ${props.totalMatching === 1 ? 'employee' : 'employees'}.`;
    }

    const count = props.selection.ids.length;

    return count === 1
        ? '1 selected employee.'
        : `${count} selected employees.`;
});

const schedule = ref(false);
const scheduledFor = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        schedule.value = false;
        scheduledFor.value = '';
    }
});

// The earliest moment the picker accepts: now, in the browser's local time,
// to the minute. The server validates "after now" again.
const minSchedule = computed(() => {
    const now = new Date();
    now.setSeconds(0, 0);
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
});

const filterFields = computed(() => [
    ['hotel', props.filters.hotel],
    ['department', props.filters.department],
    ['consent', props.filters.consent],
    ['activity', props.filters.activity],
    ['search', props.filters.search],
]);

const fieldClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';

function onSuccess(): void {
    open.value = false;
    emit('sent');
}
</script>

<template>
    <MessagesModal
        v-model:open="open"
        title="Send Reminder"
        description="Email reminders reach only employees who gave consent; the rest are logged as blocked. In-app messages need no consent."
    >
        <Form
            :key="`${selection.all ? 'all' : selection.ids.join('-')}`"
            v-bind="send.form()"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <template v-if="selection.all">
                <input type="hidden" name="select_all" value="1" />
                <input
                    v-for="[name, value] in filterFields"
                    :key="name"
                    type="hidden"
                    :name="name"
                    :value="value"
                />
            </template>
            <template v-else>
                <input
                    v-for="id in selection.ids"
                    :key="id"
                    type="hidden"
                    name="recipients[]"
                    :value="id"
                />
            </template>

            <p
                class="bg-brand-50 text-brand-900 rounded-md px-3 py-2 text-[12.5px] leading-5"
                data-test="send-summary"
            >
                <span class="font-semibold">To:</span> {{ summary }}
            </p>
            <InputError :message="errors.recipients" />

            <div class="grid gap-1.5">
                <Label
                    for="send-template"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    Template
                </Label>
                <select
                    id="send-template"
                    name="template_id"
                    required
                    :class="fieldClass"
                    :aria-invalid="errors.template_id ? true : undefined"
                    data-test="send-template-select"
                >
                    <option
                        v-for="template in activeTemplates"
                        :key="template.id"
                        :value="template.id"
                    >
                        {{ template.name }}
                    </option>
                </select>
                <InputError :message="errors.template_id" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="send-channel"
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    Channel
                </Label>
                <select
                    id="send-channel"
                    name="channel"
                    required
                    :class="fieldClass"
                    :aria-invalid="errors.channel ? true : undefined"
                    data-test="send-channel-select"
                >
                    <option
                        v-for="channel in channels"
                        :key="channel.value"
                        :value="channel.value"
                    >
                        {{ channel.label }}
                    </option>
                </select>
                <InputError :message="errors.channel" />
            </div>

            <div class="grid gap-1.5">
                <label
                    class="text-brand-900 flex min-h-8 items-center gap-2 text-[12.5px] font-medium"
                >
                    <input
                        v-model="schedule"
                        type="checkbox"
                        class="accent-brand-600 size-4"
                        data-test="send-schedule-checkbox"
                    />
                    Schedule for later
                </label>
                <template v-if="schedule">
                    <Label
                        for="send-scheduled-for"
                        class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                    >
                        Send on
                    </Label>
                    <input
                        id="send-scheduled-for"
                        v-model="scheduledFor"
                        type="datetime-local"
                        name="scheduled_for"
                        required
                        :min="minSchedule"
                        :class="fieldClass"
                        :aria-invalid="errors.scheduled_for ? true : undefined"
                        data-test="send-scheduled-for-input"
                    />
                    <InputError :message="errors.scheduled_for" />
                </template>
            </div>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-send-button"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    :disabled="processing || recipientCount === 0"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="confirm-send-button"
                >
                    {{
                        schedule
                            ? `Schedule for ${recipientCount}`
                            : `Send to ${recipientCount}`
                    }}
                </Button>
            </div>
        </Form>
    </MessagesModal>
</template>
