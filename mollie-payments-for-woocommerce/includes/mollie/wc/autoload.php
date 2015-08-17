<?php
class Mollie_WC_Autoload
{
    /**
     * @var bool
     */
    static $registered = false;

    /**
     * @return bool
     */
    public static function register ()
    {
        if (self::$registered === true)
        {
            /*
             * Autoloader already registered
             */
            return false;
        }

        // Set registered
        self::$registered = true;

        return spl_autoload_register(array(__CLASS__, "autoload"));
    }

    /**
     * @return bool
     */
    public static function unregister ()
    {
        if (self::$registered === false)
        {
            /*
             * Autoloader not registered
             */
            return false;
        }

        return spl_autoload_unregister(array(__CLASS__, "autoload"));
    }

    /**
     * @param string $class_name
     */
    public static function autoload ($class_name)
    {
        // Path to includes directory
        $base_path = dirname(dirname(dirname(__FILE__)));

        if (stripos($class_name, "Mollie_WC_") === 0)
        {
            $class_path = $base_path . '/' . str_replace('_', '/', strtolower($class_name)) . '.php';

            if (file_exists($class_path))
            {
                require_once $class_path;
            }
        }
        // Mollie API client
        elseif (stripos($class_name, "Mollie_API_") === 0)
        {
            $class_path = $base_path . '/mollie-api-php/src/' . str_replace('_', '/', $class_name) . '.php';

            if (file_exists($class_path))
            {
                require_once $class_path;
            }
        }
    }
}
