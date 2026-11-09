<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Anything that stops the app getting a usable, trusted answer from a gateway.
 * Catch this one to handle every gateway problem the same way.
 */
abstract class PaymentException extends RuntimeException {}
