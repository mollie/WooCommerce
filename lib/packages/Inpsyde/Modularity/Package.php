<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Vendor\Inpsyde\Modularity;

use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Container\ContainerConfigurator;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Container\PackageProxyContainer;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\FactoryModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\Module;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties\Properties;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;

class Package
{
    /**
     * All the hooks fired in this class use this prefix.
     * @var string
     */
    private const HOOK_PREFIX = 'inpsyde.modularity.';

    /**
     * Identifier to access Properties in Container.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->boot();
     *
     * $container = $package->container();
     * $container->has(Package::PROPERTIES);
     * $container->get(Package::PROPERTIES);
     * </code>
     *
     * @var string
     */
    public const PROPERTIES = 'properties';

    /**
     * Custom action to be used to add Modules to the package.
     * It might also be used to access package properties.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_INIT),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_INIT = 'init';

    /**
     * Custom action which is triggered after the application
     * is booted to access container and properties.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_READY),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_READY = 'ready';

    /**
     * Custom action which is triggered when a failure happens during the building stage.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_FAILED_BUILD),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_FAILED_BUILD = 'failed-build';

    /**
     * Custom action which is triggered when a failure happens during the booting stage.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_FAILED_BOOT),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_FAILED_BOOT = 'failed-boot';

    /**
     * Custom action which is triggered when a package is connected.
     */
    public const ACTION_PACKAGE_CONNECTED = 'package-connected';

    /**
     * Custom action which is triggered when a package cannot be connected.
     */
    public const ACTION_FAILED_CONNECTION = 'failed-connection';

    /**
     * Module states can be used to get information about your module.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // false
     * $package->addModule(new SomeModule());
     * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // true
     * </code>
     */
    public const MODULE_ADDED = 'added';
    public const MODULE_NOT_ADDED = 'not-added';
    public const MODULE_REGISTERED = 'registered';
    public const MODULE_REGISTERED_FACTORIES = 'registered-factories';
    public const MODULE_EXTENDED = 'extended';
    public const MODULE_EXECUTED = 'executed';
    public const MODULE_EXECUTION_FAILED = 'executed-failed';
    public const MODULES_ALL = '*';

    /**
     * Custom states for the class.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->statusIs(Package::IDLE); // true
     * $package->boot();
     * $package->statusIs(Package::BOOTED); // true
     * </code>
     */
    public const STATUS_IDLE = 2;
    public const STATUS_INITIALIZED = 4;
    public const STATUS_MODULES_ADDED = 5;
    public const STATUS_READY = 7;
    public const STATUS_BOOTED = 8;
    public const STATUS_FAILED = -8;

    /**
     * Current state of the application.
     *
     * @see Package::STATUS_*
     *
     * @var int
     */
    private $status = self::STATUS_IDLE;

    /**
     * Contains the progress of all modules.
     *
     * @see Package::moduleProgress()
     *
     * @var array<string, list<string>>
     */
    private $moduleStatus = [self::MODULES_ALL => []];

    /**
     * Hashmap of where keys are names of connected packages, and values are boolean, true
     * if connection was successful.
     *
     * @see Package::connect()
     *
     * @var array<string, bool>
     */
    private $connectedPackages = [];

    /**
     * @var list<ExecutableModule>
     */
    private $executables = [];

    /**
     * @var Properties
     */
    private $properties;

    /**
     * @var ContainerConfigurator
     */
    private $containerConfigurator;

    /**
     * @var bool
     */
    private $built = false;

    /**
     * @var bool
     */
    private $hasContainer = false;

    /**
     * @var \Throwable|null
     */
    private $lastError = null;

    /**
     * @param Properties $properties
     * @param ContainerInterface[] $containers
     *
     * @return Package
     */
    public static function new(Properties $properties, ContainerInterface  ...$containers): Package
    {
        return new self($properties, ...$containers);
    }

    /**
     * @param Properties $properties
     * @param ContainerInterface[] $containers
     */
    private function __construct(Properties $properties, ContainerInterface ...$containers)
    {
        $this->properties = $properties;

        $this->containerConfigurator = new ContainerConfigurator($containers);
        $this->containerConfigurator->addService(
            self::PROPERTIES,
            static function () use ($properties) {
                return $properties;
            }
        );
    }

    /**
     * @param Module $module
     *
     * @return static
     * @throws \Exception
     */
    public function addModule(Module $module): Package
    {
        try {
            $this->assertStatus(self::STATUS_IDLE, sprintf('add module %s', $module->id()));

            $registeredServices = $this->addModuleServices(
                $module,
                self::MODULE_REGISTERED
            );
            $registeredFactories = $this->addModuleServices(
                $module,
                self::MODULE_REGISTERED_FACTORIES
            );
            $extended = $this->addModuleServices(
                $module,
                self::MODULE_EXTENDED
            );
            $isExecutable = $module instanceof ExecutableModule;

            // ExecutableModules are collected and executed on Package::boot()
            // when the Container is being compiled.
            if ($isExecutable) {
                /** @var ExecutableModule $module */
                $this->executables[] = $module;
            }

            $added = $registeredServices || $registeredFactories || $extended || $isExecutable;
            $status = $added ? self::MODULE_ADDED : self::MODULE_NOT_ADDED;
            $this->moduleProgress($module->id(), $status);
        } catch (\Throwable $throwable) {
            $this->handleFailure($throwable, self::ACTION_FAILED_BUILD);
        }

        return $this;
    }

    /**
     * @param Package $package
     * @return bool
     * @throws \Exception
     */
    public function connect(Package $package): bool
    {
        try {
            if ($package === $this) {
                return false;
            }

            $packageName = $package->name();
            $errorData = ['package' => $packageName, 'status' => $this->status];
            $errorMessage = "Failed connecting package {$packageName}";

            // Don't connect, if already connected
            if (array_key_exists($packageName, $this->connectedPackages)) {
                $error = "{$errorMessage} because it was already connected.";
                do_action(
                    $this->hookName(self::ACTION_FAILED_CONNECTION),
                    $packageName,
                    new \WP_Error('already_connected', $error, $errorData)
                );

                throw new \Exception($error, 0, $this->lastError);
            }

            // Don't connect, if already booted or boot failed
            $failed = $this->statusIs(self::STATUS_FAILED);
            if ($failed || $this->statusIs(self::STATUS_BOOTED)) {
                $status = $failed ? 'errored' : 'booted';
                $error = "{$errorMessage} to a {$status} package.";
                do_action(
                    $this->hookName(self::ACTION_FAILED_CONNECTION),
                    $packageName,
                    new \WP_Error("no_connect_on_{$status}", $error, $errorData)
                );

                throw new \Exception($error, 0, $this->lastError);
            }

            $this->connectedPackages[$packageName] = true;

            // We put connected package's properties in this package's container, so that in modules
            // "run" method we can access them if we need to.
            $this->containerConfigurator->addService(
                sprintf('%s.%s', $package->name(), self::PROPERTIES),
                static function () use ($package): Properties {
                    return $package->properties();
                }
            );

            // If the other package is booted, we can obtain a container, otherwise
            // we build a proxy container
            $container = $package->statusIs(self::STATUS_BOOTED)
                ? $package->container()
                : new PackageProxyContainer($package);

            $this->containerConfigurator->addContainer($container);

            do_action(
                $this->hookName(self::ACTION_PACKAGE_CONNECTED),
                $packageName,
                $this->status,
                $container instanceof PackageProxyContainer
            );

            return true;
        } catch (\Throwable $throwable) {
            if (isset($packageName)) {
                $this->connectedPackages[$packageName] = false;
            }
            $this->handleFailure($throwable, self::ACTION_FAILED_BUILD);

            return false;
        }
    }

    /**
     * @return static
     */
    public function build(): Package
    {
        try {
            // Don't allow building the application multiple times.
            $this->assertStatus(self::STATUS_IDLE, 'build package');

            do_action(
                $this->hookName(self::ACTION_INIT),
                $this
            );
            // Changing the status here ensures we can not call this method again, and also we can not
            // add new modules, because both this and `addModule()` methods check for idle status.
            // For backward compatibility, adding new modules via `boot()` will still be possible, even
            // if deprecated, at the condition that the container was not yet accessed at that point.
            $this->progress(self::STATUS_INITIALIZED);
        } catch (\Throwable $throwable) {
            $this->handleFailure($throwable, self::ACTION_FAILED_BUILD);
        } finally {
            $this->built = true;
        }

        return $this;
    }

    /**
     * @param Module ...$defaultModules Deprecated, use `addModule()` to add default modules.
     * @return bool
     *
     * @throws \Throwable
     */
    public function boot(Module ...$defaultModules): bool
    {
        try {
            // Call build() if not called yet, and ensure any new module passed here is added
            // as well, throwing if the container was already built.
            $this->doBuild(...$defaultModules);

            // Don't allow booting the application multiple times.
            $this->assertStatus(self::STATUS_MODULES_ADDED, 'boot application', '<');
            $this->assertStatus(self::STATUS_FAILED, 'boot application', '!=');

            $this->progress(self::STATUS_MODULES_ADDED);

            $this->doExecute();

            $this->progress(self::STATUS_READY);

            do_action(
                $this->hookName(self::ACTION_READY),
                $this
            );
        } catch (\Throwable $throwable) {
            $this->handleFailure($throwable, self::ACTION_FAILED_BOOT);

            return false;
        }

        $this->progress(self::STATUS_BOOTED);

        return true;
    }

    /**
     * @param Module ...$defaultModules
     * @return void
     */
    private function doBuild(Module ...$defaultModules): void
    {
        if ($defaultModules) {
            $this->deprecatedArgument(
                sprintf(
                    'Passing default modules to %1$s::boot() is deprecated since version 1.7.0.'
                    . ' Please add modules via %1$s::addModule().',
                    __CLASS__
                ),
                __METHOD__,
                '1.7.0'
            );
        }

        if (!$this->built) {
            array_map([$this, 'addModule'], $defaultModules);
            $this->build();

            return;
        }

        if (
            !$defaultModules
            || ($this->status >= self::STATUS_MODULES_ADDED)
            || ($this->statusIs(self::STATUS_FAILED))
        ) {
            // if we don't have default modules, there's nothing to do, and if the status is beyond
            // "modules added" or is failed, we do nothing as well and let `boot()` throw.
            return;
        }

        $backup = $this->status;

        try {
            // simulate idle status to prevent `addModule()` from throwing
            // only if we don't have a container yet
            $this->hasContainer or $this->status = self::STATUS_IDLE;

            foreach ($defaultModules as $defaultModule) {
                // If a module was added by `build()` or `addModule()` we can skip it, a
                // deprecation was trigger to make it noticeable without breakage
                if (!$this->moduleIs($defaultModule->id(), self::MODULE_ADDED)) {
                    $this->addModule($defaultModule);
                }
            }
        } finally {
            $this->status = $backup;
        }
    }

    /**
     * @param Module $module
     * @param string $status
     * @return bool
     */
    private function addModuleServices(Module $module, string $status): bool
    {
        $services = null;
        $addCallback = null;
        switch ($status) {
            case self::MODULE_REGISTERED:
                $services = $module instanceof ServiceModule ? $module->services() : null;
                $addCallback = [$this->containerConfigurator, 'addService'];
                break;
            case self::MODULE_REGISTERED_FACTORIES:
                $services = $module instanceof FactoryModule ? $module->factories() : null;
                $addCallback = [$this->containerConfigurator, 'addFactory'];
                break;
            case self::MODULE_EXTENDED:
                $services = $module instanceof ExtendingModule ? $module->extensions() : null;
                $addCallback = [$this->containerConfigurator, 'addExtension'];
                break;
        }

        if (!$services) {
            return false;
        }

        $ids = [];
        array_walk(
            $services,
            static function (callable $service, string $id) use ($addCallback, &$ids) {
                /** @var callable(string, callable) $addCallback */
                $addCallback($id, $service);
                /** @var list<string> $ids */
                $ids[] = $id;
            }
        );
        /** @var list<string> $ids */
        $this->moduleProgress($module->id(), $status, $ids);

        return true;
    }

    /**
     * @return void
     *
     * @throws \Throwable
     */
    private function doExecute(): void
    {
        foreach ($this->executables as $executable) {
            $success = $executable->run($this->container());
            $this->moduleProgress(
                $executable->id(),
                $success
                    ? self::MODULE_EXECUTED
                    : self::MODULE_EXECUTION_FAILED
            );
        }
    }

    /**
     * @param string $moduleId
     * @param string $status
     * @param list<string>|null $serviceIds
     *
     * @return  void
     */
    private function moduleProgress(string $moduleId, string $status, ?array $serviceIds = null)
    {
        isset($this->moduleStatus[$status]) or $this->moduleStatus[$status] = [];
        $this->moduleStatus[$status][] = $moduleId;

        if (!$serviceIds || !$this->properties->isDebug()) {
            $this->moduleStatus[self::MODULES_ALL][] = "{$moduleId} {$status}";

            return;
        }

        $description = sprintf('%s %s (%s)', $moduleId, $status, implode(', ', $serviceIds));
        $this->moduleStatus[self::MODULES_ALL][] = $description;
    }

    /**
     * @return array<string, list<string>>
     */
    public function modulesStatus(): array
    {
        return $this->moduleStatus;
    }

    /**
     * @return array<string, bool>
     */
    public function connectedPackages(): array
    {
        return $this->connectedPackages;
    }

    /**
     * @param string $packageName
     * @return bool
     */
    public function isPackageConnected(string $packageName): bool
    {
        return $this->connectedPackages[$packageName] ?? false;
    }

    /**
     * @param string $moduleId
     * @param string $status
     *
     * @return bool
     */
    public function moduleIs(string $moduleId, string $status): bool
    {
        return in_array($moduleId, $this->moduleStatus[$status] ?? [], true);
    }

    /**
     * Return the filter name to be used to extend modules of the plugin.
     *
     * If the plugin is single file `my-plugin.php` in plugins folder the filter name will be:
     * `inpsyde.modularity.my-plugin`.
     *
     * If the plugin is in a sub-folder e.g. `my-plugin/index.php` the filter name will be:
     * `inpsyde.modularity.my-plugin` anyway, so the file name is not relevant.
     *
     * @param string $suffix
     *
     * @return string
     * @see Package::name()
     *
     */
    public function hookName(string $suffix = ''): string
    {
        $filter = self::HOOK_PREFIX . $this->properties->baseName();

        if ($suffix) {
            $filter .= '.' . $suffix;
        }

        return $filter;
    }

    /**
     * @return Properties
     */
    public function properties(): Properties
    {
        return $this->properties;
    }

    /**
     * @return ContainerInterface
     *
     * @throws \Exception
     */
    public function container(): ContainerInterface
    {
        $this->assertStatus(self::STATUS_INITIALIZED, 'obtain the container instance', '>=');
        $this->hasContainer = true;

        return $this->containerConfigurator->createReadOnlyContainer();
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->properties->baseName();
    }

    /**
     * @param int $status
     */
    private function progress(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    public function statusIs(int $status): bool
    {
        return $this->status === $status;
    }

    /**
     * @param \Throwable $throwable
     * @param Package::ACTION_FAILED_* $action
     * @return void
     * @throws \Throwable
     */
    private function handleFailure(\Throwable $throwable, string $action): void
    {
        $this->progress(self::STATUS_FAILED);
        $hook = $this->hookName($action);
        did_action($hook) or do_action($hook, $throwable);

        if ($this->properties->isDebug()) {
            throw $throwable;
        }

        $this->lastError = $throwable;
    }

    /**
     * @param int $status
     * @param string $action
     * @param string $operator
     *
     * @throws \Exception
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function assertStatus(int $status, string $action, string $operator = '=='): void
    {
        if (!version_compare((string) $this->status, (string) $status, $operator)) {
            throw new \Exception(
                sprintf("Can't %s at this point of application.", $action),
                0,
                $this->lastError
            );
        }
    }

    /**
     * Similar to WP's `_deprecated_argument()`, but executes regardless of WP_DEBUG and without
     * translated message (so without attempting loading translation files).
     *
     * @param string $message
     * @param string $function
     * @param string $version
     *
     * @return void
     */
    private function deprecatedArgument(string $message, string $function, string $version): void
    {
        do_action('deprecated_argument_run', $function, $message, $version);

        if (apply_filters('deprecated_argument_trigger_error', true)) {
            trigger_error($message, \E_USER_DEPRECATED);
        }
    }
}
