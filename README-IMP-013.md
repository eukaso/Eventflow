# EventFlow IMP-013 — Automated Test Harness

IMP-013 turns the existing unit tests into one reproducible local and continuous-integration gate.

## Test gate

`composer test` performs two ordered checks:

1. `tests/bin/lint.php` syntax-checks the plugin entry point and every PHP file under `src/` and `tests/`.
2. PHPUnit runs the `unit` suite through `phpunit.xml.dist` and `tests/bootstrap.php`.

The PHPUnit configuration fails on warnings, notices, deprecations, risky tests, and unexpected output. Its environment is deterministic: UTC timezone, `E_ALL`, no production configuration, and no network or live WordPress dependency.

## Continuous integration

`.github/workflows/foundation-tests.yml` validates Composer metadata, installs the committed lock file, and executes the same `composer test` command on PHP 8.2 and PHP 8.3. The workflow receives read-only repository permission.

## Runtime reconciliation

The codebase uses PHP 8.2 readonly-class syntax and PHPUnit 11 also requires PHP 8.2. The declared Composer and bootstrap runtime floor is therefore reconciled from PHP 8.1 to PHP 8.2; this prevents installations from passing preflight on a runtime that cannot parse the implementation.

No database schema or approved API contract changes are introduced.
