<?php

declare(strict_types=1);

namespace App\Content\Bridge;

/** An instruction for an AI, made: the text to give it, what it contains (the scope of an answer), and how big it is. */
final readonly class Brief
{
    /**
     * @param  list<string>  $scope
     * @param  int  $tokens  an estimate from the length of the text, not a count of any model's tokens
     */
    public function __construct(
        public string $text,
        public array $scope,
        public string $code,
        public string $pack,
        public string $snapshot,
        public int $bytes,
        public int $tokens,
    ) {}
}
