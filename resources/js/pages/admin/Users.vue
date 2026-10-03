<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import UserFormDialog from '@/components/users/UserFormDialog.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { dashboard } from '@/routes';
import { tk } from '@/lib/i18n';

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

type Props = {
    accounts: {
        data: AppAccount[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    roles: UserRole[];
    /** Where a Hotel Admin or a hotel-bound custom role works. */
    hotels: { value: number; label: string }[];
};

const props = defineProps<Props>();
const { can } = useCan();
const canManage = can('users.manage');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Users'), href: '/users' },
        ],
    },
});

const modalOpen = ref(false);
const selectedAccount = ref<AppAccount | null>(null);
const activeCount = computed(
    () =>
        props.accounts.data.filter((account) => account.status === 'active')
            .length,
);

function addUser(): void {
    selectedAccount.value = null;
    modalOpen.value = true;
}

function editUser(account: AppAccount): void {
    selectedAccount.value = account;
    modalOpen.value = true;
}
</script>

<template>
    <Head :title="$t('Users')" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            :title="$t('Users')"
            :description="
                $t('Manage app access for the people helping you run GHASIDO.')
            "
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <PanelCard
            :title="$t('App users')"
            title-id="app-users-title"
            body-class="-mx-4 -mb-4 mt-3"
        >
            <template #actions>
                <span class="text-ink-slate hidden text-[12px] sm:inline">
                    {{
                        $t(':active active on this page · :total total', {
                            active: activeCount,
                            total: accounts.total,
                        })
                    }}
                </span>
                <Button
                    v-if="canManage"
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-white"
                    data-test="add-app-user-button"
                    @click="addUser"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    <span class="hidden sm:inline">{{ $t('Add user') }}</span>
                    <span class="sm:hidden">{{ $t('Add') }}</span>
                </Button>
            </template>

            <div class="min-w-0 overflow-hidden rounded-b-lg">
                <table
                    class="w-full table-fixed border-collapse text-start text-[13px]"
                >
                    <caption class="sr-only">
                        {{
                            $t(
                                'App users, their access roles, and account status',
                            )
                        }}
                    </caption>
                    <colgroup>
                        <col class="w-[43%] sm:w-[38%]" />
                        <col class="w-[26%] sm:w-[30%]" />
                        <col class="w-[17%] sm:w-[17%]" />
                        <col class="w-[14%] sm:w-[15%]" />
                    </colgroup>
                    <thead
                        class="bg-app text-ink-slate text-[10px] tracking-wide uppercase sm:text-[11px]"
                    >
                        <tr>
                            <th scope="col" class="px-2 py-2.5 sm:px-4">
                                {{ $t('Account') }}
                            </th>
                            <th scope="col" class="px-2 py-2.5 sm:px-4">
                                {{ $t('Access') }}
                            </th>
                            <th scope="col" class="px-2 py-2.5 sm:px-4">
                                {{ $t('Status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-2 py-2.5 text-end sm:px-4"
                            >
                                {{ $t('Edit') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="account in accounts.data"
                            :key="account.id"
                            class="border-line text-ink border-t"
                        >
                            <th
                                scope="row"
                                class="px-2 py-3 text-start font-normal sm:px-4"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="bg-brand-50 text-brand-600 hidden size-8 shrink-0 place-items-center rounded-lg sm:grid"
                                    >
                                        <UserRound
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="font-heading block truncate text-[12px] font-semibold sm:text-[13px]"
                                            >{{ account.name }}</span
                                        >
                                        <span
                                            class="text-ink-slate block truncate text-[10px] sm:text-[11px]"
                                            >{{
                                                account.username
                                                    ? `@${account.username}`
                                                    : account.email
                                            }}</span
                                        >
                                    </span>
                                </div>
                            </th>
                            <td class="px-2 py-3 sm:px-4">
                                <span
                                    class="text-brand-800 block truncate font-medium"
                                    :title="
                                        account.role?.label ??
                                        $t('No role assigned')
                                    "
                                >
                                    {{ account.role?.label ?? $t('No role') }}
                                </span>
                                <span
                                    v-if="account.hotelName"
                                    class="text-brand-700 block truncate text-[10px] font-semibold"
                                    :title="account.hotelName"
                                >
                                    {{ account.hotelName }}
                                </span>
                                <span
                                    class="text-ink-slate hidden text-[10px] sm:block"
                                >
                                    {{
                                        account.role?.name === 'super_admin'
                                            ? $t('Full platform access')
                                            : $tc(
                                                  ':count permission|:count permissions',
                                                  account.role
                                                      ?.permissionCount ?? 0,
                                              )
                                    }}
                                </span>
                            </td>
                            <td class="px-2 py-3 sm:px-4">
                                <span
                                    :class="
                                        account.status === 'active'
                                            ? 'bg-success-tint text-success-text'
                                            : 'bg-app text-ink-slate'
                                    "
                                    class="inline-flex rounded-[5px] px-1.5 py-1 text-[9px] font-semibold capitalize sm:px-2 sm:text-[10px]"
                                >
                                    {{
                                        account.status === 'active'
                                            ? $t('Active')
                                            : $t('Inactive')
                                    }}
                                </span>
                            </td>
                            <td class="px-1.5 py-3 text-end sm:px-4">
                                <Button
                                    v-if="canManage"
                                    type="button"
                                    variant="outline"
                                    class="border-line text-ink size-8 rounded-md p-0 sm:h-9 sm:w-auto sm:gap-1.5 sm:px-2.5"
                                    :data-test="`edit-app-user-${account.id}-button`"
                                    :aria-label="
                                        $t('Edit :name', { name: account.name })
                                    "
                                    @click="editUser(account)"
                                >
                                    <Pencil
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span class="hidden sm:inline">{{
                                        $t('Edit')
                                    }}</span>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="accounts.data.length === 0">
                            <td
                                colspan="4"
                                class="text-ink-slate px-4 py-10 text-center"
                            >
                                {{
                                    $t(
                                        'No app users yet. Add an account to choose who can help manage GHASIDO.',
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="accounts.last_page > 1"
                :aria-label="$t('User pages')"
                class="border-line flex flex-wrap items-center justify-center gap-1 border-t px-3 py-3"
            >
                <button
                    v-for="link in accounts.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    :class="
                        link.active
                            ? 'bg-brand-600 text-white'
                            : 'border-line text-ink-slate hover:bg-app disabled:opacity-40'
                    "
                    class="min-w-8 rounded-md border px-2 py-1.5 text-[11px]"
                    @click="
                        link.url &&
                        router.get(link.url, {}, { preserveScroll: true })
                    "
                >
                    <span v-if="link.label.includes('Previous')">{{
                        $t('Previous')
                    }}</span>
                    <span v-else-if="link.label.includes('Next')">{{
                        $t('Next')
                    }}</span>
                    <span v-else>{{
                        link.label.replace(/&laquo;|&raquo;/g, '')
                    }}</span>
                </button>
            </nav>
        </PanelCard>
    </div>

    <UserFormDialog
        v-if="canManage"
        v-model:open="modalOpen"
        :account="selectedAccount"
        :roles="roles"
        :hotels="hotels"
    />
</template>
