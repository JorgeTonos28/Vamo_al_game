import { fetchCurrentUser } from '@/services/auth'
import { hydrateBrandingState } from '@/state/branding'
import { sessionState } from '@/state/session'

type RefreshLoader = (() => Promise<unknown> | unknown) | undefined

export async function refreshMobileApp(loadPage?: RefreshLoader): Promise<void> {
  const tasks: Promise<unknown>[] = [hydrateBrandingState(true)]

  if (sessionState.token) {
    tasks.push(fetchCurrentUser())
  }

  if (loadPage) {
    tasks.push(Promise.resolve(loadPage()))
  }

  const results = await Promise.allSettled(tasks)
  const rejected = results.find(
    (result): result is PromiseRejectedResult => result.status === 'rejected',
  )

  if (rejected) {
    throw rejected.reason
  }
}

export async function handleMobileRefresher(
  event: CustomEvent,
  loadPage?: RefreshLoader,
): Promise<void> {
  try {
    await refreshMobileApp(loadPage)
  } finally {
    await (event.target as HTMLIonRefresherElement).complete()
  }
}
