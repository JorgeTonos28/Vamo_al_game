import { api } from '@/services/api'

type ApiSuccess<T> = {
  success: boolean
  message: string
  data: T
  errors: unknown[]
  meta: Record<string, unknown>
}

export type LeagueOperationalContext = {
  can_access_modules: boolean
  can_manage_league: boolean
  is_guest_role: boolean
}

export type LeagueHomePayload = {
  mode: 'operational' | 'guest' | 'no_league'
  league: { id: number; name: string; emoji: string | null; slug: string } | null
  role: { value: string; label: string; can_manage: boolean } | null
  requires_league_selection: boolean
  summary: {
    cut_label: string
    is_past_due: boolean
    players_count: number
    paid_players_count: number
    pending_players_count: number
    today_arrivals_count: number
    today_guests_count: number
    session_status: string
  } | null
}

export type LeagueRosterManagement = {
  can_manage: boolean
  active_players: Array<{ id: number; name: string; jersey_number: number | null }>
  inactive_players: Array<{ id: number; name: string; jersey_number: number | null }>
  referral_options: Array<{ id: number; name: string }>
  referral_credit_amount_cents: number
}

export type LeagueArrivalPayload = {
  league: { id: number; name: string; emoji: string | null; slug: string }
  role: { value: string; label: string; can_manage: boolean }
  cut: {
    id: number
    label: string
    starts_on: string
    ends_on: string
    due_on: string
    is_past_due: boolean
    member_fee_amount_cents: number
    guest_fee_amount_cents: number
  }
  session: {
    id: number | null
    status: string
    session_date: string | null
    started_at: string | null
    prepared_at: string | null
    counts: { arrived_members: number; total_members: number; guests: number }
    prepared_pool: Array<{ id: number; name: string; entry_type: string }>
    prepared_queue: Array<{ id: number; name: string; entry_type: string }>
  }
  players: Array<{
    id: number
    name: string
    jersey_number: number | null
    attendance_count: number
    arrival_order: number | null
    has_arrived: boolean
    current_cut_paid: boolean
    status_tone: string
    status_message: string
  }>
  guests: Array<{ id: number; name: string; arrival_order: number; guest_fee_paid: boolean }>
  roster_management: LeagueRosterManagement
}

export type LeagueManagementPayload = {
  league: { id: number; name: string; emoji: string | null; slug: string }
  role: { value: string; label: string }
  cut_selector: { selected_cut_id: number; cuts: Array<{ id: number; label: string; is_active: boolean }> }
  summary: {
    selected_cut: { id: number; label: string; starts_on: string; ends_on: string; due_on: string; is_past_due: boolean }
    income: { cash_payments_cents: number; guest_income_cents: number; total_cents: number }
    expenses: { total_cents: number }
    balance_cents: number
  }
  payments: Array<{
    player: { id: number; name: string; jersey_number: number | null }
    balance: {
      status: string
      amount_paid_cents: number
      extra_credit_cents: number
      available_referral_credit_cents: number
      previous_debt_cents: number
      settlement_due_cents: number
      status_tone: string
      status_message: string
    }
  }>
  expenses: Array<{ id: number; name: string; expense_type: string; amount_cents: number; is_system_generated: boolean; is_fixed: boolean }>
  board: { members: Array<{ id: number; name: string; share_cents: number }>; share_cents: number }
  settings: { sessions_limit: number; game_days: string[]; cut_day: number; member_fee_amount_cents: number; guest_fee_amount_cents: number; referral_credit_amount_cents: number }
  referrals: Array<{ referrer: { id: number; name: string }; available_credit_cents: number; members: Array<{ id: number; name: string }> }>
  roster_management: LeagueRosterManagement
}

export async function fetchLeagueHome(): Promise<LeagueHomePayload> {
  const { data } = await api.get<ApiSuccess<LeagueHomePayload>>('/league/home')
  return data.data
}

export async function fetchLeagueArrival(): Promise<LeagueArrivalPayload> {
  const { data } = await api.get<ApiSuccess<LeagueArrivalPayload>>('/league/arrival')
  return data.data
}

export async function toggleLeagueArrivalPlayer(playerId: number, paid?: boolean): Promise<LeagueArrivalPayload> {
  const { data } = await api.post<ApiSuccess<LeagueArrivalPayload>>(`/league/arrival/players/${playerId}/toggle`, {
    paid,
  })
  return data.data
}

export async function addLeagueArrivalGuest(guestName: string): Promise<LeagueArrivalPayload> {
  const { data } = await api.post<ApiSuccess<LeagueArrivalPayload>>('/league/arrival/guests', {
    guest_name: guestName,
  })
  return data.data
}

export async function updateLeagueArrivalGuest(guestId: number, guestFeePaid: boolean): Promise<LeagueArrivalPayload> {
  const { data } = await api.patch<ApiSuccess<LeagueArrivalPayload>>(`/league/arrival/guests/${guestId}`, {
    guest_fee_paid: guestFeePaid,
  })
  return data.data
}

export async function deleteLeagueArrivalGuest(guestId: number): Promise<LeagueArrivalPayload> {
  const { data } = await api.delete<ApiSuccess<LeagueArrivalPayload>>(`/league/arrival/guests/${guestId}`)
  return data.data
}

export async function prepareLeagueArrival(guestPayments: Array<{ id: number; paid: boolean }>): Promise<LeagueArrivalPayload> {
  const { data } = await api.post<ApiSuccess<LeagueArrivalPayload>>('/league/arrival/prepare', {
    guest_payments: guestPayments,
  })
  return data.data
}

export async function resetLeagueArrival(): Promise<LeagueArrivalPayload> {
  const { data } = await api.post<ApiSuccess<LeagueArrivalPayload>>('/league/arrival/reset')
  return data.data
}

export async function fetchLeagueManagement(cutId?: number): Promise<LeagueManagementPayload> {
  const { data } = await api.get<ApiSuccess<LeagueManagementPayload>>('/league/management', {
    params: cutId ? { cut_id: cutId } : undefined,
  })
  return data.data
}

export async function recordLeaguePayment(playerId: number, payload: { cut_id: number; amount_cents: number; apply_referral_credit?: boolean }): Promise<LeagueManagementPayload> {
  const { data } = await api.post<ApiSuccess<LeagueManagementPayload>>(`/league/management/payments/${playerId}`, payload)
  return data.data
}

export async function removeLeaguePayment(playerId: number, cutId: number): Promise<LeagueManagementPayload> {
  const { data } = await api.delete<ApiSuccess<LeagueManagementPayload>>(`/league/management/payments/${playerId}`, {
    data: { cut_id: cutId },
  })
  return data.data
}

export async function addLeagueExpense(payload: { cut_id: number; name: string; amount_cents: number; is_fixed?: boolean }): Promise<LeagueManagementPayload> {
  const { data } = await api.post<ApiSuccess<LeagueManagementPayload>>('/league/management/expenses', payload)
  return data.data
}

export async function deleteLeagueExpense(expenseId: number): Promise<LeagueManagementPayload> {
  const { data } = await api.delete<ApiSuccess<LeagueManagementPayload>>(`/league/management/expenses/${expenseId}`)
  return data.data
}

export async function updateLeagueSettings(payload: {
  sessions_limit: number
  game_days: string[]
  cut_day: number
  member_fee_amount_cents: number
  guest_fee_amount_cents: number
  referral_credit_amount_cents: number
}): Promise<LeagueManagementPayload> {
  const { data } = await api.post<ApiSuccess<LeagueManagementPayload>>('/league/management/settings', payload)
  return data.data
}

export async function addLeagueReferral(payload: { referrer_player_id: number; referred_player_id: number }): Promise<LeagueManagementPayload> {
  const { data } = await api.post<ApiSuccess<LeagueManagementPayload>>('/league/management/referrals', payload)
  return data.data
}

export async function deleteLeagueReferral(referralId: number): Promise<LeagueManagementPayload> {
  const { data } = await api.delete<ApiSuccess<LeagueManagementPayload>>(`/league/management/referrals/${referralId}`)
  return data.data
}

export async function addLeaguePlayer(payload: {
  first_name: string
  last_name: string
  document_id?: string | null
  phone?: string | null
  address?: string | null
  email: string
  account_role: 'league_admin' | 'member'
}): Promise<LeagueManagementPayload> {
  const { data } = await api.post<ApiSuccess<LeagueManagementPayload>>('/league/management/players', payload)
  return data.data
}

export async function updateLeaguePlayer(
  playerId: number,
  payload: { display_name: string; jersey_number?: number | null },
): Promise<LeagueManagementPayload> {
  const { data } = await api.patch<ApiSuccess<LeagueManagementPayload>>(`/league/management/players/${playerId}`, payload)
  return data.data
}

export async function setLeaguePlayerStatus(
  playerId: number,
  active: boolean,
): Promise<LeagueManagementPayload> {
  const { data } = await api.patch<ApiSuccess<LeagueManagementPayload>>(`/league/management/players/${playerId}/status`, {
    active,
  })
  return data.data
}

export async function downloadLeagueManagementReport(cutId: number): Promise<string> {
  const response = await api.get('/league/management/report', {
    params: { cut_id: cutId },
    responseType: 'blob',
  })

  return URL.createObjectURL(response.data as Blob)
}
