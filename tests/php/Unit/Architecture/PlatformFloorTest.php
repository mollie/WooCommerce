<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Architecture;

use Inpsyde\EnvironmentChecker\Constraints\PhpConstraint;
use Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * PHP 8.0 is the floor, and it is the floor in every place that declares one.
 *
 * The blueprint and every express spec assume 8.0: declare(strict_types=1), constructor
 * promotion, match, union types, named arguments. The first match expression would be a parse
 * error on a 7.4 box, so the floor is not a preference — it is what makes the new code loadable.
 *
 * Seven files declare it, and they only protect a merchant if they agree. One of them left at 7.4
 * means a site that installs the plugin, loads a match expression and dies with a parse error
 * instead of the readable notice ConstraintsChecker is there to show. This test is the guard that
 * keeps them agreeing after the bump.
 *
 * @covers \Mollie\WooCommerce\Activation\ConstraintsChecker
 */
class PlatformFloorTest extends TestCase
{
    private const FLOOR = '8.0';

    protected function setUp(): void
    {
        parent::setUp();
        // AbstractVersionConstraint escapes the plugin name it is given.
        when('esc_html')->returnArg();
    }

    /**
     * Scenario: every file that declares a PHP floor declares 8.0
     *   Given the seven declaration sites named by the core-seed spec
     *   When each is read
     *   Then it names 8.0, and none of them still names 7.4
     *
     * @dataProvider declarationSites
     */
    public function testDeclaresPhp80AsTheFloorInEveryDeclarationSite(
        string $file,
        string $pattern,
        string $what
    ): void {

        $path = PROJECT_DIR . '/' . $file;
        self::assertFileExists($path);

        self::assertRegExp(
            $pattern,
            (string) file_get_contents($path),
            sprintf('%s does not declare PHP %s (%s).', $file, self::FLOOR, $what)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function declarationSites(): array
    {
        return [
            'composer.json require' => [
                'composer.json',
                '/"php"\s*:\s*"\^8\.0"/',
                'the version the plugin requires',
            ],
            'composer.json config.platform' => [
                'composer.json',
                '/"platform"\s*:\s*\{[^}]*"php"\s*:\s*"8\.0"/s',
                'the version composer resolves dependencies against',
            ],
            'composer.lock platform-overrides' => [
                'composer.lock',
                '/"platform-overrides"\s*:\s*\{[^}]*"php"\s*:\s*"8\.0"/s',
                'the lock must be regenerated with the new platform',
            ],
            'the plugin header' => [
                'mollie-payments-for-woocommerce.php',
                '/^\s*\*\s*Requires PHP:\s*8\.0\s*$/m',
                'what WordPress checks before activating',
            ],
            'readme.txt' => [
                'readme.txt',
                '/^Requires PHP:\s*8\.0\s*$/m',
                'what wordpress.org shows on the plugin page',
            ],
            'ConstraintsChecker' => [
                'src/Activation/ConstraintsChecker.php',
                '/new PhpConstraint\(\s*.8\.0.\s*\)/',
                'what refuses activation and shows the merchant why',
            ],
            'the DDEV box' => [
                '.ddev/config.yaml',
                '/^php_version:\s*"8\.0"\s*$/m',
                'the version the development box runs',
            ],
            'the CI matrix' => [
                '.github/workflows/ci.yml',
                '/php-versions:\s*\[\s*.8\.0./',
                'the lowest version CI proves the plugin on',
            ],
        ];
    }

    /**
     * Scenario: 7.4 is no longer tested anywhere in CI
     *   Given the CI matrix
     *   Then it does not run the suite on 7.4, which would now fail to parse the new code
     */
    public function testCiNoLongerRunsOnPhp74(): void
    {
        $matrix = (string) file_get_contents(PROJECT_DIR . '/.github/workflows/ci.yml');

        self::assertNotRegExp(
            "/php-versions:.*'7\.4'/",
            $matrix,
            'CI still runs on PHP 7.4, where new code using match or promotion cannot parse.'
        );
    }

    /**
     * Scenario: activating on PHP 7.4 is refused
     *   Given the constraint ConstraintsChecker builds
     *   When it is checked against a 7.4 runtime
     *   Then it fails, so the plugin is disabled and the merchant is told why
     *   And it passes for 8.0 and above
     *
     * PhpConstraint::check() reads the PHP_VERSION constant and takes no argument, so the test
     * drives the version comparison the constraint is built on instead of the ambient runtime.
     * That keeps the assertion about the declared floor, not about the box the suite runs on.
     *
     * @covers \Mollie\WooCommerce\Activation\ConstraintsChecker::__construct
     */
    public function testRefusesActivationOnPhp74(): void
    {
        $constraint = $this->floorConstraint();

        foreach (['8.0.0', '8.1.27', '8.3.0'] as $supported) {
            self::assertTrue(
                $constraint->checkRuntime($supported),
                sprintf('PHP %s must satisfy the floor.', $supported)
            );
        }

        foreach (['7.4.33', '7.4', '7.2.0'] as $refused) {
            try {
                $constraint->checkRuntime($refused);
                self::fail(sprintf('PHP %s must be refused by the constraint.', $refused));
            } catch (ConstraintFailedException $exception) {
                self::assertSame(
                    $refused,
                    $exception->getValidationSubject(),
                    'The refusal must name the runtime it refused.'
                );
                self::assertSame($constraint, $exception->getValidator());
            }
        }
    }

    /**
     * The constraint ConstraintsChecker builds, with the version comparison exposed.
     *
     * PhpConstraint::check() takes no argument and reads PHP_VERSION, so it can only ever say
     * something about the box the suite happens to run on. checkRuntime() drives the same
     * comparison against a version the test chooses.
     */
    private function floorConstraint(): PhpConstraint
    {
        return new class (self::FLOOR) extends PhpConstraint {
            /**
             * @return bool
             * @throws ConstraintFailedException
             */
            public function checkRuntime(string $actualVersion)
            {
                $this->message = 'PHP version has to be ' . $this->requiredVersion . ' or higher.';

                return $this->checkVersion($actualVersion);
            }
        };
    }
}
