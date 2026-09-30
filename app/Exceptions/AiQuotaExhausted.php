<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The AI text provider (Qwen) answered 429 "quota exhausted": the account's
 * plan is spent until it is topped up. Saying it again cannot help.
 */
class AiQuotaExhausted extends RuntimeException {}
