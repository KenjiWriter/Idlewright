<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A change of the sign-ins or of the consent of an account that is refused. It has a stable `code` for the client and a
 * status; the message is the translated text `account.refused.<code>` (see bootstrap/app.php).
 */
final class AccountRefusedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details  what the client needs besides the code, answered next to it
     * @param  array<string, scalar>  $replace  the values of the message
     */
    public function __construct(
        public readonly string $reason,
        public readonly int $status,
        public readonly array $details = [],
        public readonly array $replace = [],
    ) {
        parent::__construct("Account change refused: {$reason}.");
    }

    /** The driver is not allowed to do this with their account. */
    public static function forbidden(string $reason, array $details = []): self
    {
        return new self($reason, 403, $details);
    }

    /** The request is well formed but cannot be done to this account. */
    public static function unprocessable(string $reason, array $details = [], array $replace = []): self
    {
        return new self($reason, 422, $details, $replace);
    }

    public static function notFound(string $reason): self
    {
        return new self($reason, 404);
    }

    public static function conflict(string $reason, array $details = []): self
    {
        return new self($reason, 409, $details);
    }
}
