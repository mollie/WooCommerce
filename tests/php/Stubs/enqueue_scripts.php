<?php
if (!function_exists('wp_register_style')) {
    function wp_register_style(...$args)
    {
        WpScriptsStub::instance()->register('style', ...$args);
    }
}
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args)
    {
        WpScriptsStub::instance()->enqueue('style', ...$args);
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script(...$args)
    {
        WpScriptsStub::instance()->register('script', ...$args);
    }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args)
    {
        WpScriptsStub::instance()->enqueue('script', ...$args);
    }
}

class WpScriptsStub
{
    const REGISTERED_KEY = 'registered';
    const ENQUEUED_KEY = 'enqueued';

    private $collection = [];

    public static function instance()
    {
        static $instance = null;

        if (!$instance) {
            $instance = new self;
        }

        return $instance;
    }

    public function register($what, ...$args)
    {
        $this->collection[$what][self::REGISTERED_KEY][$args[0]] = $args;
    }

    public function enqueue($what, ...$args)
    {
        $this->collection[$what][self::ENQUEUED_KEY][$args[0]] = $args;
    }

    public function isEnqueued($what, $handle)
    {
        return isset($this->collection[$what][self::ENQUEUED_KEY][$handle]);
    }

    public function isRegistered($what, $handle)
    {
        return isset($this->collection[$what][self::REGISTERED_KEY][$handle]);
    }

    public function registered($what, $handle)
    {
        return $this->fromCollectionWithStatus($what, self::REGISTERED_KEY, $handle);
    }

    public function enqueued($what, $handle)
    {
        return $this->fromCollectionWithStatus($what, self::ENQUEUED_KEY, $handle);
    }

    public function allRegistered($what)
    {
        return $this->allFromCollectionWithStatus($what, self::REGISTERED_KEY);
    }

    public function allEnqueued($what)
    {
        return $this->allFromCollectionWithStatus($what, self::ENQUEUED_KEY);
    }

    private function allFromCollectionWithStatus($what, $status)
    {
        return isset($this->collection[$what][$status]) ? $this->collection[$what][$status] : [];
    }

    private function fromCollectionWithStatus($what, $status, $handle)
    {
        return isset($this->collection[$what][$status][$handle])
            ? $this->collection[$what][$status][$handle]
            : [];
    }

    private function __construct()
    {
    }
}
