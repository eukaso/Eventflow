# Sprint 12 operations certification

IMP-097 closes the operational gap between repository-only job contracts and a production-like WordPress runtime. EventFlow registers a bounded one-minute worker tick and composes strict versioned handlers for Import apply, Export generation, Privacy execution, and the PII-free certification probe.

## Runtime contract

- WordPress cron uses `eventflow_worker_tick` on the `eventflow_every_minute` schedule.
- Each tick processes at most ten durable jobs.
- Job claims use database leases and schema-version gating.
- Heartbeats accept a same-second no-op only when the current unexpired lease token is still authoritative.
- Import handlers continue bounded batches until completion and heartbeat between batches.
- Export and Privacy handlers validate payload shape before invoking their authoritative application services.
- Unknown job types and unsupported payload versions fail closed.

## Authorized staging command

Copy the repository tool to protected operator storage outside the plugin archive, then run:

```shell
wp --path=/srv/www/wordpress eval-file /secure/release-tools/certify-staging-operations.php -- \
  --expected-version=1.3.0-dev \
  --artifact-sha256=SHA256 \
  --backup-evidence=/secure/evidence.json \
  --event-id=ID \
  --confirm-operations-certification
```

The command is staging-only, requires an authenticated WordPress user, re-verifies fresh backup evidence and the exact artifact hash, and emits only safe status codes plus three bounded metrics. It waits once for the 30-second retry window, exercises expired-lease recovery, deletes its temporary storage probe, and retains only PII-free durable probe-job records.

## Pass conditions

All ten checks must pass: cron cadence, worker completion, heartbeat, retry/backoff and second-attempt completion, lease recovery, protected storage, anonymous Export denial, audit-chain integrity, Privacy reconciliation readiness, and sanitized diagnostics. Full evidence remains outside Git.
