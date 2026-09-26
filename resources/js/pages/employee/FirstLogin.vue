<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FirstLoginForm from '@/components/learning/FirstLoginForm.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import type { FirstLoginUser } from '@/types';

/*
 * First login (AUTH-04, AUTH-05, PRIV-01, PRIV-02; spec 0003 Part E). No
 * client mockup covers this page yet.
 */
type Props = {
    user: FirstLoginUser;
    requireEmail: boolean;
    hotelName: string | null;
    departmentName: string | null;
};

defineProps<Props>();
</script>

<template>
    <Head :title="$t('Welcome')" />
    <h1 class="sr-only">{{ $t('Welcome') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('Welcome to GHASIDO')"
            :description="
                $t('A moment to set up your account before your first lesson.')
            "
        />

        <FirstLoginForm
            :user="user"
            :require-email="requireEmail"
            :hotel-name="hotelName"
            :department-name="departmentName"
            class="max-w-2xl"
        />
    </div>
</template>
