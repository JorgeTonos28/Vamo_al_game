import axios from 'axios'
import { clearSessionState, sessionState } from '@/state/session'

const fallbackApiBaseUrl = 'http://127.0.0.1:8000/api/v1'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? fallbackApiBaseUrl,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

export function backendBaseUrl(): string {
  const apiBaseUrl = api.defaults.baseURL ?? fallbackApiBaseUrl

  return apiBaseUrl.replace(/\/api\/v\d+\/?$/, '')
}

api.interceptors.request.use((config) => {
  if (sessionState.token) {
    config.headers.Authorization = `Bearer ${sessionState.token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await clearSessionState()
    }

    return Promise.reject(error)
  },
)

export function extractApiErrors(error: unknown): string[] {
  if (!axios.isAxiosError(error)) {
    return ['Ocurrio un error inesperado.']
  }

  const responseData = error.response?.data as
    | { message?: string; errors?: Record<string, string[] | string> }
    | undefined

  const fieldErrors = responseData?.errors
    ? Object.values(responseData.errors)
        .flatMap((value) => Array.isArray(value) ? value : [value])
        .filter((value): value is string => typeof value === 'string' && value.trim().length > 0)
    : []

  if (fieldErrors.length > 0) {
    return fieldErrors
  }

  if (responseData?.message) {
    return [responseData.message]
  }

  return ['Ocurrio un error inesperado.']
}

export function extractApiError(error: unknown): string {
  return extractApiErrors(error)[0] ?? 'Ocurrio un error inesperado.'
}
