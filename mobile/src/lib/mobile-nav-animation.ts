import { createAnimation } from '@ionic/vue'

export function mobileNavAnimation(_: HTMLElement, opts: Record<string, unknown>) {
  const enteringEl = opts.enteringEl as HTMLElement | undefined
  const leavingEl = opts.leavingEl as HTMLElement | undefined
  const direction = opts.direction === 'back' ? 'back' : 'forward'
  const enteringOffset = direction === 'back' ? '-12%' : '12%'
  const leavingOffset = direction === 'back' ? '8%' : '-8%'

  const rootAnimation = createAnimation()
    .duration(320)
    .easing('cubic-bezier(0.16, 1, 0.3, 1)')

  if (enteringEl) {
    const enteringAnimation = createAnimation()
      .addElement(enteringEl)
      .beforeStyles({
        opacity: '0.01',
        transform: `translate3d(${enteringOffset}, 0, 0)`,
      })
      .afterClearStyles(['opacity', 'transform'])
      .fromTo('opacity', '0.01', '1')
      .fromTo('transform', `translate3d(${enteringOffset}, 0, 0)`, 'translate3d(0, 0, 0)')

    rootAnimation.addAnimation(enteringAnimation)
  }

  if (leavingEl) {
    const leavingAnimation = createAnimation()
      .addElement(leavingEl)
      .afterClearStyles(['opacity', 'transform'])
      .fromTo('opacity', '1', '0.72')
      .fromTo('transform', 'translate3d(0, 0, 0)', `translate3d(${leavingOffset}, 0, 0)`)

    rootAnimation.addAnimation(leavingAnimation)
  }

  return rootAnimation
}
