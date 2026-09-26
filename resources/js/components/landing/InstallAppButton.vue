<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Download,
    EllipsisVertical,
    MonitorDown,
    Share,
    SquarePlus,
} from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';

type Variant = 'outline' | 'light' | 'menu' | 'nav';

type Props = {
    variant?: Variant;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { variant: 'outline' });

const emit = defineEmits<{ opened: [] }>();

// The landing page's "Download the app": installs GHASIDO as a PWA whose
// home-screen icon opens the sign-in page (public/manifest.webmanifest).
const page = usePage();
const signInHref = computed(() =>
    page.props.auth.user ? dashboard().url : login().url,
);

const { standalone, isIos, install } = usePwaInstall();
const helpOpen = ref(false);

const variants: Record<Variant, string> = {
    outline:
        'border border-line-strong bg-surface text-brand-700 shadow-card hover:bg-brand-50 hover:text-brand-700 h-12 px-6 has-[>svg]:px-6 text-[14px]',
    light: 'bg-surface/10 text-surface border border-surface/35 hover:bg-surface/20 hover:text-surface h-12 px-6 has-[>svg]:px-6 text-[14px]',
    menu: 'text-brand-700 hover:bg-brand-50 hover:text-brand-700 mt-1 h-auto min-h-11 w-full justify-start px-3 text-sm',
    // The landing navbar: a 44px brand-tinted icon until 1280px, then the
    // icon and "Download app".
    nav: 'border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100 hover:text-brand-700 size-11 shrink-0 p-0 has-[>svg]:px-0 text-[13px] xl:h-11 xl:w-auto xl:px-4 xl:has-[>svg]:px-4',
};

async function onClick(): Promise<void> {
    emit('opened');

    // Already inside the installed app: just go and sign in.
    if (standalone.value) {
        router.visit(signInHref.value);

        return;
    }

    const outcome = await install();

    if (outcome === 'accepted') {
        router.visit(signInHref.value);
    } else if (outcome === 'unavailable') {
        // Safari, Firefox, or the app is already installed: show the steps.
        helpOpen.value = true;
    }
}
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        :class="
            cn(
                'focus-visible:ring-brand-600 rounded-md font-semibold focus-visible:ring-2 focus-visible:outline-none',
                variants[props.variant],
                props.class,
            )
        "
        data-test="download-app-button"
        @click="onClick"
    >
        <Download
            :class="props.variant === 'nav' ? 'size-5 xl:size-4' : 'size-4'"
            aria-hidden="true"
        />
        <span :class="props.variant === 'nav' && 'sr-only xl:not-sr-only'">
            {{ props.variant === 'nav' ? 'Download app' : 'Download the app' }}
        </span>
    </Button>

    <Dialog v-model:open="helpOpen">
        <DialogContent
            class="bg-surface border-line shadow-pop rounded-lg p-6 sm:max-w-[440px]"
        >
            <DialogHeader class="flex-row items-center gap-4 text-start">
                <img
                    src="/pwa/icon-192.png"
                    alt=""
                    width="56"
                    height="56"
                    class="border-line size-14 shrink-0 rounded-xl border"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <DialogTitle
                        class="font-heading text-brand-900 text-[18px] font-semibold"
                    >
                        Install GHASIDO
                    </DialogTitle>
                    <DialogDescription
                        class="text-ink-slate mt-1 text-[13px] leading-5"
                    >
                        Add the app to your home screen. It opens straight to
                        the sign-in page.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <ol
                v-if="isIos"
                class="text-ink-indigo grid gap-3 text-[14px] leading-6"
            >
                <li class="flex items-start gap-3">
                    <span
                        class="bg-brand-50 text-brand-700 grid size-8 shrink-0 place-items-center rounded-md"
                    >
                        <Share class="size-4" aria-hidden="true" />
                    </span>
                    <span
                        >In Safari, tap the <strong>Share</strong> button.</span
                    >
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="bg-brand-50 text-brand-700 grid size-8 shrink-0 place-items-center rounded-md"
                    >
                        <SquarePlus class="size-4" aria-hidden="true" />
                    </span>
                    <span
                        >Choose <strong>Add to Home Screen</strong>, then tap
                        <strong>Add</strong>.</span
                    >
                </li>
            </ol>
            <ol v-else class="text-ink-indigo grid gap-3 text-[14px] leading-6">
                <li class="flex items-start gap-3">
                    <span
                        class="bg-brand-50 text-brand-700 grid size-8 shrink-0 place-items-center rounded-md"
                    >
                        <EllipsisVertical class="size-4" aria-hidden="true" />
                    </span>
                    <span
                        >On a phone, open the browser menu and choose
                        <strong>Install app</strong> or
                        <strong>Add to Home screen</strong>.</span
                    >
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="bg-brand-50 text-brand-700 grid size-8 shrink-0 place-items-center rounded-md"
                    >
                        <MonitorDown class="size-4" aria-hidden="true" />
                    </span>
                    <span
                        >On a computer, use Chrome or Edge and click the
                        <strong>install</strong> icon in the address bar.</span
                    >
                </li>
            </ol>

            <p class="text-ink-slate text-[12.5px] leading-5">
                Already installed? Open GHASIDO from your home screen.
            </p>

            <Button
                as-child
                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 h-11 w-full rounded-md text-[14px] font-semibold"
            >
                <Link :href="signInHref" @click="helpOpen = false">
                    Continue to sign in
                </Link>
            </Button>
        </DialogContent>
    </Dialog>
</template>
