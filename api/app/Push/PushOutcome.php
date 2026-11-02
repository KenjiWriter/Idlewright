<?php

declare(strict_types=1);

namespace App\Push;

/** What the push service did with a message. */
enum PushOutcome
{
    /** It took the message (201): it will deliver it, now or when the device is back. */
    case Sent;

    /** The subscription is dead (404 or 410): the device is gone, or the driver took the permission back. It is deleted at once. */
    case Gone;

    /** Not now (429, 5xx, no connection): the same message is tried again later, after `retryAfter` seconds when the service said. */
    case Retry;

    /** It will not take this message, and sending it again does not help (the key does not match, the message is too large). */
    case Rejected;
}
