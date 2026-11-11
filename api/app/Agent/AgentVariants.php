<?php

declare(strict_types=1);

namespace App\Agent;

use App\Content\PackContent;
use App\Content\Publishing\CandidatePack;
use App\Content\Publishing\DerivedTables;
use App\Services\ContentPacks;

/**
 * The content a study of the assistant counts on (D12 of the design): `live` is the pack that players have, `drafts` is the content as it is being worked on (what a pack
 * made now would hold) and `scratch` is that with the scratch of the assistant laid over it. The drafts and the scratch are judged as a pack is and get their derived tables
 * made again, as the preview of the balance does; content that would not make a pack is refused with what is wrong with it.
 */
final class AgentVariants
{
    public const LIVE = 'live';

    public const DRAFTS = 'drafts';

    public const SCRATCH = 'scratch';

    public const ALL = [self::LIVE, self::DRAFTS, self::SCRATCH];

    /** How many errors of the validation a refusal names. */
    private const ISSUES = 20;

    public function __construct(
        private readonly ContentPacks $packs,
        private readonly CandidatePack $candidates,
        private readonly DerivedTables $tables,
    ) {}

    /**
     * The content of a variant and its mark (the same content has the same mark, so a study counted on it once need not be counted again).
     *
     * @param  array<string, string>  $replacements  the documents of the scratch, for `scratch`
     * @return array{content: PackContent, mark: string}
     *
     * @throws ToolRefused
     */
    public function content(string $variant, array $replacements = []): array
    {
        if ($variant === self::LIVE) {
            $live = $this->packs->activeSource() ?? throw new ToolRefused('No pack is live in this game yet: count on drafts.');

            return ['content' => $live, 'mark' => 'live:'.$live->fingerprint()];
        }

        $number = $this->packs->active()?->number ?? now()->format('Y.n').'.0';
        $candidate = $this->candidates->make($number);

        if ($variant === self::SCRATCH) {
            $candidate = $candidate->with($replacements);
        }

        $validation = $this->packs->validate($candidate, checkTables: false);

        if (! $validation->isValid()) {
            $lines = array_map(static fn (array $error): string => '- '.trim($error['file'].' '.$error['pointer']).': '.$error['message'], array_slice($validation->errors(), 0, self::ISSUES));

            throw new ToolRefused("The {$variant} would not make a pack, so the simulators cannot count it:\n".implode("\n", $lines));
        }

        $mark = $variant.':'.$candidate->fingerprint();

        return ['content' => $candidate->with($this->tables->make($candidate)), 'mark' => $mark];
    }
}
