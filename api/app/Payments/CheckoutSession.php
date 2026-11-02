<?php

declare(strict_types=1);

namespace App\Payments;

use Carbon\CarbonInterface;

/** The page where a driver pays, as the provider gave it: what it is called there, where to send the driver, and until when it works. */
final readonly class CheckoutSession
{
    public function __construct(public string $ref, public string $url, public ?CarbonInterface $expiresAt = null) {}
}
