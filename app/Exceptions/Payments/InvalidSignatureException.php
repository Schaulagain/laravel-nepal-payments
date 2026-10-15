<?php

namespace App\Exceptions\Payments;

use Exception;

/**
 * Thrown when callback data fails signature or integrity checks,
 * which means it may have been tampered with.
 */
class InvalidSignatureException extends Exception {}
