<?php

declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

/** The game cannot take money now: no provider is set up, it is in a mode this installation does not allow, or the provider could not be reached. */
final class PaymentsUnavailable extends RuntimeException {}
