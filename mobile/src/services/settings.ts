import { api } from '@/services/api'
import { clearSessionState, setSessionUser } from '@/state/session'
import type {
  DeleteAccountPayload,
  PasswordUpdatePayload,
  ProfileUpdatePayload,
  RecoveryCodesResponse,
  TwoFactorSetupResponse,
  TwoFactorStatusResponse,
  UserResponse,
} from '@/types/api'

export async function updateProfile(payload: ProfileUpdatePayload): Promise<UserResponse['data']> {
  const { data } = await api.patch<UserResponse>('/settings/profile', payload)

  setSessionUser(data.data)

  return data.data
}

export async function resendVerificationEmail(): Promise<UserResponse['data']> {
  const { data } = await api.post<UserResponse>('/settings/email/verification-notification')

  setSessionUser(data.data)

  return data.data
}

export async function updatePassword(payload: PasswordUpdatePayload): Promise<void> {
  await api.put('/settings/password', payload)
}

export async function deleteAccount(payload: DeleteAccountPayload): Promise<void> {
  await api.delete('/settings/profile', {
    data: payload,
  })

  await clearSessionState()
}

export async function fetchTwoFactorStatus(): Promise<TwoFactorStatusResponse['data']> {
  const { data } = await api.get<TwoFactorStatusResponse>('/settings/two-factor')

  return data.data
}

export async function enableTwoFactor(): Promise<TwoFactorSetupResponse['data']> {
  const { data } = await api.post<TwoFactorSetupResponse>('/settings/two-factor')

  return data.data
}

export async function fetchTwoFactorSetup(): Promise<TwoFactorSetupResponse['data']> {
  const { data } = await api.get<TwoFactorSetupResponse>('/settings/two-factor/setup')

  return data.data
}

export async function confirmTwoFactor(code: string): Promise<TwoFactorStatusResponse['data']> {
  const { data } = await api.post<TwoFactorStatusResponse>('/settings/two-factor/confirm', {
    code,
  })

  return data.data
}

export async function disableTwoFactor(): Promise<TwoFactorStatusResponse['data']> {
  const { data } = await api.delete<TwoFactorStatusResponse>('/settings/two-factor')

  return data.data
}

export async function fetchRecoveryCodes(): Promise<RecoveryCodesResponse['data']> {
  const { data } = await api.get<RecoveryCodesResponse>('/settings/two-factor/recovery-codes')

  return data.data
}

export async function regenerateRecoveryCodes(): Promise<RecoveryCodesResponse['data']> {
  const { data } = await api.post<RecoveryCodesResponse>('/settings/two-factor/recovery-codes')

  return data.data
}
