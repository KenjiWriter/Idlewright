import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { describe, expect, it, vi } from 'vitest'

// The service worker is a plain script (public/sw.js), so it is run here as the browser runs it: with a `self`, a `caches` and a `fetch` that these tests hold.

const source = readFileSync(resolve(__dirname, '../public/sw.js'), 'utf-8')
const ORIGIN = 'https://raceidle.test'

interface Asked {
  method: string
  url: string
  mode: string
}

type Key = Asked | { url: string } | string

/** A cache keys a request by its address, and a path is the address on this site. */
const addressOf = (key: Key): string => (typeof key === 'string' ? (key.startsWith('/') ? `${ORIGIN}${key}` : key) : key.url)

/** A cache of the browser, by the address of the request. */
function fakeCache() {
  const entries = new Map<string, Response>()

  return {
    entries,
    match: async (request: Key) => entries.get(addressOf(request))?.clone(),
    put: async (request: Key, response: Response) => void entries.set(addressOf(request), response),
    keys: async () => [...entries.keys()].map((url) => ({ url })),
    delete: async (request: Key) => entries.delete(addressOf(request)),
  }
}

function fakeCaches() {
  const stores = new Map<string, ReturnType<typeof fakeCache>>()
  const open = async (name: string) => {
    if (!stores.has(name)) {
      stores.set(name, fakeCache())
    }

    return stores.get(name)!
  }

  return {
    stores,
    open,
    keys: async () => [...stores.keys()],
    delete: async (name: string) => stores.delete(name),
    match: async (request: Key, options?: { cacheName?: string }) => (options?.cacheName ? stores.get(options.cacheName)?.match(request) : undefined),
  }
}

interface PushEvent {
  request?: Asked
  waitUntil?: (work: Promise<unknown>) => void
  respondWith?: (answer: Promise<Response>) => void
  data?: { json: () => unknown } | null
  notification?: { close: () => void; data?: { url?: unknown; nid?: unknown } }
  oldSubscription?: { options: object } | null
}

type Listener = (event: PushEvent) => void

function boot(network: (request: Asked | string) => Promise<Response>) {
  const listeners: Record<string, Listener> = {}
  const self = {
    location: { origin: ORIGIN },
    addEventListener: (type: string, listener: Listener) => void (listeners[type] = listener),
    skipWaiting: vi.fn(async () => undefined),
    clients: { claim: vi.fn(async () => undefined), matchAll: vi.fn(async (): Promise<unknown[]> => []), openWindow: vi.fn(async () => undefined) },
    registration: { showNotification: vi.fn(async () => undefined), pushManager: { subscribe: vi.fn(async () => ({})) } },
  }
  const caches = fakeCaches()
  const fetchMock = vi.fn(network)

  new Function('self', 'caches', 'fetch', 'URL', source)(self, caches, fetchMock, URL)

  /** What the worker answers to a request: nothing when it leaves the request alone. */
  async function ask(path: string, options: Partial<Asked> = {}): Promise<Response | undefined> {
    const request: Asked = { method: 'GET', url: `${path.startsWith('http') ? '' : ORIGIN}${path}`, mode: 'no-cors', ...options }
    let answer: Promise<Response> | undefined

    listeners.fetch({ request, respondWith: (given) => void (answer = given) })

    return answer
  }

  return { listeners, self, caches, fetchMock, ask }
}

const html = () => new Response('<!doctype html><title>RideIdle</title>', { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' } })
const script = (status = 200) => new Response('console.log(1)', { status, headers: { 'Content-Type': 'text/javascript' } })

const shellPage = () =>
  new Response('<!doctype html><link rel="stylesheet" href="/assets/index-b1.css"><script type="module" src="/assets/index-a1.js"></script><link rel="modulepreload" href="/assets/index-a1.js">', {
    status: 200,
    headers: { 'Content-Type': 'text/html' },
  })

/** Installs and starts the worker, the way the browser does, and waits for what it holds the browser up with. */
async function install(worker: ReturnType<typeof boot>): Promise<void> {
  const work: Promise<unknown>[] = []
  worker.listeners.install({ waitUntil: (given) => void work.push(given) })
  await Promise.all(work)
}

describe('the service worker', () => {
  it('fetches the shell and every file of the build when it is installed, so the app starts offline on any screen after the first visit', async () => {
    const list = { 'src/main.ts': { file: 'assets/index-a1.js', css: ['assets/index-b1.css'], assets: ['assets/barlow-c1.woff2'] }, 'src/views/GarageView.vue': { file: 'assets/GarageView-d1.js' } }
    const worker = boot(async (request) => (request === '/index.html' ? shellPage() : request === '/assets-manifest.json' ? new Response(JSON.stringify(list), { status: 200 }) : script()))

    await install(worker)

    expect([...worker.caches.stores.get('shell-v1')!.entries.keys()]).toEqual([`${ORIGIN}/index.html`])
    expect([...worker.caches.stores.get('files-v1')!.entries.keys()].sort()).toEqual(
      ['index-a1.js', 'index-b1.css', 'barlow-c1.woff2', 'GarageView-d1.js'].map((name) => `${ORIGIN}/assets/${name}`).sort(),
    )
    expect(worker.self.skipWaiting).toHaveBeenCalledOnce()
  })

  it('keeps at least the files that the shell names when the list of the build is not there', async () => {
    const worker = boot(async (request) => (request === '/index.html' ? shellPage() : request === '/assets-manifest.json' ? new Response('nope', { status: 404 }) : script()))

    await install(worker)

    expect([...worker.caches.stores.get('files-v1')!.entries.keys()].sort()).toEqual([`${ORIGIN}/assets/index-a1.js`, `${ORIGIN}/assets/index-b1.css`])
  })

  it('is installed all the same when the network fails at that moment, or the shell is not there', async () => {
    const failing = boot(async () => Promise.reject(new TypeError('Failed to fetch')))
    await install(failing)
    expect(failing.self.skipWaiting).toHaveBeenCalledOnce()
    expect(failing.caches.stores.size).toBe(0)

    const missing = boot(async () => new Response('nope', { status: 404 }))
    await install(missing)
    expect(missing.self.skipWaiting).toHaveBeenCalledOnce()
    expect(missing.caches.stores.size).toBe(0)
  })

  it('takes over at once when it is installed, and keeps only its own two caches when it starts', async () => {
    const worker = boot(async () => html())
    await worker.caches.open('shell-v1')
    await worker.caches.open('files-v1')
    await worker.caches.open('an-older-worker')

    await install(worker)

    const activating: Promise<unknown>[] = []
    worker.listeners.activate({ waitUntil: (work) => void activating.push(work) })
    await Promise.all(activating)

    expect(worker.self.skipWaiting).toHaveBeenCalledOnce()
    expect(worker.self.clients.claim).toHaveBeenCalledOnce()
    expect([...worker.caches.stores.keys()].sort()).toEqual(['files-v1', 'shell-v1'])
  })

  it('leaves alone what is not for it: the API, other sites, and anything that is not a GET', async () => {
    const worker = boot(async () => html())

    expect(await worker.ask('/api/v1/garage')).toBeUndefined()
    expect(await worker.ask('/api/v1/garage', { mode: 'navigate' })).toBeUndefined()
    expect(await worker.ask('https://elsewhere.test/assets/app.js')).toBeUndefined()
    expect(await worker.ask('/assets/app.js', { method: 'POST' })).toBeUndefined()
    expect(await worker.ask('/manifest.webmanifest')).toBeUndefined()
    expect(await worker.ask('/sw.js')).toBeUndefined()
    expect(worker.fetchMock).not.toHaveBeenCalled()
    expect(worker.caches.stores.size).toBe(0)
  })

  describe('a page', () => {
    it('comes from the network, and the last one that came is kept as the shell of the app', async () => {
      const worker = boot(async () => html())

      const response = await (await worker.ask('/garage', { mode: 'navigate' }))!

      expect(response.status).toBe(200)
      expect(await response.text()).toContain('RideIdle')
      expect([...worker.caches.stores.get('shell-v1')!.entries.keys()]).toEqual([`${ORIGIN}/index.html`])
    })

    it('is the kept shell when the network fails, whichever address of the app was asked', async () => {
      const worker = boot(async () => html())
      await (await worker.ask('/garage', { mode: 'navigate' }))!.text()

      worker.fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
      const offline = await (await worker.ask('/workshop', { mode: 'navigate' }))!

      expect(await offline.text()).toContain('RideIdle')
    })

    it('fails as the network does when there is nothing kept, and keeps no answer that is not a page', async () => {
      const worker = boot(async () => new Response('{"message":"x"}', { status: 200, headers: { 'Content-Type': 'application/json' } }))

      await (await worker.ask('/garage', { mode: 'navigate' }))!.text()
      expect(worker.caches.stores.get('shell-v1')?.entries.size ?? 0).toBe(0)

      worker.fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
      await expect(worker.ask('/garage', { mode: 'navigate' }).then((answer) => answer!)).rejects.toThrow('Failed to fetch')
    })

    it('does not keep an error as the shell', async () => {
      const worker = boot(async () => new Response('<html>nope</html>', { status: 502, headers: { 'Content-Type': 'text/html' } }))

      const response = await (await worker.ask('/garage', { mode: 'navigate' }))!

      expect(response.status).toBe(502)
      expect(worker.caches.stores.get('shell-v1')?.entries.size ?? 0).toBe(0)
    })
  })

  describe('a file of the build', () => {
    it('is fetched once and then comes from the cache', async () => {
      const worker = boot(async () => script())

      await (await worker.ask('/assets/app-1a2b3c.js'))!.text()
      const again = await (await worker.ask('/assets/app-1a2b3c.js'))!

      expect(await again.text()).toBe('console.log(1)')
      expect(worker.fetchMock).toHaveBeenCalledOnce()
      expect([...worker.caches.stores.get('files-v1')!.entries.keys()]).toEqual([`${ORIGIN}/assets/app-1a2b3c.js`])
    })

    it('is the same for the icons, and is not kept when it is not there', async () => {
      const worker = boot(async () => script(404))

      expect((await (await worker.ask('/assets/gone.js'))!).status).toBe(404)
      expect(worker.caches.stores.get('files-v1')?.entries.size ?? 0).toBe(0)

      worker.fetchMock.mockResolvedValue(new Response('png', { status: 200 }))
      await (await worker.ask('/icons/icon-192.png'))!.text()

      expect([...worker.caches.stores.get('files-v1')!.entries.keys()]).toEqual([`${ORIGIN}/icons/icon-192.png`])
    })

    it('is kept up to 150 files, the oldest going first', async () => {
      const worker = boot(async () => script())
      const files = await worker.caches.open('files-v1')

      for (let i = 0; i < 150; i++) {
        await files.put(`${ORIGIN}/assets/old-${i}.js`, script())
      }

      await (await worker.ask('/assets/new.js'))!.text()

      expect(files.entries.size).toBe(150)
      expect(files.entries.has(`${ORIGIN}/assets/old-0.js`)).toBe(false)
      expect(files.entries.has(`${ORIGIN}/assets/old-1.js`)).toBe(true)
      expect(files.entries.has(`${ORIGIN}/assets/new.js`)).toBe(true)
    })
  })

  describe('push messages', () => {
    const worker = () => boot(async () => script())

    /** What the worker does when a push message comes: it waits for the notification to be shown. */
    async function receive(boots: ReturnType<typeof boot>, data: PushEvent['data']): Promise<void> {
      const work: Promise<unknown>[] = []
      boots.listeners.push({ data, waitUntil: (given) => void work.push(given) })
      await Promise.all(work)
    }

    /** What the worker does when a notification is tapped. */
    async function tap(boots: ReturnType<typeof boot>, url: unknown, nid?: string): Promise<{ closed: boolean }> {
      const work: Promise<unknown>[] = []
      const seen = { closed: false }
      boots.listeners.notificationclick({ notification: { close: () => void (seen.closed = true), data: { url, nid } }, waitUntil: (given) => void work.push(given) })
      await Promise.all(work)

      return seen
    }

    it('shows the notification that the server wrote, in the language of the driver, with the tag that lets a newer message take its place', async () => {
      const w = worker()

      await receive(w, { json: () => ({ kind: 'car_stopped', title: 'Rdzak GT-4 stoi', body: 'Skończyło się paliwo.', url: '/garage', tag: 'car-12', lang: 'pl', nid: 'n123' }) })

      expect(w.self.registration.showNotification).toHaveBeenCalledExactlyOnceWith('Rdzak GT-4 stoi', {
        body: 'Skończyło się paliwo.',
        icon: '/icons/icon-192.png',
        tag: 'car-12',
        renotify: true,
        lang: 'pl',
        data: { url: '/garage', nid: 'n123' },
      })
    })

    it('shows a neutral notification when the message cannot be read or has nothing in it, because a browser takes back the permission of a site that shows nothing', async () => {
      const unreadable = worker()
      await receive(unreadable, {
        json: () => {
          throw new SyntaxError('not json')
        },
      })
      const empty = worker()
      await receive(empty, null)
      const odd = worker()
      await receive(odd, { json: () => ({ title: 5, body: null, url: {}, tag: [] }) })

      for (const w of [unreadable, empty, odd]) {
        expect(w.self.registration.showNotification).toHaveBeenCalledExactlyOnceWith('RideIdle', { body: '', icon: '/icons/icon-192.png', tag: undefined, renotify: false, lang: undefined, data: { url: '/', nid: '' } })
      }
    })

    it('opens the page that the message names in the window that is open, and closes the notification', async () => {
      const w = worker()
      const open = { url: `${ORIGIN}/missions`, focus: vi.fn(async () => undefined), navigate: vi.fn(async () => undefined) }
      w.self.clients.matchAll.mockResolvedValue([{ url: 'https://other.example/', focus: vi.fn() }, open])

      const seen = await tap(w, '/garage')

      expect(seen.closed).toBe(true)
      expect(open.focus).toHaveBeenCalledOnce()
      expect(open.navigate).toHaveBeenCalledExactlyOnceWith(`${ORIGIN}/garage`)
      expect(w.self.clients.openWindow).not.toHaveBeenCalled()
    })

    it('does not reload the window that is already on that page', async () => {
      const w = worker()
      const open = { url: `${ORIGIN}/garage`, focus: vi.fn(async () => undefined), navigate: vi.fn(async () => undefined) }
      w.self.clients.matchAll.mockResolvedValue([open])

      await tap(w, '/garage')

      expect(open.focus).toHaveBeenCalledOnce()
      expect(open.navigate).not.toHaveBeenCalled()
    })

    it('opens a window when none is open, or the one that is open cannot be reached', async () => {
      const none = worker()
      await tap(none, '/garage')
      expect(none.self.clients.openWindow).toHaveBeenCalledExactlyOnceWith(`${ORIGIN}/garage`)

      const stuck = worker()
      stuck.self.clients.matchAll.mockResolvedValue([{ url: `${ORIGIN}/map`, focus: vi.fn(async () => Promise.reject(new Error('gone'))) }])
      await tap(stuck, '/garage')
      expect(stuck.self.clients.openWindow).toHaveBeenCalledExactlyOnceWith(`${ORIGIN}/garage`)
    })

    it('never opens a page of another site, whatever the message says', async () => {
      const w = worker()

      await tap(w, 'https://attacker.example/phish')
      await tap(w, 42)

      expect(w.self.clients.openWindow.mock.calls).toEqual([[`${ORIGIN}/`], [`${ORIGIN}/`]])
    })

    it('makes the subscription again, with the same options, when the browser renews it', async () => {
      const w = worker()
      const options = { userVisibleOnly: true, applicationServerKey: new Uint8Array([4, 1]) }
      const work: Promise<unknown>[] = []

      w.listeners.pushsubscriptionchange({ oldSubscription: { options }, waitUntil: (given) => void work.push(given) })
      await Promise.all(work)

      expect(w.self.registration.pushManager.subscribe).toHaveBeenCalledExactlyOnceWith(options)

      w.listeners.pushsubscriptionchange({ oldSubscription: null, waitUntil: (given) => void work.push(given) })
      expect(w.self.registration.pushManager.subscribe).toHaveBeenCalledOnce()
    })

    it('tells the server that a notification was opened, with the number the message carried and nothing else, and does not mind when it fails', async () => {
      const w = worker()
      await tap(w, '/garage', 'n123')

      expect(w.fetchMock).toHaveBeenCalledExactlyOnceWith('/api/v1/push/seen', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nid: 'n123' }),
        keepalive: true,
      })

      const offline = boot(async () => Promise.reject(new TypeError('Failed to fetch')))
      await tap(offline, '/garage', 'n456')

      expect(offline.self.clients.openWindow).toHaveBeenCalledExactlyOnceWith(`${ORIGIN}/garage`)
    })

    it('says nothing to the server about a message that had no number', async () => {
      const w = worker()
      await tap(w, '/garage')
      await tap(w, '/garage', '')

      expect(w.fetchMock).not.toHaveBeenCalled()
    })

    it('still leaves the API alone, and the pages and files as they were', async () => {
      const w = worker()

      expect(await w.ask('/api/v1/push/config')).toBeUndefined()
      expect(await w.ask('/icons/icon-192.png')).toBeDefined()
    })
  })
})
