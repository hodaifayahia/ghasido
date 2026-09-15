import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge has to be taught the Guesvia scales defined in
 * resources/css/app.css. Without this it cannot tell a custom font-size from a
 * custom text-colour and silently drops classes — cn('text-stat', 'text-ink')
 * would resolve to 'text-ink' alone, losing the 30px stat size.
 */
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display',
                'h1',
                'h2',
                'h3',
                'stat',
                'body',
                'body-sm',
                'label',
                'pill',
                'script',
            ],
            font: ['heading', 'script', 'arabic'],
            radius: ['pill'],
            shadow: ['card', 'hover', 'pop', 'btn'],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}
