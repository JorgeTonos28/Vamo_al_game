import { api } from '@/services/api'
import { clearSessionState, setSessionToken, setSessionUser } from '@/state/session'
import type { AuthTokenResponse, HealthResponse, LoginPayload, UserResponse } from '@/types/api'

export async function login(payload: LoginPayload): Promise<AuthTokenResponse> {
  const { data } = await api.post<AuthTokenResponse>('/auth/login', payload)

  await setSessionToken(data.data.token)
  setSessionUser(data.data.user)

  return data
}

export async function logout(): Promise<void> {
  try {
    await api.post('/auth/logout')
  } finally {
    await clearSessionState()
  }
}

export async function fetchCurrentUser(): Promise<UserResponse['data']> {
  const { data } = await api.get<UserResponse>('/me')

  setSessionUser(data.data)

  return data.data
}

export async function fetchHealth(): Promise<HealthResponse['data']> {
  const { data } = await api.get<HealthResponse>('/health')

  return data.data
}
