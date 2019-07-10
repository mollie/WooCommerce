<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerce\Tests;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Mockery;
use PHPUnit\Framework\TestCase as PhpUniTestCase;
use PHPUnit_Framework_MockObject_MockBuilder;
use ReflectionException;
use ReflectionMethod;

/**
 * Class Testcase
 */
class TestCase extends PhpUniTestCase
{
    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        setUp();
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
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
     * @param string $sutMethod
     * @return PHPUnit_Framework_MockObject_MockBuilder
     */
    protected function buildTesteeMock(
        $className,
        $constructorArguments,
        $methods,
        $sutMethod
    ) {

        $testee = $this->getMockBuilder($className);
        $constructorArguments
            ? $testee->setConstructorArgs($constructorArguments)
            : $testee->disableOriginalConstructor();

        $methods and $testee->setMethods($methods);
        $sutMethod and $testee->setMethodsExcept([$sutMethod]);

        return $testee;
    }

    /**
     * Retrieve a Testee Mock to Test Protected Methods
     *
     * return MockBuilder
     * @param string $className
     * @param array $constructorArguments
     * @param string $method
     * @param array $methods
     * @return array
     * @throws ReflectionException
     */
    protected function buildTesteeMethodMock(
        $className,
        $constructorArguments,
        $method,
        $methods
    ) {

        $testee = $this->buildTesteeMock(
            $className,
            $constructorArguments,
            $methods,
            ''
        )->getMock();
        $reflectionMethod = new ReflectionMethod($className, $method);
        $reflectionMethod->setAccessible(true);
        return [
            $testee,
            $reflectionMethod,
        ];
    }
}
