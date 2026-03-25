import { api } from '@/services/api'
import { setBranding } from '@/state/branding'
import type {
  Branding,
  CommandCenterAssignLeagueMembershipPayload,
  CommandCenterAssignLeagueMembershipResponse,
  CommandCenterDashboardResponse,
  CommandCenterInviteUserPayload,
  CommandCenterInviteUserResponse,
  CommandCenterLeaguesResponse,
  CommandCenterLeagueResponse,
  CommandCenterSettingsResponse,
  CommandCenterUsersResponse,
} from '@/types/api'

export async function fetchCommandCenterDashboard(): Promise<CommandCenterDashboardResponse['data']> {
  const { data } = await api.get<CommandCenterDashboardResponse>('/command-center/dashboard')

  return data.data
}

export async function fetchCommandCenterUsers(): Promise<CommandCenterUsersResponse['data']> {
  const { data } = await api.get<CommandCenterUsersResponse>('/command-center/users')

  return data.data
}

export async function inviteCommandCenterUser(
  payload: CommandCenterInviteUserPayload,
): Promise<CommandCenterInviteUserResponse['data']> {
  const { data } = await api.post<CommandCenterInviteUserResponse>('/command-center/users', payload)

  return data.data
}

export async function assignCommandCenterUserLeague(
  userId: number,
  payload: CommandCenterAssignLeagueMembershipPayload,
): Promise<CommandCenterAssignLeagueMembershipResponse['data']> {
  const { data } = await api.post<CommandCenterAssignLeagueMembershipResponse>(`/command-center/users/${userId}/leagues`, payload)

  return data.data
}

export async function fetchCommandCenterLeagues(): Promise<CommandCenterLeaguesResponse['data']> {
  const { data } = await api.get<CommandCenterLeaguesResponse>('/command-center/leagues')

  return data.data
}

export async function toggleCommandCenterLeague(
  leagueId: number,
): Promise<CommandCenterLeagueResponse['data']> {
  const { data } = await api.patch<CommandCenterLeagueResponse>(`/command-center/leagues/${leagueId}`)

  return data.data
}

export async function fetchCommandCenterSettings(): Promise<CommandCenterSettingsResponse['data']> {
  const { data } = await api.get<CommandCenterSettingsResponse>('/command-center/settings')

  setBranding(data.data.branding)

  return data.data
}

export async function updateCommandCenterSettings(payload: {
  logo: File | null
  favicon: File | null
}): Promise<Branding> {
  const formData = new FormData()

  if (payload.logo) {
    formData.append('logo', payload.logo)
  }

  if (payload.favicon) {
    formData.append('favicon', payload.favicon)
  }

  const { data } = await api.post<CommandCenterSettingsResponse>('/command-center/settings', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })

  setBranding(data.data.branding)

  return data.data.branding
}
