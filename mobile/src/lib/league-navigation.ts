import type { LeagueOperationalContext } from '@/services/league'
import {
  calendarOutline,
  clipboardOutline,
  documentTextOutline,
  gameControllerOutline,
  gridOutline,
  listOutline,
  logInOutline,
  searchOutline,
  settingsOutline,
  statsChartOutline,
  thumbsUpOutline,
  trophyOutline,
  walletOutline,
} from 'ionicons/icons'

export type LeagueNavItem = {
  label: string
  routeName: string
  href: string
  icon: string
  adminOnly?: boolean
}

const items: LeagueNavItem[] = [
  { label: 'Panel', routeName: 'league-panel', href: '/app/league/panel', icon: gridOutline },
  { label: 'Llegada', routeName: 'league-arrival', href: '/app/league/arrival', icon: logInOutline },
  { label: 'Juego', routeName: 'league-game', href: '/app/league/game', icon: gameControllerOutline },
  { label: 'Cola', routeName: 'league-queue', href: '/app/league/queue', icon: listOutline },
  { label: 'Stats', routeName: 'league-stats', href: '/app/league/stats', icon: statsChartOutline },
  { label: 'Tabla', routeName: 'league-table', href: '/app/league/table', icon: gridOutline },
  { label: 'Temporada', routeName: 'league-season', href: '/app/league/season', icon: calendarOutline },
  { label: 'Scout', routeName: 'league-scout', href: '/app/league/scout', icon: searchOutline },
  { label: 'Torneo', routeName: 'league-placeholder', href: '/app/league/modules/torneo', icon: trophyOutline },
  { label: 'Anotador', routeName: 'league-placeholder', href: '/app/league/modules/anotador', icon: clipboardOutline },
  { label: 'Votos', routeName: 'league-placeholder', href: '/app/league/modules/votos', icon: thumbsUpOutline },
  { label: 'Post', routeName: 'league-placeholder', href: '/app/league/modules/post', icon: documentTextOutline },
  { label: 'Gestion', routeName: 'league-management', href: '/app/league/management', icon: walletOutline, adminOnly: true },
  { label: 'Ajustes', routeName: 'settings-profile', href: '/app/settings/profile', icon: settingsOutline },
]

export function leagueNavItems(context: LeagueOperationalContext | null): LeagueNavItem[] {
  if (!context?.can_access_modules) {
    return [
      { label: 'Panel', routeName: 'app-home', href: '/app/home', icon: gridOutline },
      { label: 'Ajustes', routeName: 'settings-profile', href: '/app/settings/profile', icon: settingsOutline },
    ]
  }

  return items.filter((item) => !item.adminOnly || context.can_manage_league)
}
