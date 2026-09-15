import { toast } from 'vue-sonner';

/**
 * Feedback for chrome that points at a section which has no route yet
 * (Hotels, Lessons & Content, reminders…), so a click is never silent.
 */
export function notifyComingSoon(feature: string): void {
    toast.info(feature, { description: 'This section is coming soon.' });
}
