<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Mollie\Psr\Container\ContainerInterface;
class PaymentGatewayValidator
{
    /** @var string[] */
    private array $requiredServices;
    private ContainerInterface $container;
    public function __construct(ContainerInterface $container, array $requiredServices)
    {
        $this->container = $container;
        $this->requiredServices = $requiredServices;
    }
    public function validate(string $gatewayId): bool
    {
        /** @var string $requiredService */
        foreach ($this->requiredServices as $requiredService) {
            $service = sprintf($requiredService, $gatewayId);
            if (!$this->container->has($service)) {
                throw new \Exception("Please define a service: '{$service}' for a gateway with the ID: {$gatewayId}");
            }
        }
        return \true;
    }
}
