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
    note: string | null;
    createdAt: string | null;
    by: string | null;
};

export type ApiAccountCard = {
    account: ApiAccountId;
    label: string;
    vendor: string;
    usedFor: string;
    tracksTokens: boolean;
    state: ApiAccountState;
    paused: boolean;
    limitedByUsd: boolean;
    limitedByTokens: boolean;
    credit: { usd: number; tokens: number };
    spent: { usd: number; tokens: number };
    remaining: { usd: number | null; tokens: number | null };
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
