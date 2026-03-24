import { api } from '@/services/api'
import { setSessionTenancy, setSessionUser } from '@/state/session'
import type { ActiveLeagueUpdatePayload, CurrentUserResponse } from '@/types/api'

export async function updateActiveLeague(
  payload: ActiveLeagueUpdatePayload,
): Promise<CurrentUserResponse['data']> {
  const { data } = await api.patch<CurrentUserResponse>('/me/active-league', payload)

  setSessionUser(data.data)
  setSessionTenancy(data.meta.tenancy)

  return data.data
}
