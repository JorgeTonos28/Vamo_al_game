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
  active_players: Array<{
    id: number
    name: string
    jersey_number: number | null
    first_name: string
    last_name: string
    document_id: string | null
    phone: string | null
    email: string | null
    address: string | null
    account_role: 'league_admin' | 'member'
    invitation_pending: boolean
  }>
  inactive_players: Array<{
    id: number
    name: string
    jersey_number: number | null
    first_name: string
    last_name: string
    document_id: string | null
    phone: string | null
    email: string | null
    address: string | null
    account_role: 'league_admin' | 'member'
    invitation_pending: boolean
  }>
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
  email?: string | null
  jersey_number?: number | null
  account_role: 'league_admin' | 'member'
}): Promise<LeagueManagementPayload> {
  const { data } = await api.post<ApiSuccess<LeagueManagementPayload>>('/league/management/players', payload)
  return data.data
}

export async function updateLeaguePlayer(
  playerId: number,
  payload: {
    first_name: string
    last_name: string
    document_id: string
    phone?: string | null
    address?: string | null
    email?: string | null
    jersey_number?: number | null
    account_role: 'league_admin' | 'member'
  },
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

export type LeagueCompetitionSession = {
  id: number
  status: string
  session_date: string | null
  current_game_number: number
  streak: {
    team: 'A' | 'B' | null
    count: number
    double_rotation_mode: boolean
    waiting_champion_team: 'A' | 'B' | null
  }
  participants_count: number
  pending_pool_count: number
  queue_count: number
}

export type LeagueCompetitionSessionSelector = {
  selected_session_id: number
  sessions: Array<{
    id: number
    session_date: string | null
    status: string
    entries_count: number
    completed_games_count: number
    is_current: boolean
  }>
}

export type LeagueCompetitionBase = {
  league: { id: number; name: string; emoji: string | null; slug: string }
  role: { value: string; label: string; can_manage: boolean }
  session_selector: LeagueCompetitionSessionSelector
  session: LeagueCompetitionSession
}

export type LeagueEntryCard = {
  id: number
  name: string
  is_guest: boolean
  jersey_number: number | null
  arrival_order: number
}

export type LeagueTeamPlayer = LeagueEntryCard & {
  points: number
  shots: { 1: number; 2: number; 3: number }
}

export type LeagueGamePayload = LeagueCompetitionBase & {
  game: {
    state: 'idle' | 'draft' | 'live' | 'completed'
    draft: { entries: LeagueEntryCard[]; can_start: boolean }
    clock: {
      duration_seconds: number | null
      remaining_seconds: number | null
      state: 'paused' | 'running' | 'finished' | 'unconfigured'
      started_at: string | null
    }
    rotation_notice: null | {
      key: string
      title: string
      body: string[]
      tone: string
      icon: string
    }
    current: null | {
      id: number
      game_number: number
      score: { team_a: number; team_b: number }
      streak: LeagueCompetitionSession['streak']
      team_a: LeagueTeamPlayer[]
      team_b: LeagueTeamPlayer[]
    }
    history: Array<{
      id: number
      game_number: number
      score: string
      winner_side: 'A' | 'B' | null
      summary: string
    }>
    summary: {
      games: number
      streak_label: string
      active_players: number
      guests: number
      cash_collected_cents: number
      unpaid_members_count: number
    }
  }
}

export type LeagueQueuePayload = LeagueCompetitionBase & {
  queue: {
    on_court: Array<LeagueEntryCard & { team_side: string | null; games_played: number; points_scored: number }>
    waiting: Array<LeagueEntryCard & { position: number | null; games_played: number; points_scored: number }>
    summary: {
      games: number
      streak_label: string
      current_streak: string
      active_players: number
      guests: number
      today_guests: number
      cash_collected_cents: number
      unpaid_members_count: number
    }
    live_game: null | { game_number: number; score: string }
  }
}

export type LeagueStatsPayload = LeagueCompetitionBase & {
  stats: {
    games_count: number
    points_leaders: Array<{
      identity: { name: string; is_guest: boolean }
      points: number
      games: number
      shots: { 1: number; 2: number; 3: number }
    }>
    games_leaders: Array<{
      identity: { name: string; is_guest: boolean }
      games: number
      wins: number
      losses: number
    }>
  }
}

export type LeagueTablePayload = LeagueCompetitionBase & {
  table: {
    banner: { games: number; points: number; players: number }
    standings: Array<{
      identity: { name: string; is_guest: boolean }
      games: number
      wins: number
      losses: number
      win_rate: number
    }>
    top_scorers: Array<{
      identity: { name: string; is_guest: boolean }
      points: number
      points_per_game: number
    }>
    top_games: Array<{
      identity: { name: string; is_guest: boolean }
      games: number
      wins: number
      losses: number
    }>
  }
}

export type LeagueSeasonProfile = {
  identity: { name: string; is_guest: boolean }
  points: number
  games: number
  wins: number
  losses: number
  shots: { 1: number; 2: number; 3: number }
  points_per_game: number
  win_rate: number
  sessions_attended: number
}

export type LeagueSeasonPayload = LeagueCompetitionBase & {
  season: {
    season: {
      id: number
      label: string
      starts_on: string | null
      sessions_count: number
      totals: {
        games: number
        points: number
        revenue_cents: number
        show_revenue: boolean
      }
    }
    leaders: {
      points: LeagueSeasonProfile[]
      wins: LeagueSeasonProfile[]
      games: LeagueSeasonProfile[]
    }
    sessions: Array<{
      id: number
      date: string | null
      total_games: number
      total_points: number
      players: number
      top_scorer: null | { name: string; points: number }
    }>
    profiles: LeagueSeasonProfile[]
  }
}

export type LeagueScoutProfile = {
  position: string | null
  role: string | null
  offensive_consistency: string | null
  speed_rating: number
  dribbling_rating: number
  scoring_rating: number
  team_play_rating: number
  court_knowledge_rating: number
  defense_rating: number
  triples_rating: number
}

export type LeagueScoutPayload = LeagueCompetitionBase & {
  scout: {
    meta: {
      positions: string[]
      roles: string[]
      consistencies: string[]
    }
    summary: {
      profiled_players: number
      total_players: number
      auto_preview_ready: boolean
      auto_preview_pool_count: number
    }
    players: Array<{
      player: { id: number; name: string; jersey_number: number | null }
      profile: LeagueScoutProfile
      season_stats: null | {
        points: number
        games: number
        wins: number
        losses: number
        points_per_game: number
        win_rate: number
        sessions_attended: number
      }
      combined_rating: number
      manual_rating: number
      stat_rating: number | null
      has_stats: boolean
    }>
    ranking: Array<{
      player: { id: number; name: string; jersey_number: number | null }
      combined_rating: number
      profile: LeagueScoutProfile
      has_stats: boolean
    }>
    auto_preview: null | {
      mode: string
      source: string
      team_a: Array<{
        id: number
        name: string
        is_guest: boolean
        jersey_number: number | null
        combined_rating: number
        role: string | null
        position: string | null
        offensive_consistency: string | null
        has_stats: boolean
      }>
      team_b: Array<{
        id: number
        name: string
        is_guest: boolean
        jersey_number: number | null
        combined_rating: number
        role: string | null
        position: string | null
        offensive_consistency: string | null
        has_stats: boolean
      }>
      team_a_rating: number
      team_b_rating: number
    }
  }
}

export async function fetchLeagueGame(): Promise<LeagueGamePayload> {
  const { data } = await api.get<ApiSuccess<LeagueGamePayload>>('/league/modules/game')
  return data.data
}

export async function fetchLeagueQueue(sessionId?: number): Promise<LeagueQueuePayload> {
  const { data } = await api.get<ApiSuccess<LeagueQueuePayload>>('/league/modules/queue', {
    params: sessionId ? { session_id: sessionId } : undefined,
  })
  return data.data
}

export async function fetchLeagueStats(sessionId?: number): Promise<LeagueStatsPayload> {
  const { data } = await api.get<ApiSuccess<LeagueStatsPayload>>('/league/modules/stats', {
    params: sessionId ? { session_id: sessionId } : undefined,
  })
  return data.data
}

export async function fetchLeagueTable(): Promise<LeagueTablePayload> {
  const { data } = await api.get<ApiSuccess<LeagueTablePayload>>('/league/modules/table')
  return data.data
}

export async function fetchLeagueSeason(): Promise<LeagueSeasonPayload> {
  const { data } = await api.get<ApiSuccess<LeagueSeasonPayload>>('/league/modules/season')
  return data.data
}

export async function fetchLeagueScout(): Promise<LeagueScoutPayload> {
  const { data } = await api.get<ApiSuccess<LeagueScoutPayload>>('/league/modules/scout')
  return data.data
}

export async function draftLeagueGame(payload: { mode: 'auto' | 'arrival' | 'manual'; assignments?: Record<number, 'A' | 'B'> }): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/draft', payload)
  return data.data
}

export async function addLeagueTeamPoint(teamSide: 'A' | 'B'): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/team-point', {
    team_side: teamSide,
  })
  return data.data
}

export async function addLeaguePlayerPoint(entryId: number, points: 1 | 2 | 3): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>(`/league/modules/game/players/${entryId}/point`, {
    points,
  })
  return data.data
}

export async function revertLeaguePlayerPoint(entryId: number, points: 1 | 2 | 3): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>(`/league/modules/game/players/${entryId}/revert`, {
    points,
  })
  return data.data
}

export async function removeLeagueGamePlayer(entryId: number): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>(`/league/modules/game/players/${entryId}/remove`)
  return data.data
}

export async function undoLeagueGameAction(): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/undo')
  return data.data
}

export async function finishLeagueGame(winnerSide?: 'A' | 'B'): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/finish', {
    winner_side: winnerSide,
  })
  return data.data
}

export async function configureLeagueGameClock(durationSeconds: number): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/clock', {
    duration_seconds: durationSeconds,
  })
  return data.data
}

export async function startLeagueGameClock(): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/clock/start')
  return data.data
}

export async function pauseLeagueGameClock(): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/clock/pause')
  return data.data
}

export async function resetLeagueGameClock(): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/clock/reset')
  return data.data
}

export async function endLeagueSession(): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/end-session')
  return data.data
}

export async function resetLeagueGame(): Promise<LeagueGamePayload> {
  const { data } = await api.post<ApiSuccess<LeagueGamePayload>>('/league/modules/game/reset')
  return data.data
}

export async function updateLeagueScoutPlayer(
  playerId: number,
  payload: {
    position: string | null
    role: string | null
    offensive_consistency: string | null
    speed_rating: number
    dribbling_rating: number
    scoring_rating: number
    team_play_rating: number
    court_knowledge_rating: number
    defense_rating: number
    triples_rating: number
  },
): Promise<LeagueScoutPayload> {
  const { data } = await api.patch<ApiSuccess<LeagueScoutPayload>>(`/league/modules/scout/players/${playerId}`, payload)
  return data.data
}
