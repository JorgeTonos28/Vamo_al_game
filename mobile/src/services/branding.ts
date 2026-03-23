import { api } from '@/services/api'
import type { BrandingResponse } from '@/types/api'

export async function fetchBranding(): Promise<BrandingResponse['data']> {
  const { data } = await api.get<BrandingResponse>('/branding')

  return data.data
}
