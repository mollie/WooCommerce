<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests;

use Brain\Monkey;

/**
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 */
class OptionsMock extends \ArrayObject
{
    /**
     * @param array $options
     * @return void
     */
    public static function initialize(array $options = []): void
    {
        $instance = new static($options);

        Monkey\Functions\when('get_option')->alias(
            static function (string $name, $default = false) use (&$instance) {
                return $instance->get($name, $default);
            }
        );

        Monkey\Functions\when('delete_option')->alias(
            static function (string $option) use (&$instance): bool {
                return $instance->delete($option);
            }
        );

        Monkey\Functions\expect('update_option')->andReturnUsing(
            static function (string $name, $value, $autoload = null) use (&$instance): bool {
                if (!in_array($autoload, ['yes', 'no', true, false, null], true)) {
                    throw new \Exception("Invalid 'autoload' value updating option '{$name}'.");
                }

                return $instance->set($name, $value);
            }
        );

        Monkey\Functions\when('add_option')->alias(
            static function (
                string $name,
                $value = '',
                $deprecated = '',
                $autoload = 'yes'
            ) use (&$instance): bool {

                if ($deprecated !== '') {
                    throw new \Exception("Deprecated param used when adding option '{$name}'.");
                }

                if (!in_array($autoload, ['yes', 'no', true, false], true)) {
                    throw new \Exception("Invalid 'autoload' value adding option '{$name}'.");
                }

                return $instance->set($name, $value);
            }
        );
    }

    /**
     * @param string $name
     * @param bool $default
     * @return mixed
     */
    public function get(string $name, $default = false)
    {
        return $this->offsetExists($name) ? $this->offsetGet($name) : $default;
    }

    /**
     * @param string $name
     * @param $value
     * @return void
     */
    public function set(string $name, $value): bool
    {
        $exist = $this->offsetExists($name);
        $existing = $exist ? $this->offsetGet($name) : null;
        $this->offsetSet($name, $value);

        return !$exist || $existing !== $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function delete(string $name): bool
    {
        $exist = $this->offsetExists($name);
        $this->offsetUnset($name);

        return $exist;
    }
}
