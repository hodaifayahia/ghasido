<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AiPointsMeter from '@/components/shell/AiPointsMeter.vue';
import LanguageSelect from '@/components/shell/LanguageSelect.vue';
import NotificationMenu from '@/components/shell/NotificationMenu.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useI18n } from '@/composables/useI18n';
import { useInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Props = {
    class?: HTMLAttributes['class'];
    topbarTaglineSrc?: string;
};

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();

// The role line under the greeting follows each signed-in user's role (ROLE-01).
const roleLabel = computed((): string => {
    switch (user.value.role) {
        case 'employee':
            return user.value.department_name
                ? t(':department Department', {
                      department: user.value.department_name,
                  })
                : t('Employee');
        case 'manager':
            return t('Hotel Manager');
        case 'admin':
            return t('Hotel Administrator');
        default:
            return t('System Administrator');
    }
});

const { getInitials } = useInitials();
const { isMobile, state } = useSidebar();

const firstName = computed(
    () => user.value.name.trim().split(/\s+/u)[0] ?? user.value.name,
);
const initial = computed(() => getInitials(firstName.value));
const hasAvatar = computed(() => Boolean(user.value.avatar));

const iconButtonClass =
    'rounded-md transition-colors duration-150 ease-brand hover:bg-brand-50 focus-visible:ring-2 focus-visible:ring-brand-600/40 focus-visible:outline-none data-[state=open]:bg-brand-50';
</script>

<template>
    <header
        :class="
            cn(
                'md:h-topbar bg-surface relative sticky top-0 z-9 flex h-14 shrink-0 items-center gap-1 ps-1 pe-2 md:gap-1.5 md:ps-4 md:pe-5 md:pt-[3px] xl:ps-6 xl:pe-[22px]',
                props.class,
            )
        "
    >
        <!-- Keep sidebar access on narrow screens; notification and account
             actions stay available across the app. -->
        <SidebarTrigger
            :class="
                cn(
                    'text-brand-800 hover:bg-brand-50 hover:text-brand-800 focus-visible:ring-brand-600/40 size-10 shrink-0 focus-visible:ring-2 md:size-11 [&_svg:not([class*=\'size-\'])]:size-5',
                    state === 'expanded' && !isMobile && 'xl:hidden',
                )
            "
        />
        <Link
            v-if="isMobile"
            :href="dashboard()"
            class="focus-visible:ring-brand-600/40 flex shrink-0 items-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
        >
            <AppLogo variant="mark" class="size-8" />
        </Link>

        <!-- The handwritten tagline sits where the Lessons & Content mockup
             draws it: its text starts 195px after the sidebar, clear of the
             bell, account and language box at the end (logical, so it
             mirrors in Arabic). -->
        <img
            v-if="props.topbarTaglineSrc"
            :src="props.topbarTaglineSrc"
            alt=""
            aria-hidden="true"
            width="513"
            height="72"
            draggable="false"
            class="h-topbar pointer-events-none absolute start-[173px] top-0 hidden w-[314px] mix-blend-multiply select-none xl:block"
        />

        <div class="ms-auto flex min-w-0 items-center">
            <AiPointsMeter />
            <NotificationMenu />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-test="topbar-user-menu"
                        :class="
                            cn(
                                'ms-0.5 flex min-w-0 shrink-0 items-center gap-[15px] p-1 text-start md:ms-[15px]',
                                iconButtonClass,
                            )
                        "
                    >
                        <Avatar class="size-8 md:size-[34px]">
                            <AvatarImage
                                v-if="hasAvatar"
                                :src="user.avatar ?? ''"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-brand-800/85 font-heading text-[15px] font-semibold text-white"
                            >
                                {{ initial }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="sr-only md:hidden">{{
                            $t('Account menu')
                        }}</span>
                        <span class="hidden min-w-0 flex-col md:flex">
                            <span
                                class="font-heading text-ink-indigo truncate text-[13.5px] leading-5 font-semibold tracking-[-0.02em]"
                            >
                                {{ $t('Welcome, :name!', { name: firstName }) }}
                            </span>
                            <span
                                class="text-ink-slate mt-px truncate text-[12.5px] leading-[18px] tracking-[-0.01em]"
                            >
                                {{ roleLabel }}
                            </span>
                        </span>
                        <ChevronDown
                            class="text-ink-indigo -ms-[5px] -mt-2 hidden size-6 shrink-0 stroke-[2.5] md:block"
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    :side-offset="8"
                    class="min-w-56 rounded-lg"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- The approved language box (AGENTS.md §0.4): English or
                 Arabic interface (I18N-02). -->
            <LanguageSelect />
        </div>
    </header>
</template>
