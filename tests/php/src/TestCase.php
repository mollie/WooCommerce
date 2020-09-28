<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests;

use Faker\Generator;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Generator|null
     */
    protected $faker;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Brain\faker();
    }
}
