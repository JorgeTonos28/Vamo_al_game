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
