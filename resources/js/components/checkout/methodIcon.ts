import { CreditCard, Landmark, Smartphone, Wallet } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

/*
 * A recognisable glyph for a payment method, guessed from its name: the
 * client adds methods freely in the admin, so there is no stored logo.
 */
export function methodIcon(name: string): LucideIcon {
    const key = name.toLowerCase();

    if (/bank|banque|virement|transfer|bna|cpa|bea|agb|cib/.test(key)) {
        return Landmark;
    }

    if (/baridi|ccp|mob|flexy|mobile|poste/.test(key)) {
        return Smartphone;
    }

    if (/card|carte|visa|master|redot|pay/.test(key)) {
        return CreditCard;
    }

    return Wallet;
}

const tones = [
    'bg-brand-50 text-brand-600',
    'bg-ai-tint text-ai',
    'bg-aqua-tint text-aqua',
    'bg-success-tint text-success',
    'bg-warning-tint text-warning',
] as const;

/** A tint for the method's icon chip, so neighbouring cards differ. */
export function methodTone(index: number): string {
    return tones[index % tones.length] ?? tones[0];
}
