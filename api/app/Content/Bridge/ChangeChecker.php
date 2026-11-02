<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Definitions\JsonDocument;
use App\Content\Publishing\CandidatePack;
use App\Games\CurrentGame;
use App\Models\Game;
use App\Services\ContentPacks;

/**
 * Looks at what a person pasted without changing anything: reads the block, checks that it is for this game, works out every number it changes, and has the whole content
 * judged as it would be with those numbers in it. The answer says what is wrong, in a form that can be given back to the AI, and what each number was and would be.
 */
final class ChangeChecker
{
    private const FOLDERS = ['rules' => 'rules', 'part' => 'parts', 'vehicle' => 'vehicles'];

    public function __construct(
        private readonly ChangeBlockParser $parser,
        private readonly ChangeResolver $resolver,
        private readonly ContentSnapshot $snapshot,
        private readonly ContentPacks $packs,
        private readonly CandidatePack $candidates,
        private readonly CurrentGame $current,
    ) {}

    /**
     * @param  list<string>|null  $scope  what the instruction contained (see `ChangeResolver::resolve`); null for no limit
     */
    public function check(string $text, ?array $scope = null): ChangeCheck
    {
        ['block' => $block, 'issues' => $issues] = $this->parser->parse($text);

        if ($block === null) {
            return ChangeCheck::refused($issues);
        }

        $game = Game::query()->find($this->current->id())?->slug;

        if ($block['game'] !== $game) {
            return ChangeCheck::refused([BridgeIssue::error('wrong_game', "The block is for the game {$block['game']}, and this is the game {$game}. Nothing was changed.", where: 'game')])->withBlock($block);
        }

        $check = $this->resolver->resolve($block, $scope)->withIssues($this->baseRemarks($block));

        return $check->errors() === [] && $check->changes !== [] ? $check->withIssues($this->judgeContent($check)) : $check;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<BridgeIssue>
     */
    private function baseRemarks(array $block): array
    {
        $remarks = [];
        $live = $this->packs->active()?->number;

        if ($live !== null && $block['base']['pack'] !== $live) {
            $remarks[] = BridgeIssue::warning('base_pack', "The block was made from the pack {$block['base']['pack']}, and the pack that is live is {$live}. Look at the numbers before and after.", where: 'base');
        }

        if ($block['base']['snapshot'] !== $this->snapshot->current()) {
            $remarks[] = BridgeIssue::warning('base_changed', 'The content has changed since the instruction was made. The changes are worked out on the content as it is now; look at the numbers before and after.', where: 'base');
        }

        return $remarks;
    }

    /**
     * The whole content as it would be, judged as a pack is: the schemas of the documents, what the rules point at, the numbers that depend on each other.
     *
     * @return list<BridgeIssue>
     */
    private function judgeContent(ChangeCheck $check): array
    {
        $replace = [];

        foreach ($check->documents as $key => $document) {
            [$kind, $slug] = explode(':', $key, 2);
            $replace[self::FOLDERS[$kind].'/'.$slug] = JsonDocument::encode($document);
        }

        // The manifest of the candidate has to be a number a pack can have; the number of the one that is live will do, as nothing is made of this.
        $number = $this->packs->active()?->number ?? now()->format('Y.n').'.0';
        $result = $this->packs->validate($this->candidates->make($number)->with($replace), checkTables: false);

        return array_map(
            static fn (array $error): BridgeIssue => BridgeIssue::error('invalid_content', $error['message'], where: trim($error['file'].' '.$error['pointer'])),
            $result->errors(),
        );
    }
}
