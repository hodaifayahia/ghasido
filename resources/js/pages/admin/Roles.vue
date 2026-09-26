<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import RoleFormDialog from '@/components/roles/RoleFormDialog.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { cn } from '@/lib/utils';
import { dashboard, roles as rolesRoute } from '@/routes';
import { destroy } from '@/routes/roles';
import type { RolePermissionGroup, RoleRecord } from '@/types';

type Props = {
    roles: RoleRecord[];
    permissionGroups: RolePermissionGroup[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Roles & Permissions', href: rolesRoute() },
        ],
    },
});

const { can } = useCan();
const canManage = can('roles.manage');

const totalPermissions = computed(() =>
    props.permissionGroups.reduce(
        (total, group) => total + group.permissions.length,
        0,
    ),
);

const formOpen = ref(false);
const formRole = ref<RoleRecord | null>(null);

function addRole(): void {
    formRole.value = null;
    formOpen.value = true;
}

function editRole(role: RoleRecord): void {
    formRole.value = role;
    formOpen.value = true;
}

function onSaved(): void {
    router.reload({ only: ['roles'] });
}

const deleteOpen = ref(false);
const deleteRole = ref<RoleRecord | null>(null);

function confirmDelete(role: RoleRecord): void {
    deleteRole.value = role;
    deleteOpen.value = true;
}

function performDelete(): void {
    if (deleteRole.value === null) {
        return;
    }

    router.delete(destroy(deleteRole.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
        },
    });
}

function badgeClass(role: RoleRecord): string {
    return role.isSystem
        ? 'bg-brand-50 text-brand-700'
        : 'bg-success-tint text-success-text';
}
</script>

<template>
    <Head title="Roles & Permissions" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            title="Roles & Permissions"
            description="Define what each role can do. Built-in roles are fixed; add your own for anything in between."
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <PanelCard
            title="Roles"
            title-id="roles-list-title"
            body-class="-mx-4 -mb-4 mt-3"
        >
            <template v-if="canManage" #actions>
                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-white"
                    data-test="add-role-button"
                    @click="addRole"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Add role
                </Button>
            </template>

            <div class="min-w-0 overflow-hidden rounded-b-lg">
                <table
                    class="w-full table-fixed border-collapse text-start text-[13px]"
                >
                    <caption class="sr-only">
                        Roles, account counts, permission coverage and actions
                    </caption>
                    <thead
                        class="bg-app text-ink-slate text-[11px] tracking-wide uppercase"
                    >
                        <tr>
                            <th
                                scope="col"
                                class="w-[68%] px-3 py-2.5 sm:w-[48%] sm:px-4"
                            >
                                Role
                            </th>
                            <th
                                scope="col"
                                class="hidden px-3 py-2.5 text-end sm:table-cell sm:w-[14%] sm:px-4"
                            >
                                Accounts
                            </th>
                            <th
                                scope="col"
                                class="hidden px-3 py-2.5 text-end sm:table-cell sm:w-[16%] sm:px-4"
                            >
                                Permissions
                            </th>
                            <th
                                scope="col"
                                class="w-[32%] px-3 py-2.5 text-end sm:w-[22%] sm:px-4"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="role in roles"
                            :key="role.id"
                            class="border-line text-ink hover:bg-app/60 border-t transition-colors"
                        >
                            <th
                                scope="row"
                                class="px-3 py-3 text-start font-normal sm:px-4"
                            >
                                <div class="flex min-w-0 items-start gap-2.5">
                                    <span
                                        class="bg-brand-50 text-brand-600 mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg"
                                    >
                                        <ShieldCheck
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div class="min-w-0">
                                        <div
                                            class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"
                                        >
                                            <span
                                                class="font-heading text-brand-800 font-semibold"
                                            >
                                                {{ role.label }}
                                            </span>
                                            <span
                                                :class="
                                                    cn(
                                                        'inline-flex h-5 shrink-0 items-center rounded-[5px] px-1.5 text-[10px] font-semibold',
                                                        badgeClass(role),
                                                    )
                                                "
                                            >
                                                {{
                                                    role.isSystem
                                                        ? 'System'
                                                        : 'Custom'
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            class="text-ink-faint mt-0.5 text-[11px] leading-4"
                                        >
                                            {{ role.name }}
                                            <span class="sm:hidden">
                                                · {{ role.userCount }} accounts
                                                ·
                                                {{
                                                    role.locked
                                                        ? totalPermissions
                                                        : role.permissions
                                                              .length
                                                }}
                                                permissions
                                            </span>
                                        </p>
                                        <p
                                            v-if="role.locked"
                                            class="text-ink-slate mt-0.5 text-[11px]"
                                        >
                                            Holds every permission by design.
                                        </p>
                                    </div>
                                </div>
                            </th>
                            <td
                                class="hidden px-3 py-3 text-end tabular-nums sm:table-cell sm:px-4"
                            >
                                {{ role.userCount }}
                            </td>
                            <td
                                class="hidden px-3 py-3 text-end tabular-nums sm:table-cell sm:px-4"
                            >
                                <span class="font-semibold">{{
                                    role.locked
                                        ? totalPermissions
                                        : role.permissions.length
                                }}</span>
                                <span class="text-ink-slate">
                                    / {{ totalPermissions }}</span
                                >
                            </td>
                            <td class="px-2 py-3 sm:px-4">
                                <div
                                    v-if="canManage"
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="border-line text-ink h-8 gap-1.5 rounded-md px-2 sm:h-9 sm:px-2.5"
                                        :data-test="`edit-role-${role.id}-button`"
                                        :aria-label="`${role.locked ? 'View' : 'Edit'} ${role.label}`"
                                        @click="editRole(role)"
                                    >
                                        <component
                                            :is="role.locked ? Eye : Pencil"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span class="hidden 2xl:inline">{{
                                            role.locked ? 'View' : 'Edit'
                                        }}</span>
                                    </Button>
                                    <Button
                                        v-if="!role.isSystem"
                                        type="button"
                                        variant="outline"
                                        class="border-danger/40 text-danger hover:bg-danger-tint size-8 rounded-md p-0 2xl:h-9 2xl:w-auto 2xl:gap-1.5 2xl:px-2.5"
                                        :data-test="`delete-role-${role.id}-button`"
                                        :aria-label="`Delete ${role.label}`"
                                        @click="confirmDelete(role)"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span class="hidden 2xl:inline"
                                            >Delete</span
                                        >
                                    </Button>
                                </div>
                                <span
                                    v-else
                                    class="text-ink-slate block text-end text-[12px]"
                                >
                                    {{ role.isSystem ? 'Built in' : 'Custom' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </PanelCard>
    </div>

    <RoleFormDialog
        v-if="canManage"
        v-model:open="formOpen"
        :role="formRole"
        :permission-groups="permissionGroups"
        @saved="onSaved"
    />

    <HotelsModal
        v-if="canManage"
        v-model:open="deleteOpen"
        title="Delete role"
        :description="`Delete the ${deleteRole?.label ?? ''} role? This cannot be undone.`"
    >
        <div class="mt-2 flex items-center justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                class="h-10 rounded-md"
                @click="deleteOpen = false"
            >
                Cancel
            </Button>
            <Button
                type="button"
                class="bg-danger hover:bg-danger/90 h-10 rounded-md text-white"
                data-test="confirm-delete-role-button"
                @click="performDelete"
            >
                Delete role
            </Button>
        </div>
    </HotelsModal>
</template>
