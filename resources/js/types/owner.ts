import type { AiCheckState, AiPriceRow } from './ai-models';

/**
 * The owner console (spec 0007): the platform owner's paid API accounts,
 * their credit and the price table. Keys never travel here, only their
 * last four characters (API-02, SEC-03).
 */

export type ApiAccountId = 'qwen' | 'deepgram';

/** `unlimited` = no recharge yet (D6). */
export type ApiAccountState =
    | 'unlimited'
    | 'active'
    | 'low'
    | 'exhausted'
    | 'paused';

export type ApiAccountTopup = {
    id: number;
    usd: number;
    tokens: number;
    characters: number;
    seconds: number;
    note: string | null;
    createdAt: string | null;
    by: string | null;
};

/** A unit a recharge can buy (spec 0007, D10). */
export type CreditMeterId = 'tokens' | 'characters' | 'seconds';

export type ApiAccountMeter = {
    meter: CreditMeterId;
    label: string;
    covers: string;
    /** The recharge form field: `tokens`, `characters` or `minutes`. */
    field: string;
    /** Stored units per entered unit (a minute is 60 seconds). */
    scale: number;
    limited: boolean;
    granted: number;
    used: number;
};

/** `units` = a pack (dollars follow the units), `dollars`, `none`. */
export type AiCreditMode = 'units' | 'dollars' | 'none';

/**
 * One account's AI credit as the Super Admin sees it (spec 0007, D11):
 * never the owner's cost, prices or keys.
 */
export type AiCreditAccount = {
    account: ApiAccountId;
    service: string;
    provider: string;
    state: ApiAccountState;
    mode: AiCreditMode;
    creditUsd: number;
    remainingUsd: number | null;
    usedUsd: number | null;
    /** 0–1, null without a limit. */
    shareLeft: number | null;
    meters: {
        meter: CreditMeterId;
        label: string;
        granted: number;
        left: number;
    }[];
};

export type ApiAccountCard = {
    account: ApiAccountId;
    label: string;
    vendor: string;
    usedFor: string;
    state: ApiAccountState;
    paused: boolean;
    mode: AiCreditMode;
    /** Exactly what the Super Admin sees for this account. */
    client: AiCreditAccount;
    /** Usage at the owner's prices: the owner's figure only. */
    costUsd: number;
    meters: ApiAccountMeter[];
    /** When metering started: the first recharge. */
    since: string | null;
    calls: number;
    /** Models with billable usage but no price, with the unit they count in. */
    unpricedModels: { model: string; unit: string }[];
    byFeature: {
        feature: string;
        label: string;
        calls: number;
        cost: number;
        units: number;
    }[];
    key: {
        source: 'owner' | 'env' | 'none';
        masked: string | null;
        updatedAt: string | null;
    };
    checks: { capability: string; label: string; state: AiCheckState | null }[];
    topups: ApiAccountTopup[];
};

export type DeepgramBalance = {
    ok: boolean;
    project: string | null;
    balances: { amount: number; units: string }[];
    error: string | null;
    fetchedAt: string;
};

export type OwnerConsolePayload = {
    accounts: ApiAccountCard[];
    prices: AiPriceRow[];
    units: string[];
    deepgramBalance: DeepgramBalance | null;
};

/** Shared only on owner routes by the `owner` middleware. */
export type OwnerIdentity = { name: string; email: string };
