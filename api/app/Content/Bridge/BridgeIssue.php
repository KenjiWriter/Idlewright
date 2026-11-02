<?php

declare(strict_types=1);

namespace App\Content\Bridge;

/**
 * One thing the bridge has to say about a block of changes: a refusal (an error stops the block) or a remark (a warning does not). The code is a short name that does
 * not change, so that a message pasted back to the AI says the same thing each time; the message is for the person and the AI to read.
 */
final readonly class BridgeIssue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @param  int|null  $op  the number of the operation (the first is 1), when the issue is about one
     * @param  string|null  $opId  the `id` the block gave to that operation
     * @param  string|null  $where  the document and the place in it, or the place in the block
     */
    public function __construct(
        public string $severity,
        public string $code,
        public string $message,
        public ?int $op = null,
        public ?string $opId = null,
        public ?string $where = null,
    ) {}

    public static function error(string $code, string $message, ?int $op = null, ?string $opId = null, ?string $where = null): self
    {
        return new self(self::ERROR, $code, $message, $op, $opId, $where);
    }

    public static function warning(string $code, string $message, ?int $op = null, ?string $opId = null, ?string $where = null): self
    {
        return new self(self::WARNING, $code, $message, $op, $opId, $where);
    }

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    /** One line, as it is written when the AI is told what to fix. */
    public function line(): string
    {
        $about = match (true) {
            $this->op !== null && $this->opId !== null => "op {$this->op} (id {$this->opId})",
            $this->op !== null => "op {$this->op}",
            default => 'the block',
        };

        return "{$about} [{$this->code}]".($this->where !== null ? " at {$this->where}" : '').": {$this->message}";
    }

    /** @return array{severity: string, code: string, message: string, op: int|null, id: string|null, where: string|null} */
    public function toArray(): array
    {
        return ['severity' => $this->severity, 'code' => $this->code, 'message' => $this->message, 'op' => $this->op, 'id' => $this->opId, 'where' => $this->where];
    }
}
