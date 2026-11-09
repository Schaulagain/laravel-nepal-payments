<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\EsewaGateway;
use App\Payments\Gateways\HblGateway;
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
        'hbl' => HblGateway::class,
    ];

    /**
     * Config keys each gateway needs before it can take payments.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED = [
        'esewa' => EsewaGateway::REQUIRED_CONFIG,
        'khalti' => KhaltiGateway::REQUIRED_CONFIG,
        'hbl' => HblGateway::REQUIRED_CONFIG,
    ];

    public function __construct(private Container $container) {}

    public function gateway(string $name): PaymentGateway
    {
        $driver = self::DRIVERS[$name] ?? throw new InvalidArgumentException("Unknown payment gateway [{$name}].");

        return $this->container->make($driver, ['config' => config("payments.gateways.{$name}")]);
    }

    /** HBL by its own type, for the backend notification it alone sends. */
    public function hbl(): HblGateway
    {
        /** @var HblGateway */
        return $this->gateway('hbl');
    }

    /** The name customers see for a gateway, e.g. "eSewa". */
    public function label(string $name): string
    {
        return (string) config("payments.gateways.{$name}.label");
    }

    public function isConfigured(string $name): bool
    {
        return collect(self::REQUIRED[$name] ?? [])
            ->every(fn (string $key): bool => filled(config("payments.gateways.{$name}.{$key}")));
    }

    /**
     * Gateways that are set up and can be used at checkout.
     *
     * @return array<string, string> Gateway name => display label.
     */
    public function available(): array
    {
        return collect($this->all())
            ->filter(fn (string $label, string $name): bool => $this->isConfigured($name))
            ->all();
    }

    /**
     * Every gateway the app knows about, set up or not.
     *
     * @return array<string, string> Gateway name => display label.
     */
    public function all(): array
    {
        return collect(self::DRIVERS)
            ->keys()
            ->mapWithKeys(fn (string $name): array => [$name => $this->label($name)])
            ->all();
    }
}
