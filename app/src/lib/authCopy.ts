import type { Branding } from '@/api';

/**
 * The app's own wording for the sign-in panel, used when an admin hasn't set any.
 */
export const authCopy: Record<keyof Branding['auth_messages'], string> = {
  sign_in: 'Plug in the SMS and email providers you already pay for. Send campaigns, see every delivery receipt, keep one contact list.',
  sign_up: 'Send with the providers you already use',
  invite: 'Your team works from one contact list, one set of templates and the providers your workspace already connected.',
};
