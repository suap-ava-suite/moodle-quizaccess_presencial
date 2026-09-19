# Repository instructions

## Tests

- Test public Moodle-facing behaviour rather than private methods or incidental SQL structure.
- Use `basic_testcase` when the test does not need Moodle database state.
- Use `advanced_testcase` for tests that exercise Moodle persistence and call `$this->resetAfterTest()` before mutating database state.
- Access Moodle data generators through `self::getDataGenerator()`.
- Override `setUp()` only for shared per-test initialisation. Use `resetAfterTest()` for isolation rather than treating `parent::setUp()` as database cleanup.
- Keep PHPUnit focused on domain rules and integration boundaries. Use Behat for representative browser and navigation journeys.

