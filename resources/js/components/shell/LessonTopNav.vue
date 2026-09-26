<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ChevronDown, CircleUserRound, Star } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import LanguageSelect from '@/components/shell/LanguageSelect.vue';
import NotificationMenu from '@/components/shell/NotificationMenu.vue';
import SolidHouseIcon from '@/components/icons/SolidHouseIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn } from '@/lib/utils';
import { home, lessons, phrasebook } from '@/routes/learn';

/*
 * The lesson runner's 72px white top bar (spec 0003 H.1), measured on
 * desginphotos/employ/photo_1 at 1280px: logo lockup at x 32 / y 11 (as the
 * admin sidebar header); on the right, "Home" (solid house 18px, text at
 * x 664), "My Lessons" (book 22px), "My Phrasebook" (solid star 20px),
 * "Welcome!" user menu (28px outline avatar + chevron) and the language box
 * (100×40 at x 1162 / y 21). Items sit ~30px apart, their centre 40px down.
 * Below md the labels drop and BottomNav carries the sections.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const linkClass =
    'text-ink-graphite ease-brand hover:text-brand-600 focus-visible:ring-brand-600/40 flex min-h-11 items-center gap-2.5 rounded-md px-1 text-[13px] leading-none font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none';
</script>

<template>
    <header
        class="bg-surface h-topbar flex shrink-0 items-center ps-4 pe-3 md:ps-8 md:pe-[18px]"
    >
        <Link
            :href="home()"
            class="focus-visible:ring-brand-600/40 flex shrink-0 self-start rounded-md pt-[11px] focus-visible:ring-2 focus-visible:outline-none"
        >
            <AppLogo class="hidden md:flex" />
            <AppLogo variant="mark" class="size-10 md:hidden" />
        </Link>

        <nav
            :aria-label="$t('Learner')"
            class="ms-auto mt-2 flex items-center gap-2 md:gap-[22px]"
        >
            <Link
                :href="home()"
                :class="linkClass"
                :aria-current="isCurrentUrl(home()) ? 'page' : undefined"
            >
                <SolidHouseIcon
                    class="text-brand-900 size-[18px] shrink-0"
                    aria-hidden="true"
                />
                <span class="hidden md:inline">{{ $t('Home') }}</span>
            </Link>
            <Link
                :href="lessons()"
                :class="linkClass"
                :aria-current="
                    isCurrentOrParentUrl(lessons()) ? 'page' : undefined
                "
            >
                <BookOpen
                    class="text-brand-900 size-[22px] shrink-0 stroke-[2.25]"
                    aria-hidden="true"
                />
                <span class="hidden md:inline">{{ $t('My Lessons') }}</span>
            </Link>
            <Link
                :href="phrasebook()"
                :class="linkClass"
                :aria-current="
                    isCurrentOrParentUrl(phrasebook()) ? 'page' : undefined
                "
            >
                <Star
                    class="text-warning size-5 shrink-0 fill-current"
                    aria-hidden="true"
                />
                <span class="hidden md:inline">{{ $t('My Phrasebook') }}</span>
            </Link>

            <NotificationMenu />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-test="lesson-user-menu"
                        :class="
                            cn(
                                linkClass,
                                'hover:bg-brand-50 data-[state=open]:bg-brand-50 gap-[11px]',
                            )
                        "
                    >
                        <CircleUserRound
                            class="text-brand-900 size-7 shrink-0 stroke-[1.75]"
                            aria-hidden="true"
                        />
                        <span class="sr-only md:hidden">{{
                            $t('Account menu')
                        }}</span>
                        <span class="hidden md:inline">{{
                            $t('Welcome!')
                        }}</span>
                        <ChevronDown
                            class="text-ink-graphite hidden size-5 shrink-0 stroke-[2.5] md:block"
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

            <!-- The lesson bar's smaller language box (I18N-02). -->
            <LanguageSelect compact class="ms-0 md:ms-0" />
        </nav>
    </header>
</template>
