import { toast } from 'vue-sonner';
import { t } from '@/lib/i18n';

/**
 * Feedback for chrome that points at a section which has no route yet
 * (Hotels, Lessons & Content, reminders…), so a click is never silent.
 * The feature name is an English nav label, translated here (I18N-02).
 */
export function notifyComingSoon(feature: string): void {
    toast.info(t(feature), { description: t('This section is coming soon.') });
}
