<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type UserRole = {
    id: number;
    name: string;
    label: string;
    permissionCount: number;
};

type AppAccount = {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
    status: string;
    role: UserRole | null;
    isCurrentUser: boolean;
};

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    account: AppAccount | null;
    roles: UserRole[];
}>();

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role_id: '',
    status: 'active',
});

watch(
    () => [props.account, open.value] as const,
    ([account, isOpen]) => {
        if (!isOpen) return;

        form.name = account?.name ?? '';
        form.username = account?.username ?? '';
        form.email = account?.email ?? '';
        form.password = '';
        form.role_id = account?.role ? String(account.role.id) : '';
        form.status = account?.status ?? 'active';
        form.clearErrors();
    },
    { immediate: true },
);

const editing = () => props.account !== null;
const passwordHint = () =>
    editing()
        ? 'Leave blank to keep the current password.'
        : 'At least 8 characters. Share it with the account owner securely.';

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.account !== null) {
        form.put(`/users/${props.account.id}`, options);
    } else {
        form.post('/users', options);
    }
}

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="editing() ? 'Edit app user' : 'Add app user'"
        description="Choose a role to control which parts of GHASIDO this account can access."
    >
        <form class="mt-2 grid gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="app-user-name" :class="labelClass">Full name</Label>
                <Input
                    id="app-user-name"
                    v-model="form.name"
                    required
                    maxlength="120"
                    autocomplete="name"
                    :aria-invalid="form.errors.name ? true : undefined"
                    :class="inputClass"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-1.5 sm:grid-cols-2 sm:gap-3">
                <div class="grid min-w-0 gap-1.5">
                    <Label for="app-user-username" :class="labelClass"
                        >Username</Label
                    >
                    <Input
                        id="app-user-username"
                        v-model="form.username"
                        required
                        minlength="3"
                        maxlength="40"
                        autocomplete="username"
                        spellcheck="false"
                        :aria-invalid="form.errors.username ? true : undefined"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.username" />
                </div>
                <div class="grid min-w-0 gap-1.5">
                    <Label for="app-user-email" :class="labelClass"
                        >Email
                        <span class="text-ink-slate font-normal"
                            >(optional)</span
                        ></Label
                    >
                    <Input
                        id="app-user-email"
                        v-model="form.email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        :aria-invalid="form.errors.email ? true : undefined"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.email" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="app-user-password" :class="labelClass">
                    {{ editing() ? 'New password' : 'Initial password' }}
                </Label>
                <Input
                    id="app-user-password"
                    v-model="form.password"
                    type="password"
                    :required="!editing()"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    :aria-invalid="form.errors.password ? true : undefined"
                    :class="inputClass"
                />
                <p class="text-ink-slate text-[11px] leading-4">
                    {{ passwordHint() }}
                </p>
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label for="app-user-role" :class="labelClass"
                    >Access role</Label
                >
                <select
                    id="app-user-role"
                    v-model="form.role_id"
                    required
                    :aria-invalid="form.errors.role_id ? true : undefined"
                    :class="inputClass"
                >
                    <option disabled value="">Choose a role</option>
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="String(role.id)"
                    >
                        {{ role.label }} —
                        {{
                            role.name === 'super_admin'
                                ? 'Full platform access'
                                : `${role.permissionCount} permissions`
                        }}
                    </option>
                </select>
                <p class="text-ink-slate text-[11px] leading-4">
                    Adjust permissions in
                    <a
                        href="/roles"
                        class="text-brand-700 underline underline-offset-2"
                        >Roles &amp; Permissions</a
                    >.
                </p>
                <InputError :message="form.errors.role_id" />
            </div>

            <div v-if="editing()" class="grid gap-1.5">
                <Label for="app-user-status" :class="labelClass"
                    >Account status</Label
                >
                <select
                    id="app-user-status"
                    v-model="form.status"
                    :disabled="props.account?.isCurrentUser"
                    :class="inputClass"
                >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <p
                    v-if="props.account?.isCurrentUser"
                    class="text-ink-slate text-[11px] leading-4"
                >
                    You cannot deactivate your own account.
                </p>
                <InputError :message="form.errors.status" />
            </div>

            <div
                class="flex flex-col-reverse justify-end gap-2 pt-1 sm:flex-row"
            >
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
                >
                    {{ editing() ? 'Save changes' : 'Add user' }}
                </Button>
            </div>
        </form>
    </HotelsModal>
</template>
