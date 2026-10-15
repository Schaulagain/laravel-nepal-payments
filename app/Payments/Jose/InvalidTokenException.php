<?php

namespace App\Payments\Jose;

use App\Exceptions\Payments\InvalidSignatureException;

/**
 * A JOSE token that can't be decrypted, has a bad signature or fails its claim checks.
 */
class InvalidTokenException extends InvalidSignatureException {}
