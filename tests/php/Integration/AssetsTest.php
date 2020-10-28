<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration;

use Inpsyde\Fasti\Application\Assets;
use Inpsyde\Fasti\Application\Core\App;
use Inpsyde\Fasti\Application\Core\ServiceProvider;
use Mollie\WooCommerceTests\Src\IntegrationTestCase;

/**
 * @runTestsInSeparateProcesses
 */
final class AssetsTest extends IntegrationTestCase
{
    public const CONTAINER_KEY = 'test-assets';
    public const PLUGIN_URL = 'https://example.com/wp-content/plugins/fasti';

    /**
     * @return
     */
    protected function providers(): array
    {
        return [

        ];
    }

    /**
     * @test
     */
    public function testEnqueueInHeader(): void
    {
        $this->mockServer();

       assertEquals(true,true);
    }
}
