import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import TrackThumb from './TrackThumb.vue'

/** The points of the drawn line as numbers. */
function drawn(wrapper: ReturnType<typeof mount>): number[][] {
  return wrapper
    .get('[data-test="thumb-line"]')
    .attributes('points')!
    .split(' ')
    .map((pair) => pair.split(',').map(Number))
}

describe('TrackThumb', () => {
  it('draws the line fitted into the box, with y growing upwards, and marks the start', () => {
    const wrapper = mount(TrackThumb, {
      props: {
        label: 'The line',
        points: [
          [0, 0],
          [200, 0],
          [200, 100],
        ],
      },
    })
    const points = drawn(wrapper)

    expect(points).toHaveLength(3)
    // The first leg runs left to right along the bottom, the second goes up: a smaller y on the screen is higher.
    expect(points[1]![0]).toBeGreaterThan(points[0]![0]!)
    expect(points[1]![1]).toBeCloseTo(points[0]![1]!, 1)
    expect(points[2]![1]).toBeLessThan(points[1]![1]!)
    expect(wrapper.get('[data-test="thumb-start"]').attributes('cx')).toBe(String(points[0]![0]))
    expect(wrapper.get('svg').attributes('aria-label')).toBe('The line')
  })

  it('keeps the proportions of a long thin track', () => {
    const wrapper = mount(TrackThumb, {
      props: {
        label: 'The line',
        points: [
          [0, 0],
          [1000, 0],
          [1000, 10],
        ],
      },
    })
    const points = drawn(wrapper)
    const width = points[2]![0]! - points[0]![0]!
    const height = points[0]![1]! - points[2]![1]!

    expect(width / height).toBeCloseTo(100, -1)
  })
})
