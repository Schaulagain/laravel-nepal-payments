<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\EsewaGateway;
use App\Payments\Gateways\KhaltiGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves a gateway driver by name, e.g. `$manager->gateway('esewa')`.
 */
class GatewayManager
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private const DRIVERS = [
        'esewa' => EsewaGateway::class,
        'khalti' => KhaltiGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function gateway(string $name): PaymentGateway
    {
        $driver = self::DRIVERS[$name] ?? throw new InvalidArgumentException("Unknown payment gateway [{$name}].");

        return $this->container->make($driver, ['config' => config("payments.gateways.{$name}")]);
    }

    /**
     * @return array<string, string> Gateway name => display label.
     */
    public function available(): array
    {
        return collect(self::DRIVERS)
            ->keys()
            ->mapWithKeys(fn (string $name): array => [$name => config("payments.gateways.{$name}.label")])
            ->all();
    }
}
