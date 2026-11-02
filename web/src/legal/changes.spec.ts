import { describe, expect, it } from 'vitest'

import { LEGAL_CHANGES, changesSince } from './changes'

const changes = {
  '2026-10-23': { en: ['The first.'], pl: ['Pierwsza.'] },
  '2026-11-01': { en: ['The second.'], pl: ['Druga.'] },
  '2026-12-01': { en: ['The third.'] },
}

describe('what changed in the legal texts', () => {
  it('has nothing written down beforehand: what counts as a change is for the one who answers for the texts', () => {
    expect(LEGAL_CHANGES).toEqual({})
    expect(changesSince('2026-01-01', '2026-11-01', 'en')).toEqual([])
  })

  it('lists the versions after the one the driver accepted, up to the one in force, oldest first', () => {
    expect(changesSince('2026-10-23', '2026-12-01', 'en', changes)).toEqual(['The second.', 'The third.'])
  })

  it('does not list the version the driver accepted, nor one that is not in force yet', () => {
    expect(changesSince('2026-11-01', '2026-11-01', 'en', changes)).toEqual([])
    expect(changesSince('2026-10-01', '2026-10-23', 'en', changes)).toEqual(['The first.'])
  })

  it('lists everything for a driver who accepted before the texts had a version', () => {
    expect(changesSince(null, '2026-11-01', 'en', changes)).toEqual(['The first.', 'The second.'])
  })

  it('speaks the language of the driver, and English where a version has no line in it', () => {
    expect(changesSince('2026-10-01', '2026-12-01', 'pl', changes)).toEqual(['Pierwsza.', 'Druga.', 'The third.'])
    expect(changesSince('2026-10-01', '2026-12-01', 'de', changes)).toEqual(['The first.', 'The second.', 'The third.'])
  })
})
