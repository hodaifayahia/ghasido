<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import TransText from '@/components/common/TransText.vue';
import InputError from '@/components/InputError.vue';
import { useI18n } from '@/composables/useI18n';
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
    hotelId: number | null;
    hotelName: string | null;
    isCurrentUser: boolean;
};

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    account: AppAccount | null;
    roles: UserRole[];
    hotels: { value: number; label: string }[];
}>();

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role_id: '',
    hotel_id: '',
    status: 'active',
});

// A Hotel Admin works for one hotel; a custom role may or may not; the Super
// Admin for none (client report 2026-10-02).
const chosenRole = computed(
    () => props.roles.find((role) => String(role.id) === form.role_id) ?? null,
);
const showsHotel = computed(
    () => chosenRole.value !== null && chosenRole.value.name !== 'super_admin',
);
const needsHotel = computed(() => chosenRole.value?.name === 'admin');

watch(
    () => [props.account, open.value] as const,
    ([account, isOpen]) => {
        if (!isOpen) return;

        form.name = account?.name ?? '';
        form.username = account?.username ?? '';
        form.email = account?.email ?? '';
        form.password = '';
        form.role_id = account?.role ? String(account.role.id) : '';
        form.hotel_id = account?.hotelId ? String(account.hotelId) : '';
        form.status = account?.status ?? 'active';
        form.clearErrors();
    },
    { immediate: true },
);

const { t } = useI18n();

const editing = () => props.account !== null;
const passwordHint = () =>
    editing()
        ? t('Leave blank to keep the current password.')
        : t('At least 8 characters. Share it with the account owner securely.');

function withHotel<T extends { hotel_id: string }>(data: T) {
    return {
        ...data,
        hotel_id: showsHotel.value ? data.hotel_id || null : null,
    };
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.account !== null) {
        form.transform(withHotel).put(`/users/${props.account.id}`, options);
    } else {
        form.transform(withHotel).post('/users', options);
    }
}

const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
const inputClass =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 rounded-sm text-[13px] shadow-none focus-visible:ring-3';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="editing() ? $t('Edit app user') : $t('Add app user')"
        :description="
            $t(
                'Choose a role to control which parts of GHASIDO this account can access.',
            )
        "
    >
        <form class="mt-2 grid gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="app-user-name" :class="labelClass">{{
                    $t('Full name')
                }}</Label>
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
                    <Label for="app-user-username" :class="labelClass">{{
                        $t('Username')
                    }}</Label>
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
                        >{{ $t('Email') }}
                        <span class="text-ink-slate font-normal">{{
                            $t('(optional)')
                        }}</span></Label
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
                    {{
                        editing() ? $t('New password') : $t('Initial password')
                    }}
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
                <Label for="app-user-role" :class="labelClass">{{
                    $t('Access role')
                }}</Label>
                <select
                    id="app-user-role"
                    v-model="form.role_id"
                    required
                    :aria-invalid="form.errors.role_id ? true : undefined"
                    :class="inputClass"
                >
                    <option disabled value="">{{ $t('Choose a role') }}</option>
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="String(role.id)"
                    >
                        {{ role.label }} —
                        {{
                            role.name === 'super_admin'
                                ? $t('Full platform access')
                                : $tc(
                                      ':count permission|:count permissions',
                                      role.permissionCount,
                                  )
                        }}
                    </option>
                </select>
                <TransText
                    tag="p"
                    text="Adjust permissions in :link."
                    class="text-ink-slate text-[11px] leading-4"
                >
                    <template #link>
                        <a
                            href="/roles"
                            class="text-brand-700 underline underline-offset-2"
                            >{{ $t('Roles & Permissions') }}</a
                        >
                    </template>
                </TransText>
                <InputError :message="form.errors.role_id" />
            </div>

            <div v-if="showsHotel" class="grid gap-1.5">
                <Label for="app-user-hotel" :class="labelClass"
                    >{{ $t('Hotel') }}
                    <span
                        v-if="!needsHotel"
                        class="text-ink-slate font-normal"
                        >{{ $t('(optional)') }}</span
                    ></Label
                >
                <select
                    id="app-user-hotel"
                    v-model="form.hotel_id"
                    :required="needsHotel"
                    :aria-invalid="form.errors.hotel_id ? true : undefined"
                    data-test="app-user-hotel"
                    :class="inputClass"
                >
                    <option value="">
                        {{
                            needsHotel
                                ? $t('Choose a hotel')
                                : $t('No hotel (platform team)')
                        }}
                    </option>
                    <option
                        v-for="hotel in hotels"
                        :key="hotel.value"
                        :value="String(hotel.value)"
                    >
                        {{ hotel.label }}
                    </option>
                </select>
                <p class="text-ink-slate text-[11px] leading-4">
                    {{
                        needsHotel
                            ? $t(
                                  'A Hotel Admin manages this hotel only: its employees, departments, AI points and reports.',
                              )
                            : $t(
                                  'Pick a hotel to limit this role to that hotel, or leave it empty for the platform team.',
                              )
                    }}
                </p>
                <InputError :message="form.errors.hotel_id" />
            </div>

            <div v-if="editing()" class="grid gap-1.5">
                <Label for="app-user-status" :class="labelClass">{{
                    $t('Account status')
                }}</Label>
                <select
                    id="app-user-status"
                    v-model="form.status"
                    :disabled="props.account?.isCurrentUser"
                    :class="inputClass"
                >
                    <option value="active">{{ $t('Active') }}</option>
                    <option value="inactive">{{ $t('Inactive') }}</option>
                </select>
                <p
                    v-if="props.account?.isCurrentUser"
                    class="text-ink-slate text-[11px] leading-4"
                >
                    {{ $t('You cannot deactivate your own account.') }}
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
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 h-10 rounded-md text-white"
                    :disabled="form.processing"
                >
                    {{ editing() ? $t('Save changes') : $t('Add user') }}
                </Button>
            </div>
        </form>
    </HotelsModal>
</template>
