<?php

declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

/** What arrived at the webhook is not from the provider (the signature is wrong or missing), is too old, or is not an event. The answer is 400, and nothing is done. */
final class InvalidWebhook extends RuntimeException {}
