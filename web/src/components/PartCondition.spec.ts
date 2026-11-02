import { beforeEach, describe, expect, it } from 'vitest'

import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { mountView } from '@/test/mountView'

import PartCondition from './PartCondition.vue'

async function show(percent: number, unserviceable: boolean) {
  return (await mountView(PartCondition, '/garage', { props: { percent, unserviceable } })).wrapper
}

describe('PartCondition', () => {
  beforeEach(async () => {
    localStorage.clear()
    storeToken('device-token')
    await setLocale('en')
  })

  it('shows nothing for a part as good as new', async () => {
    expect((await show(100, false)).find('[data-test="part-condition"]').exists()).toBe(false)
  })

  it('marks a part unserviceable when the server says so, at any level of the line the rules set', async () => {
    // A game whose rules put the line at 40% says it at 35%.
    const wrapper = await show(35, true)

    expect(wrapper.get('[data-test="part-unserviceable"]').text()).toBe('Unserviceable')
    expect(wrapper.get('.bar').classes()).toContain('bar-bad')
  })

  it('does not mark a part that the server does not call unserviceable, even under a fifth of its life', async () => {
    // A game whose rules put the line at 5% does not say it at 15%.
    const wrapper = await show(15, false)

    expect(wrapper.find('[data-test="part-unserviceable"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="part-condition-value"]').text()).toContain('15')
  })
})
