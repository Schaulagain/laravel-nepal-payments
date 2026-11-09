<?php

namespace App\Exceptions\Payments;

/**
 * Thrown when callback data fails signature or integrity checks,
 * which means it may have been tampered with.
 */
class InvalidSignatureException extends PaymentException {}
