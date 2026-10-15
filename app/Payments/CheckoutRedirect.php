<?php

namespace App\Payments;

/**
 * Where to send the customer to pay: a plain redirect, or a form POST
 * (eSewa requires the signed fields to be posted from the browser).
 */
final readonly class CheckoutRedirect
{
    /**
     * @param  array<string, string>  $fields
     */
    private function __construct(
        public string $url,
        public string $method,
        public array $fields = [],
    ) {}

    public static function to(string $url): self
    {
        return new self($url, 'GET');
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function post(string $url, array $fields): self
    {
        return new self($url, 'POST', $fields);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
