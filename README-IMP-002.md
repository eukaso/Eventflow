# EventFlow IMP-002 — Configuration & Bootstrap

This package contains the starter implementation for IMP-002.

## Included
- Thin WordPress plugin entry point
- Runtime requirements and validation
- Immutable configuration model and loader
- Bootstrap state/result model
- Schema compatibility model
- Foundation container
- Idempotent application bootstrap
- Minimal/full bootstrap mode placeholders
- Starter PHPUnit unit tests

## Important
`ApplicationBootstrap::readInstalledSchemaVersionPlaceholder()` is intentionally temporary.
IMP-003 will replace it with the authoritative schema metadata/migration repository.

No database migration is executed during normal bootstrap.
