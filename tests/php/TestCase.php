<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests;

use Faker\Generator;
use Faker;
use Mockery;
use PHPUnit_Framework_MockObject_MockBuilder;
use PHPUnit_Framework_MockObject_MockObject;
use WP_Error;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Xpmock\Reflection;
use Xpmock\TestCaseTrait;
use Inpsyde\ModularityTestCase\ModularityTestCase;

/**
 * Class Testcase
 */
class TestCase extends ModularityTestCase
{
    use TestCaseTrait;
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {

        parent::setUp();
        setUp();
        $this->setupFaker();

        when('__')->returnArg(1);
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();
    }

    /**
     * Create Faker instance
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function setupFaker()
    {
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        tearDown();
    }

    /**
     * Build the Testee Mock Object
     *
     * Basic configuration available for all of the testee objects, call `getMock` to get the mock.
     *
     * @param string $className
     * @param array $constructorArguments
     * @param array $methods
     * @return PHPUnit_Framework_MockObject_MockBuilder
     */
    protected function buildTesteeMock($className, $constructorArguments, $methods)
    {
        $testee = $this->getMockBuilder($className);
        $constructorArguments
            ? $testee->setConstructorArgs($constructorArguments)
            : $testee->disableOriginalConstructor();

        $testee->setMethods($methods);

        return $testee;
    }

    /**
     * Retrieve a Testee Mock to Test Protected Methods
     *
     * return MockBuilder
     * @param string $className
     * @param array $constructorArguments
     * @param array $methods
     * @return Reflection
     */
    protected function buildTesteeMethodMock($className, $constructorArguments, $methods)
    {
        $testee = $this->buildTesteeMock($className, $constructorArguments, $methods)->getMock();

        return $this->proxyFor($testee);
    }

    /**
     * Create a proxy for a mocked class
     *
     * @param PHPUnit_Framework_MockObject_MockObject $testee
     * @return Reflection
     */
    protected function proxyFor(PHPUnit_Framework_MockObject_MockObject $testee)
    {
        return $this->reflect($testee);
    }

    /**
     * @param string $code
     * @param string $message
     * @param string $data
     *
     * @return PHPUnit_Framework_MockObject_MockObject&WP_Error
     */
    protected function createWpError($code = '', $message = '', $data = '')
    {
        $mock = $this->getMockBuilder('WP_Error')
            ->setMethods(['get_error_code', 'get_error_message', 'get_error_data'])
            ->getMock();

        return $mock;
    }
}
