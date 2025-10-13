# Integration Tests
PHPUnit tests that runs against a working WP site. Useful to test modules (ex. classes) together without having the use test doubles but the real infrastructure.

### How to run tests
- Copy and rename `.env.integration.example` to  `.env.integration` from the root of the plugin, set configurations if needed.
- Run configuration: `ddev php tests/integration/PHPUnit/setup.php`
- Run tests: `ddev exec phpunit -c tests/Integration/phpunit.xml.dist --testdox`
- Run a single test: `ddev exec phpunit --filter testSomeTestName -c tests/Integration/phpunit.xml.dist --testdox`

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
