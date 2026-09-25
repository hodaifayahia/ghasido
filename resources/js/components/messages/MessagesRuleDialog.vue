<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/messages-reminders/rules';
import type {
    MessageAutomationRule,
    MessageSelectOption,
    MessageTemplate,
} from '@/types';

type Props = {
    /** The rule being edited, or null to add one. */
    rule: MessageAutomationRule | null;
    templates: MessageTemplate[];
    triggers: MessageSelectOption[];
    /** Hotels the rule may be limited to (no "all" entry). */
    hotels: MessageSelectOption[];
    /** Departments the rule may be limited to (no "all" entry). */
    departments: MessageSelectOption[];
    inactiveDays: number;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const editing = computed(() => props.rule !== null);

const action = computed(() =>
    props.rule === null ? store.form() : update.form(props.rule.id),
);

const trigger = ref('inactive_days');
const isActive = ref(true);

watch(open, (isOpen) => {
    if (isOpen) {
        trigger.value = props.rule?.triggerKey ?? 'inactive_days';
        isActive.value = props.rule?.active ?? true;
    }
});

const daysLabel = computed(() =>
    trigger.value === 'inactive_days'
        ? 'Days without activity'
        : 'Days before the same employee is reminded again',
);

const daysPlaceholder = computed(() =>
    trigger.value === 'inactive_days' ? String(props.inactiveDays) : '7',
);

function isChecked(list: number[] | undefined, value: string): boolean {
    return (list ?? []).includes(Number(value));
}

const fieldClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';

function onSuccess(): void {
    open.value = false;
    emit('saved');
}
</script>

<template>
    <MessagesModal
        v-model:open="open"
        :title="editing ? `Edit ${rule?.name ?? 'rule'}` : 'Add Rule'"
        description="Runs once a day. Recipients come from the trigger, narrowed to the audience; leave both lists empty to reach every hotel and department."
        class="sm:max-w-[620px]"
    >
        <Form
            :key="rule?.id ?? 'create'"
            v-bind="action"
            :options="{ preserveScroll: true, preserveState: true }"
            class="mt-2 grid gap-4"
            v-slot="{ errors, processing }"
            @success="onSuccess"
        >
            <div class="grid gap-1.5">
                <Label for="rule-name" :class="labelClass">Rule name</Label>
                <Input
                    id="rule-name"
                    name="name"
                    required
                    maxlength="120"
                    :default-value="rule?.name ?? ''"
                    :aria-invalid="errors.name ? true : undefined"
                    data-test="rule-name-input"
                    :class="[fieldClass, 'shadow-none']"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="rule-trigger" :class="labelClass">
                        Trigger
                    </Label>
                    <select
                        id="rule-trigger"
                        v-model="trigger"
                        name="trigger"
                        required
                        :class="fieldClass"
                        :aria-invalid="errors.trigger ? true : undefined"
                        data-test="rule-trigger-select"
                    >
                        <option
                            v-for="option in triggers"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.trigger" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="rule-days" :class="labelClass">
                        {{ daysLabel }}
                    </Label>
                    <Input
                        id="rule-days"
                        name="days"
                        type="number"
                        min="1"
                        max="365"
                        :placeholder="daysPlaceholder"
                        :default-value="
                            rule?.days === null ? '' : String(rule?.days ?? '')
                        "
                        :aria-invalid="errors.days ? true : undefined"
                        data-test="rule-days-input"
                        :class="[fieldClass, 'shadow-none']"
                    />
                    <InputError :message="errors.days" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="rule-template" :class="labelClass">
                    Template to send
                </Label>
                <select
                    id="rule-template"
                    name="template_id"
                    required
                    :class="fieldClass"
                    :aria-invalid="errors.template_id ? true : undefined"
                    data-test="rule-template-select"
                >
                    <option
                        v-for="template in templates"
                        :key="template.id"
                        :value="template.id"
                        :selected="template.id === rule?.templateId"
                    >
                        {{ template.name }}
                        {{ template.isActive ? '' : '(inactive)' }}
                    </option>
                </select>
                <InputError :message="errors.template_id" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <fieldset class="grid gap-1.5">
                    <legend :class="labelClass">Hotels</legend>
                    <div
                        class="border-line max-h-40 overflow-y-auto rounded-sm border px-3 py-2"
                    >
                        <p
                            v-if="hotels.length === 0"
                            class="text-ink-faint text-[12px]"
                        >
                            No hotels yet.
                        </p>
                        <label
                            v-for="hotel in hotels"
                            :key="hotel.value"
                            class="text-ink flex min-h-7 items-center gap-2 text-[12.5px]"
                        >
                            <input
                                type="checkbox"
                                name="hotel_ids[]"
                                :value="hotel.value"
                                :checked="
                                    isChecked(rule?.hotelIds, hotel.value)
                                "
                                class="accent-brand-600 size-4"
                            />
                            {{ hotel.label }}
                        </label>
                    </div>
                    <InputError :message="errors.hotel_ids" />
                </fieldset>

                <fieldset class="grid gap-1.5">
                    <legend :class="labelClass">Departments</legend>
                    <div
                        class="border-line max-h-40 overflow-y-auto rounded-sm border px-3 py-2"
                    >
                        <p
                            v-if="departments.length === 0"
                            class="text-ink-faint text-[12px]"
                        >
                            No departments yet.
                        </p>
                        <label
                            v-for="department in departments"
                            :key="department.value"
                            class="text-ink flex min-h-7 items-center gap-2 text-[12.5px]"
                        >
                            <input
                                type="checkbox"
                                name="department_ids[]"
                                :value="department.value"
                                :checked="
                                    isChecked(
                                        rule?.departmentIds,
                                        department.value,
                                    )
                                "
                                class="accent-brand-600 size-4"
                            />
                            {{ department.label }}
                        </label>
                    </div>
                    <InputError :message="errors.department_ids" />
                </fieldset>
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
                    data-test="rule-active-checkbox"
                />
                Active: runs on the next daily pass
            </label>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="cancel-rule-button"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="save-rule-button"
                >
                    {{ editing ? 'Save changes' : 'Add rule' }}
                </Button>
            </div>
        </Form>
    </MessagesModal>
</template>
