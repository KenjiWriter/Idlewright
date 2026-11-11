<?php

declare(strict_types=1);

namespace App\Agent;

use RuntimeException;

/**
 * A tool of the assistant cannot do what it was asked (no such car, a section that does not exist): the message goes back to the assistant as the error of the call.
 */
final class ToolRefused extends RuntimeException {}
