import { useI18n } from 'vue-i18n'

/**
 * The name of a class of the competition (F to SS) as the game calls it: the message `car.className.{class}`, which the vocabulary of a game can rename, or the letters of the
 * class where there is no word for it.
 */
export function useRatingClass() {
  const { t, te } = useI18n()

  const ratingClassName = (slug: string): string => (te(`car.className.${slug}`) ? t(`car.className.${slug}`) : slug)

  return { ratingClassName }
}
