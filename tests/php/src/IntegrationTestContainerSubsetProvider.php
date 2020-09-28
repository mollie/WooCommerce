<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests;

use Inpsyde\Fasti\Application\Core\App;
use Inpsyde\Fasti\Application\Core\ContainerReadOnlySubset;
use Inpsyde\Fasti\Application\Core\ServiceProvider;

class IntegrationTestContainerSubsetProvider implements ServiceProvider
{
    /**
     * @var IntegrationTestCase
     */
    private $test;

    /**
     * @var string[]
     */
    private $props;

    /**
     * @param IntegrationTestCase $test
     * @param string ...$props
     */
    public function __construct(IntegrationTestCase $test, string ...$props)
    {
        $this->test = $test;
        $this->props = $props;
    }

    /**
     * @param App $container
     *
     * @return void
     */
    public function register(App $container): void
    {
    }

    /**
     * @param App $container
     *
     * @return void
     */
    public function provide(App $container): void
    {
        $this->test->useContainerSubset(ContainerReadOnlySubset::new($container, ...$this->props));
    }
}
