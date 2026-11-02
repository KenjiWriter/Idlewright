/** Whether this browser can be sent push messages, and what the driver must do first if it cannot yet. */
export type PushSupport = 'unsupported' | 'needs-install' | 'ready'

/** Whether the app runs as an installed app (added to the home screen), where iOS only lets a site receive push messages. */
function isInstalled(): boolean {
  const standalone = typeof window.matchMedia === 'function' && window.matchMedia('(display-mode: standalone)').matches

  return standalone || (navigator as Navigator & { standalone?: boolean }).standalone === true
}

/** An iPhone or an iPad (an iPad can say it is a Mac, but a Mac has no touch screen). */
function isApple(): boolean {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
}

/**
 * Whether the browser has everything push needs (a service worker, push, notifications). On an iPhone or iPad the objects are there only for an app that was added to the
 * home screen, so a browser tab there is told to install the app first.
 */
export function pushSupport(): PushSupport {
  const hasWorkers = 'serviceWorker' in navigator

  if (isApple() && !isInstalled() && hasWorkers) {
    return 'needs-install'
  }

  return hasWorkers && 'PushManager' in window && 'Notification' in window ? 'ready' : 'unsupported'
}

/** What the driver told the browser about notifications of this site: `default` is that they were not asked yet; `denied` cannot be changed from a page. */
export function pushPermission(): NotificationPermission | 'unsupported' {
  return 'Notification' in window ? Notification.permission : 'unsupported'
}
