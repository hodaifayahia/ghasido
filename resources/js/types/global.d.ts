import type { Auth, LearnerJourney, TrainingContext } from '@/types/auth';
import type { NotificationData } from '@/types/notifications';
import type { OwnerIdentity } from '@/types/owner';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            notifications: NotificationData;
            /** Employee role only; null for every other user and for guests. */
            journey: LearnerJourney | null;
            /**
             * A manager's training department switcher; null unless a
             * manager is on a learner route (client decision 2026-09-23).
             */
            trainingContext: TrainingContext | null;
            /** Only on the owner console's routes (spec 0007). */
            owner?: OwnerIdentity;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
