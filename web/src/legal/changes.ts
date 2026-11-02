import type { AppLocale } from '@/i18n/locales'

/**
 * What changed in the terms and the privacy policy, version by version, in the words of each language: the list the screen that asks for
 * a new consent shows. The key is the version (the day, as `LEGAL_VERSION` says it). Whoever changes the texts adds a line here for the
 * new version, in all five languages; a version with no entry is shown with a general sentence and the links to the texts.
 *
 * Nothing is written here beforehand: what counts as a change, and how it is put, is for the one who answers for the texts.
 */
export const LEGAL_CHANGES: Record<string, Partial<Record<AppLocale, string[]>>> = {}

/**
 * The lines to show a driver who accepted `accepted` (null: before the texts had a version) while `current` is in force: the entries of
 * the versions after the one they accepted, up to the current one, oldest first, in their language (English when it has no entry for it).
 */
export function changesSince(accepted: string | null, current: string, locale: AppLocale, changes = LEGAL_CHANGES): string[] {
  return Object.keys(changes)
    .filter((version) => version <= current && (accepted === null || version > accepted))
    .sort()
    .flatMap((version) => changes[version]?.[locale] ?? changes[version]?.en ?? [])
}
