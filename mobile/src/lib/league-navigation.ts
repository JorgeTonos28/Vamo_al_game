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
  { label: 'Juego', routeName: 'league-placeholder', href: '/app/league/modules/juego', icon: gameControllerOutline },
  { label: 'Cola', routeName: 'league-placeholder', href: '/app/league/modules/cola', icon: listOutline },
  { label: 'Stats', routeName: 'league-placeholder', href: '/app/league/modules/stats', icon: statsChartOutline },
  { label: 'Tabla', routeName: 'league-placeholder', href: '/app/league/modules/tabla', icon: gridOutline },
  { label: 'Temporada', routeName: 'league-placeholder', href: '/app/league/modules/temporada', icon: calendarOutline },
  { label: 'Scout', routeName: 'league-placeholder', href: '/app/league/modules/scout', icon: searchOutline },
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
