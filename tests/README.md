# Tests

Run the complete local gate from the repository root:

```text
composer test
```

The gate first syntax-checks `eventflow.php`, `src/`, and `tests/`, then runs the `unit` suite configured by `phpunit.xml.dist`. The bootstrap fixes the test timezone to UTC, enables all PHP error reporting, and exposes only the non-sensitive `EVENTFLOW_TEST_ENV=testing` marker.

Tests must be deterministic, isolated from network services and production WordPress data, and use explicit fakes for WordPress infrastructure. Production guest data, credentials, and environment files must not be committed.
