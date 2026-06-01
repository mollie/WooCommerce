<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Activation;

use Mockery;
use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\Activation\Migrations\MigratorInterface;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

use function Brain\Monkey\Functions\when;

class ActivationModuleMigrationTest extends TestCase
{
    /** @var list<string> */
    public $migrateAttempts = [];

    /** @var list<array{0:string,1:string}> */
    public $updateOptionCalls = [];

    /** @var bool */
    public $markUpdatedCalled = false;

    /** @var bool */
    public $initDbCalled = false;

    /** @var list<string> */
    public $errorLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateAttempts = [];
        $this->updateOptionCalls = [];
        $this->markUpdatedCalled = false;
        $this->initDbCalled = false;
        $this->errorLogs = [];

        $self = $this;
        when('update_option')->alias(function ($name, $value, $autoload = null) use ($self) {
            $self->updateOptionCalls[] = [$name, $value];
            return true;
        });
    }

    private function makeMigrator(string $version, ?Throwable $throwOnMigrate = null): MigratorInterface
    {
        $self = $this;
        return new class ($self, $version, $throwOnMigrate) implements MigratorInterface {
            /** @var ActivationModuleMigrationTest */
            private $self;
            /** @var string */
            private $version;
            /** @var ?Throwable */
            private $throw;

            public function __construct($self, string $version, ?Throwable $throw)
            {
                $this->self = $self;
                $this->version = $version;
                $this->throw = $throw;
            }

            public function targetVersion(): string
            {
                return $this->version;
            }

            public function migrate(): void
            {
                $this->self->migrateAttempts[] = $this->version;
                if ($this->throw !== null) {
                    throw $this->throw;
                }
            }
        };
    }

    private function makeModule(string $pluginVersion, array $migrators): object
    {
        $self = $this;

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->andReturnUsing(function ($message, $context = []) use ($self): void {
                $self->errorLogs[] = (string) $message;
            });
        $logger->shouldIgnoreMissing();

        $module = new class ($self) extends ActivationModule {
            /** @var ActivationModuleMigrationTest */
            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            public function runMigrationsPublic(): bool
            {
                return $this->runMigrations();
            }

            public function callPluginInit(): void
            {
                $this->pluginInit();
            }

            protected function markUpdatedOrNew()
            {
                $this->testCase->markUpdatedCalled = true;
            }

            public function initDb(): void
            {
                $this->testCase->initDbCalled = true;
            }
        };

        $ref = new ReflectionClass(ActivationModule::class);
        foreach (
            [
                'pluginVersion' => $pluginVersion,
                'migrators' => $migrators,
                'logger' => $logger,
            ] as $property => $value
        ) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($module, $value);
        }

        return $module;
    }

    // L1: V_old=8.1.5, V_new=8.1.7, migrators [8.1.6, 8.1.7, 8.2.0]
    //     → only 8.1.6 and 8.1.7 run, in that order
    public function testL1RunsOnlyApplicableMigratorsInOrder(): void
    {
        when('get_option')->justReturn('8.1.5');

        $module = $this->makeModule('8.1.7', [
            $this->makeMigrator('8.1.6'),
            $this->makeMigrator('8.1.7'),
            $this->makeMigrator('8.2.0'),
        ]);

        $result = $module->runMigrationsPublic();

        self::assertTrue($result);
        self::assertSame(['8.1.6', '8.1.7'], $this->migrateAttempts);
    }

    // L2: V_old='' (fresh install) → no migrator runs, markUpdatedOrNew still called
    public function testL2FreshInstallSkipsMigratorsButStillCallsMarkUpdated(): void
    {
        when('get_option')->justReturn('');

        $module = $this->makeModule('8.1.7', [
            $this->makeMigrator('8.1.7'),
        ]);

        $module->callPluginInit();

        self::assertSame([], $this->migrateAttempts);
        self::assertTrue($this->markUpdatedCalled);
        self::assertTrue($this->initDbCalled);
    }

    // L3: V_old == V_new → no migrator runs
    public function testL3SameVersionRunsNoMigrators(): void
    {
        when('get_option')->justReturn('8.1.7');

        $module = $this->makeModule('8.1.7', [
            $this->makeMigrator('8.1.7'),
        ]);

        $result = $module->runMigrationsPublic();

        self::assertTrue($result);
        self::assertSame([], $this->migrateAttempts);
    }

    // L4: migrators registered out of order → run ascending by version_compare
    public function testL4SortsMigratorsAscending(): void
    {
        when('get_option')->justReturn('8.1.0');

        $module = $this->makeModule('8.2.0', [
            $this->makeMigrator('8.2.0'),
            $this->makeMigrator('8.1.5'),
            $this->makeMigrator('8.1.7'),
        ]);

        $module->runMigrationsPublic();

        self::assertSame(['8.1.5', '8.1.7', '8.2.0'], $this->migrateAttempts);
    }

    // L5: migrator at index 1 of 3 throws → indices 0 and 1 attempted; index 2 not;
    //     cursor sits at migrator-0's target; markUpdatedOrNew not called; error logged.
    public function testL5HaltsLadderOnExceptionAndLeavesCursorAtLastSuccess(): void
    {
        when('get_option')->justReturn('8.1.0');

        $module = $this->makeModule('8.2.0', [
            $this->makeMigrator('8.1.5'),
            $this->makeMigrator('8.1.7', new RuntimeException('boom')),
            $this->makeMigrator('8.2.0'),
        ]);

        $module->callPluginInit();

        self::assertSame(['8.1.5', '8.1.7'], $this->migrateAttempts);
        self::assertCount(1, $this->updateOptionCalls);
        self::assertSame(
            [SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '8.1.5'],
            $this->updateOptionCalls[0]
        );
        self::assertFalse($this->markUpdatedCalled);
        self::assertTrue($this->initDbCalled);
        self::assertCount(1, $this->errorLogs);
        self::assertStringContainsString('8.1.7', $this->errorLogs[0]);
    }

    // L6: V_old < V_new but no applicable migrators (gap) → no migrator runs;
    //     markUpdatedOrNew called.
    public function testL6GapBetweenReleasesStillCallsMarkUpdated(): void
    {
        when('get_option')->justReturn('8.1.0');

        $module = $this->makeModule('8.1.6', [
            $this->makeMigrator('8.2.0'),
        ]);

        $module->callPluginInit();

        self::assertSame([], $this->migrateAttempts);
        self::assertTrue($this->markUpdatedCalled);
    }

    // L7: cursor write fires immediately after each successful migrate()
    public function testL7CursorWriteFiresAfterEachSuccessfulMigrate(): void
    {
        when('get_option')->justReturn('8.1.0');

        $module = $this->makeModule('8.2.0', [
            $this->makeMigrator('8.1.5'),
            $this->makeMigrator('8.1.7'),
        ]);

        $module->runMigrationsPublic();

        self::assertCount(2, $this->updateOptionCalls);
        self::assertSame(
            [SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '8.1.5'],
            $this->updateOptionCalls[0]
        );
        self::assertSame(
            [SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '8.1.7'],
            $this->updateOptionCalls[1]
        );
    }
}
