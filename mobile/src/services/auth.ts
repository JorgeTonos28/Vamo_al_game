import { api, backendBaseUrl } from '@/services/api'
import { clearSessionState, setSessionToken, setSessionUser } from '@/state/session'
import type {
  AuthChallengeResponse,
  AuthTokenResponse,
  AuthResultResponse,
  GoogleExchangePayload,
  HealthResponse,
  LoginPayload,
  RegisterPayload,
  TwoFactorChallengePayload,
  UserResponse,
} from '@/types/api'

export async function register(payload: RegisterPayload): Promise<UserResponse> {
  const { data } = await api.post<UserResponse>('/auth/register', payload)

  return data
}

export type AuthFlowResult =
  | { kind: 'authenticated'; response: AuthTokenResponse }
  | { kind: 'two-factor'; response: AuthChallengeResponse }

export async function exchangeGoogleHandoff(
  payload: GoogleExchangePayload | string,
): Promise<AuthFlowResult> {
  const requestPayload = typeof payload === 'string' ? { handoff: payload } : payload
  const response = await api.post<AuthResultResponse>('/auth/google/exchange', requestPayload)

  return persistAuthResult(response.status, response.data)
}

function isTwoFactorResponse(response: AuthResultResponse): response is AuthChallengeResponse {
  return 'challenge_token' in response.data
}

export function googleAuthUrl(): string {
  return `${backendBaseUrl()}/auth/google/redirect?channel=mobile`
}

export async function login(payload: LoginPayload): Promise<AuthFlowResult> {
  const response = await api.post<AuthResultResponse>('/auth/login', payload)

  return persistAuthResult(response.status, response.data)
}

export async function submitTwoFactorChallenge(
  payload: TwoFactorChallengePayload,
): Promise<AuthTokenResponse> {
  const { data } = await api.post<AuthTokenResponse>('/auth/two-factor-challenge', payload)

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

async function persistAuthResult(
  _status: number,
  data: AuthResultResponse,
): Promise<AuthFlowResult> {
  if (isTwoFactorResponse(data)) {
    return {
      kind: 'two-factor',
      response: data,
    }
  }

  await setSessionToken(data.data.token)
  setSessionUser(data.data.user)

  return {
    kind: 'authenticated',
    response: data,
  }
}
