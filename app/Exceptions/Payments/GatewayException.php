<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * The gateway answered, but not with something we can use (an error code,
 * a missing field), or the gateway isn't configured.
 */
class GatewayException extends RuntimeException {}
