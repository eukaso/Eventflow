# Sprint 12 artifact build and verification

## Purpose

The release candidate is built from a clean Git commit into a deterministic WordPress plugin ZIP. The archive is evidence-bearing: its contents, source commit, timestamp, and digest can be checked without trusting the build process.

## Runtime allowlist

The package root is `eventflow/`. Only these source paths are admitted:

- `eventflow.php`
- `composer.json` and `composer.lock`
- `src/`
- `assets/admin/` and `assets/guest/`
- `database/migrations/`
- `database/eventflow-schema-baseline-v1.0.sql`
- generated `vendor/autoload.php`

Tests, documentation, repository metadata, local configuration, logs, temporary files, development dependencies, and arbitrary `vendor/` contents are excluded. Symlinks and special files fail the build. Text runtime formats use LF line endings before hashing and archiving.

## Build gate

From the repository root after committing all intended source changes:

```shell
composer install --no-interaction --prefer-dist
composer test
php tools/build-plugin-artifact.php --output=build/artifacts --verify-reproducible
php tools/verify-plugin-artifact.php --directory=build/artifacts
```

The timestamp defaults to the Git commit timestamp. A controlled build may set `--source-date-epoch=UNIX_TIME`, but values before the ZIP epoch or malformed values fail validation. `--verify-reproducible` performs a second isolated build and requires the same SHA-256 digest.

## Verification evidence

Retain both generated files together:

- `eventflow-<version>.zip`
- `eventflow-<version>.manifest.json`

The verifier checks archive name, byte count, SHA-256, required files, internal/external provenance agreement, each payload byte count and SHA-256, CRC integrity, safe paths, and the exact payload set. Any mismatch is a blocking failure; rebuild from the intended clean commit rather than editing an archive.

## Dependency boundary

The current runtime requires only PHP and uses the `EventFlow\\` PSR-4 namespace. The generated runtime autoloader is valid only for that dependency-free contract. Adding any production Composer package or changing the namespace mapping deliberately blocks packaging until the artifact strategy is reviewed and updated.
