import { describe, expect, it } from 'vitest'

import { labelPlace, pointAlong, shortestRoute, type Road } from './route'

// A chain of four cities, and a long way round from a to d.
const chain: Road[] = [
  { from: 'a', to: 'b', km: 10 },
  { from: 'b', to: 'c', km: 30 },
  { from: 'c', to: 'd', km: 20 },
  { from: 'd', to: 'a', km: 100 },
]

const places: Record<string, { x: number; y: number }> = { a: { x: 0, y: 100 }, b: { x: 100, y: 100 }, c: { x: 100, y: 200 }, d: { x: 0, y: 200 } }
const placeOf = (slug: string) => places[slug]

describe('shortestRoute', () => {
  it('takes the way with the fewest kilometres, whatever the number of roads on it', () => {
    expect(shortestRoute(chain, 'a', 'd')).toEqual(['a', 'b', 'c', 'd'])
    expect(shortestRoute(chain, 'd', 'a')).toEqual(['d', 'c', 'b', 'a'])
  })

  it('runs the roads both ways, and goes straight when a road leads there', () => {
    expect(shortestRoute(chain, 'b', 'a')).toEqual(['b', 'a'])
  })

  it('is the city alone when the car is there already, and null when no road leads there', () => {
    expect(shortestRoute(chain, 'c', 'c')).toEqual(['c'])
    expect(shortestRoute(chain, 'a', 'nowhere')).toBeNull()
  })
})

describe('pointAlong', () => {
  it('counts the way in the kilometres of its roads: half of 60 km is the end of the second road', () => {
    // 10 km to b, 30 km to c: 30 km is two thirds of the road from b to c.
    const point = pointAlong(['a', 'b', 'c', 'd'], chain, placeOf, 0.5)

    expect(point?.x).toBe(100)
    expect(point?.y).toBeCloseTo(100 + (100 * 20) / 30, 9)
  })

  it('starts in the first city and ends in the last', () => {
    expect(pointAlong(['a', 'b', 'c', 'd'], chain, placeOf, 0)).toEqual({ x: 0, y: 100 })
    expect(pointAlong(['a', 'b', 'c', 'd'], chain, placeOf, 1)).toEqual({ x: 0, y: 200 })
  })

  it('counts the length of the lines when the roads say no kilometres, and is nowhere for a city it does not know', () => {
    expect(pointAlong(['a', 'b', 'c'], [], placeOf, 0.75)).toEqual({ x: 100, y: 150 })
    expect(pointAlong(['a', 'x'], chain, placeOf, 0.5)).toBeNull()
  })
})

describe('labelPlace', () => {
  const card = { width: 360, height: 277 }
  const label = { width: 100, height: 24 }

  it('puts the name under the middle of the city', () => {
    expect(labelPlace(card, { x: 180, y: 100 }, label)).toEqual({ left: 130, top: 128 })
  })

  it('moves the name in from the side where it would be cut', () => {
    expect(labelPlace(card, { x: 14, y: 100 }, label)).toEqual({ left: 2, top: 128 })
    expect(labelPlace(card, { x: 350, y: 100 }, label)).toEqual({ left: 258, top: 128 })
  })

  it('puts the name above the city at the bottom of the map', () => {
    expect(labelPlace(card, { x: 180, y: 260 }, label)).toEqual({ left: 130, top: 208 })
  })
})
