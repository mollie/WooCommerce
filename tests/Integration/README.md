# Integration Tests
PHPUnit tests that runs against a working WP site. Useful to test modules (ex. classes) together without having the use test doubles but the real infrastructure.

### How to run tests
- Start the site once: `ddev start` (the site needs WooCommerce active; WooCommerce Subscriptions is optional, its cases are skipped without it).
- Run tests: `composer tests:integration` (same as `ddev exec phpunit -c tests/Integration/phpunit.xml.dist`)
- Run a single test: `ddev exec phpunit -c tests/Integration/phpunit.xml.dist --filter testSomeTestName --testdox`
- Another WordPress than the DDEV one: copy `.env.integration.example` to `.env.integration` and set `MOLLIE_INTEGRATION_WP_DIR`.

### What the setup guarantees
- No Mollie API key is needed. Mollie is faked (`Common/FakeMollie`, or the older `API/Mocks/MockedApi`) and the tests bring their own keys.
- Nothing leaves the machine: a request no test answered is refused and listed at the end of the run.
- A broken setup stops the run with a non-zero exit code and says what is missing.

### Guidelines

- Use descriptive class names that reflect what system/component you're testing
- Group related tests using @group annotations
- Keep one test class per main class you're testing
- Use consistent prefixes like it_, should_, or test_ for methods
- Include setup/teardown methods for database state management

#### Method Naming

Pattern: it_[action]_[expected_outcome]_[given_condition]()

#### DocBlock

``` php
/**
* Brief description of what the test verifies.
*
* Longer description if needed, explaining the business scenario
* or complex integration flow being tested.
*
* @test
* @group integration
* @group [feature-name]
* @covers ClassName::methodName
* @dataProvider dataProviderName (if applicable)
 */
  
```
