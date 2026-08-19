<?php
declare(strict_types=1);
namespace Erased\Contracts;
interface PaymentGatewayInterface {
    public function createCheckout(array $order): array;
    public function verifyWebhook(string $payload,array $headers): bool;
    public function refund(string $transactionId,?int $amountMinor=null): array;
}
