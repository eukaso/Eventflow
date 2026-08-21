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
