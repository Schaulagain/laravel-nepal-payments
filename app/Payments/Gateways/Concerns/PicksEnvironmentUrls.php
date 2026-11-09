<?php

namespace App\Payments\Gateways\Concerns;

/**
 * Every gateway config has `urls.sandbox` and `urls.production`, and a `sandbox` flag.
 */
trait PicksEnvironmentUrls
{
    private function urls(): mixed
    {
        return $this->config['urls'][$this->config['sandbox'] ? 'sandbox' : 'production'];
    }
}
