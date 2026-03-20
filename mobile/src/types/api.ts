import type { components } from '@contracts/api'

export type ApiUser = components['schemas']['User']
export type LoginPayload = components['schemas']['LoginRequest']
export type AuthTokenResponse = components['schemas']['AuthTokenResponse']
export type UserResponse = components['schemas']['UserResponse']
export type HealthResponse = components['schemas']['HealthResponse']
export type ErrorResponse = components['schemas']['ErrorResponse']
