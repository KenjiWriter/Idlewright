/**
 * The way of a car on the map: the roads run both ways, and a car takes the shortest way (by their kilometres), as the server does. The map draws the car on that
 * way, road after road, and the name of each city where a player can read it.
 */

export interface Road {
  from: string
  to: string
  km: number
}

export interface Point {
  x: number
  y: number
}

/** The cities of the shortest way from one city to another, both ends in it; null when no road leads there. */
export function shortestRoute(roads: readonly Road[], from: string, to: string): string[] | null {
  if (from === to) {
    return [from]
  }

  const distance = new Map<string, number>([[from, 0]])
  const previous = new Map<string, string>()
  const settled = new Set<string>()

  for (;;) {
    let nearest: string | null = null

    for (const [city, km] of distance) {
      if (!settled.has(city) && (nearest === null || km < (distance.get(nearest) ?? Infinity))) {
        nearest = city
      }
    }

    if (nearest === null) {
      return null
    }

    if (nearest === to) {
      break
    }

    settled.add(nearest)

    for (const road of roads) {
      const other = road.from === nearest ? road.to : road.to === nearest ? road.from : null

      if (other === null || settled.has(other)) {
        continue
      }

      const km = (distance.get(nearest) ?? 0) + road.km

      if (km < (distance.get(other) ?? Infinity)) {
        distance.set(other, km)
        previous.set(other, nearest)
      }
    }
  }

  const route = [to]

  while (route[0] !== from) {
    route.unshift(previous.get(route[0]) ?? from)
  }

  return route
}

/** The kilometres of the road between two cities next to each other on a way (the shorter one, when two roads join them). */
function legKm(roads: readonly Road[], a: string, b: string): number {
  const km = roads.filter((road) => (road.from === a && road.to === b) || (road.from === b && road.to === a)).map((road) => road.km)

  return km.length === 0 ? 0 : Math.min(...km)
}

/**
 * Where a car is on a way when it has gone this part of it (0 to 1): the way is counted in the kilometres of its roads, so a long road takes longer to drive than a
 * short one, wherever their cities are drawn. A way whose roads say no kilometres is counted by the length of its lines.
 */
export function pointAlong(route: readonly string[], roads: readonly Road[], placeOf: (slug: string) => Point | undefined, progress: number): Point | null {
  const points = route.map(placeOf)

  if (points.length === 0 || points.some((point) => point === undefined)) {
    return null
  }

  const places = points as Point[]

  if (places.length === 1) {
    return { ...places[0] }
  }

  let legs = route.slice(1).map((slug, index) => legKm(roads, route[index], slug))

  if (legs.every((km) => km <= 0)) {
    legs = places.slice(1).map((point, index) => Math.hypot(point.x - places[index].x, point.y - places[index].y))
  }

  const total = legs.reduce((sum, km) => sum + km, 0)
  let left = Math.min(1, Math.max(0, progress)) * total

  for (let index = 0; index < legs.length; index++) {
    if (left <= legs[index] || index === legs.length - 1) {
      const share = legs[index] === 0 ? 1 : Math.min(1, left / legs[index])
      const [a, b] = [places[index], places[index + 1]]

      return { x: a.x + (b.x - a.x) * share, y: a.y + (b.y - a.y) * share }
    }

    left -= legs[index]
  }

  return { ...places[places.length - 1] }
}

/**
 * Where the name of a city goes on a map of this size, in pixels: under the circle of the city (whose middle is at `centre`), moved in from the side where it would be
 * cut, and above the circle when there is no room under it at the bottom of the map.
 */
export function labelPlace(card: { width: number; height: number }, centre: Point, label: { width: number; height: number }, gap = 28, margin = 2): { left: number; top: number } {
  const left = Math.min(Math.max(centre.x - label.width / 2, margin), card.width - label.width - margin)
  const below = centre.y + gap

  return { left: Math.max(margin, left), top: below + label.height > card.height - margin ? centre.y - gap - label.height : below }
}
