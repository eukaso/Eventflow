# Tools

Development, migration, validation, and release utilities belong here.

## Deployment preflight

After installing a candidate in staging, validate the public health/readiness contract and exact deployed version:

```shell
php tools/deployment-preflight.php --url=https://staging.example.test --expected-version=1.3.0-dev
```

Use `--json` for machine-readable evidence. Plain HTTP is rejected except for an explicit loopback-only development invocation with `--allow-http-localhost`. The tool sends no credentials, follows no redirects, retains no response data, and keeps TLS certificate verification enabled.

## Reproducible plugin artifact

Build the production-only WordPress plugin archive from a clean Git commit and verify that two independent builds are byte-identical:

```shell
php tools/build-plugin-artifact.php --output=build/artifacts --verify-reproducible
php tools/verify-plugin-artifact.php --directory=build/artifacts
```

The archive contains only the explicitly allowlisted runtime surface, a dependency-free production autoloader, and an internal payload manifest. The adjacent external manifest records the source commit, deterministic timestamp, archive size, file count, and SHA-256 digest. The build fails closed if Composer gains a production dependency, the source tree is dirty, an input is missing, a symlink is encountered, or reproducibility fails.

## Staging environment acceptance

After installing and activating the approved artifact, run the repository-side probe inside the target WordPress process:

```shell
wp --path=/srv/www/wordpress eval-file /secure/release-tools/staging-environment-acceptance.php -- --expected-version=1.3.0-dev --json
```

The command emits only bounded check identifiers/codes—never paths, URLs, credentials, or database connection details—and exits nonzero unless every environment and WordPress composition prerequisite passes. The acceptance tool remains external to the production plugin archive by design.

## Backup-gated fresh schema deployment

Verify a deployment-managed evidence file against the exact artifact before touching the database:

```shell
php tools/verify-deployment-backup.php --evidence=/secure/eventflow/backup-evidence.json --artifact-sha256=SHA256
```

After a full database/files restore rehearsal passes, run the forward-only catalogue inside WordPress:

```shell
wp --path=/srv/www/wordpress eval-file /secure/release-tools/run-deployment-migrations.php -- --expected-version=1.3.0-dev --artifact-sha256=SHA256 --backup-evidence=/secure/eventflow/backup-evidence.json --confirm-fresh-install
```

The command is intentionally fresh-install-only: it rejects any existing EventFlow table, verifies the backup and restore evidence again, serializes execution with the database migration lock, applies the 15-entry catalogue, and then verifies every ledger checksum plus every InnoDB/utf8mb4 table. It never deletes legacy plugin tables or performs automatic reverse migrations.

## Lui @ 60 reference-data gate

IMP-096 uses four external WP-CLI tools in strict order:

1. `prepare-lui60-staging-event.php` proves synthetic Event, Invitation, RSVP, Import, and lifecycle rollback behavior, then creates the dedicated staging reference Event.
2. `export-lui60-reference-data.php` writes the allowlisted legacy source to protected storage after re-verifying backup evidence and the artifact hash.
3. `apply-lui60-reference-data.php` stages, validates, and applies Invitations through EventFlow Import services, then maps legacy confirmations and companions through the audited RSVP service.
4. `reconcile-lui60-reference-data.php` compares the source, completed Import Job, target Invitations, response states, and Attendees without emitting PII.

See [the reference-data runbook](../docs/09-developer-guide/Sprint-12-Reference-Data-Inventory-and-Reconciliation.md) for the command contract and rollback boundary. The gate requires exactly 137 Invitations, zero failed/mismatched/orphaned rows, exact capacity/response/companion totals, and the preserved legacy table.

## Staging operations certification

After installing the exact candidate artifact and verifying its fresh backup evidence, execute the IMP-097 certification against the dedicated staging Event:

```shell
wp --path=/srv/www/wordpress eval-file /secure/release-tools/certify-staging-operations.php -- --expected-version=1.3.0-dev --artifact-sha256=SHA256 --backup-evidence=/secure/evidence.json --event-id=ID --confirm-operations-certification
```

The command creates only bounded, PII-free probe jobs. It proves the one-minute cron registration, worker heartbeat, retry/backoff, expired-lease recovery, protected-storage round trip, anonymous Export denial, Event audit-chain integrity, Privacy reconciliation readiness, and sanitized diagnostic output. Evidence contains only safe check codes and bounded counts; retain it outside Git.
