<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/roles';
import type { RolePermissionGroup, RoleRecord } from '@/types';

type Props = {
    /** The role being edited, or null to create a new one. */
    role: RoleRecord | null;
    permissionGroups: RolePermissionGroup[];
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [];
}>();

const editing = computed(() => props.role !== null);
const locked = computed(() => props.role?.locked ?? false);
const isSystem = computed(() => props.role?.isSystem ?? false);

const description = computed(() => {
    if (locked.value) {
        return 'The Super Admin always holds every permission, so this set is fixed.';
    }
    if (isSystem.value) {
        return 'Adjust the permissions for this built-in role. Its name is fixed.';
    }
    if (editing.value) {
        return 'Rename the role or change the permissions it grants.';
    }

    return 'Name the role and choose the permissions it grants.';
});

const form = useForm<{ name: string; permissions: string[] }>({
    name: '',
    permissions: [],
});

// Refill from the selected role every time the dialog opens, so editing one
// role then another never carries the first one's ticks over.
watch(
    () => [props.role, open.value] as const,
    ([role, isOpen]) => {
        if (!isOpen) {
            return;
        }

        form.name = role?.name ?? '';
        form.permissions = role ? [...role.permissions] : [];
        form.clearErrors();
    },
    { immediate: true },
);

function has(name: string): boolean {
    return form.permissions.includes(name);
}

function toggle(name: string, checked: boolean | 'indeterminate'): void {
    if (locked.value) {
        return;
    }

    if (checked === true) {
        if (!has(name)) {
            form.permissions = [...form.permissions, name];
        }
    } else {
        form.permissions = form.permissions.filter((value) => value !== name);
    }
}

function groupState(group: RolePermissionGroup): boolean | 'indeterminate' {
    const names = group.permissions.map((permission) => permission.name);
    const selected = names.filter((name) => has(name)).length;

    if (selected === 0) {
        return false;
    }

    return selected === names.length ? true : 'indeterminate';
}

function toggleGroup(
    group: RolePermissionGroup,
    checked: boolean | 'indeterminate',
): void {
    if (locked.value) {
        return;
    }

    const names = group.permissions.map((permission) => permission.name);

    if (checked === true) {
        form.permissions = Array.from(new Set([...form.permissions, ...names]));
    } else {
        form.permissions = form.permissions.filter(
            (value) => !names.includes(value),
        );
    }
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('saved');
        },
    };

    if (props.role !== null) {
        form.put(update(String(props.role.id)).url, options);
    } else {
        form.post(store().url, options);
    }
}

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="editing ? `Edit ${role?.label ?? 'role'}` : 'Add role'"
        :description="description"
    >
        <form class="mt-2 grid gap-5" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="role-name" :class="labelClass">Role name</Label>
                <Input
                    id="role-name"
                    v-model="form.name"
                    type="text"
                    maxlength="60"
                    required
                    :disabled="isSystem"
                    :aria-invalid="form.errors.name ? true : undefined"
                    autocomplete="off"
                    data-test="role-name-input"
                    :class="inputClass"
                />
                <p v-if="isSystem" class="text-ink-slate text-[12px]">
                    Built-in roles keep their name; you can still change what
                    they can do.
                </p>
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-3">
                <div class="flex items-center justify-between">
                    <span :class="labelClass">Permissions</span>
                    <span class="text-ink-slate text-[12px]">
                        {{ form.permissions.length }} selected
                    </span>
                </div>

                <div class="border-line divide-line divide-y rounded-lg border">
                    <fieldset
                        v-for="group in permissionGroups"
                        :key="group.group"
                        class="p-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <Checkbox
                                :id="`group-${group.group}`"
                                :model-value="groupState(group)"
                                :disabled="locked"
                                @update:model-value="toggleGroup(group, $event)"
                            />
                            <Label
                                :for="`group-${group.group}`"
                                class="text-ink text-[13px] font-semibold"
                            >
                                {{ group.group }}
                            </Label>
                        </div>

                        <div class="mt-2.5 grid gap-2 ps-6 sm:grid-cols-2">
                            <div
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="flex items-center gap-2.5"
                            >
                                <Checkbox
                                    :id="`perm-${permission.name}`"
                                    :model-value="has(permission.name)"
                                    :disabled="locked"
                                    @update:model-value="
                                        toggle(permission.name, $event)
                                    "
                                />
                                <Label
                                    :for="`perm-${permission.name}`"
                                    class="text-ink-slate text-[13px]"
                                >
                                    {{ permission.label }}
                                </Label>
                            </div>
                        </div>
                    </fieldset>
                </div>
                <InputError :message="form.errors.permissions" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 rounded-md"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 rounded-md text-white"
                    :disabled="form.processing"
                    data-test="save-role-button"
                >
                    {{ editing ? 'Save changes' : 'Create role' }}
                </Button>
            </div>
        </form>
    </HotelsModal>
</template>
