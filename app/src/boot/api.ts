import { defineBoot } from '#q-app/wrappers';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

/**
 * Restores the session before the first route renders, and sends signed-out users to sign in.
 */
export default defineBoot(async ({ router, store }) => {
  const session = useSession(store);
  const workspace = useWorkspace(store);

  await session.load().catch(() => session.expire());

  router.beforeEach(async (to) => {
    if (to.meta.public) {
      return true;
    }

    if (to.meta.guest && session.signedIn) {
      return { name: 'dashboard' };
    }

    if (!to.meta.guest && !session.signedIn) {
      return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } };
    }

    if (session.signedIn && !workspace.list.length) {
      await session.load();
      await workspace.load();
    }

    if (to.meta.staff && !session.isStaff) {
      return { name: 'dashboard' };
    }

    return true;
  });

  session.$subscribe((_, state) => {
    if (!state.token) {
      workspace.reset();

      if (!router.currentRoute.value.meta.guest && !router.currentRoute.value.meta.public) {
        void router.push({ name: 'login' });
      }
    }
  });
});
