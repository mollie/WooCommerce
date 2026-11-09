<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Exception;
use Mollie\Psr\Container\ContainerInterface;
class I18n
{
    protected ContainerInterface $serviceLocator;
    /**
     * @var array<string, array<string, string|callable(array):string>>
     */
    protected array $messagesGatewayMap = [];
    public function __construct(ContainerInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }
    /**
     * @throws Exception
     */
    public function translate(string $messageKey, string $gatewayId, array $params = []): string
    {
        $messages = $this->messageMap($gatewayId);
        $message = $messages[$messageKey] ?? null;
        if ($message === null) {
            throw new Exception("Message {$messageKey} not found.");
        }
        if (is_callable($message)) {
            return (string) $message($params);
        }
        return (string) $message;
    }
    protected function messageMap(string $gatewayId): array
    {
        if (!isset($this->messagesGatewayMap[$gatewayId])) {
            $this->messagesGatewayMap[$gatewayId] = array_merge((array) $this->serviceLocator->get('payment_gateways.i18n.messages'), $this->serviceLocator->has("payment_gateway.{$gatewayId}.i18n.messages") ? (array) $this->serviceLocator->get("payment_gateway.{$gatewayId}.i18n.messages") : []);
        }
        return $this->messagesGatewayMap[$gatewayId];
    }
}
