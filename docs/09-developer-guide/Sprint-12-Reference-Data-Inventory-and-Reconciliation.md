# Sprint 12 reference-data inventory and reconciliation

## Safety boundary

IMP-096 moves the authorized Lui @ 60 reference list only through staging. The source remains `{$wpdb->prefix}lui60_event_guests`; its table and the previous plugin remain available throughout the rollback window. No legacy table is deleted, renamed, or modified by these tools.

The source contains PII. Its CSV, backup evidence, full command output, host paths, and row-level mismatch investigation must remain in protected operator storage outside the web root and outside Git. Repository evidence contains only counts, hashes, safe codes, and pass/blocked states.

The legacy model has no decline action. `submitted_at IS NOT NULL` maps to `accepted`; otherwise the Invitation remains `pending`. `seats_reserved` maps to Invitation capacity, and `companion_names` maps to confirmed companion Attendees only for accepted Invitations.

## Required order

1. Keep the verified backup/restore evidence within its 24-hour validity window.
2. Complete synthetic Event, Invitation, RSVP, Import, and rollback smoke workflows on staging.
3. Create a dedicated staging Event and confirm the executing WordPress user is its owner with Import and Attendee capabilities.
4. Export the legacy source to `EVENTFLOW_PROTECTED_EXPORT_DIR` with `export-lui60-reference-data.php`.
5. Record the sanitized source SHA-256, source fingerprint, and aggregate totals outside Git. The Invitation count must be exactly 137.
6. Apply the protected CSV with `apply-lui60-reference-data.php`. The command uses EventFlow Import and RSVP application services; it does not issue direct data-changing SQL.
7. Run `reconcile-lui60-reference-data.php` for the returned Event and Import Job identifiers.
8. Require exact Invitation, capacity, accepted, pending, declined, companion, import-row, and row-level reconciliation with zero failures, mismatches, and orphans.
9. Retain the legacy tables and rollback files until user acceptance closes the rollback window.

## Commands

The tools are external to the production plugin archive. Run them with `wp eval-file` from an operator-controlled directory. On WP-CLI builds that consume dash-prefixed positional arguments, use a fixed-argument wrapper and `--use-include` as documented for the migration gate.

```shell
wp --path=/srv/www/wordpress eval-file /secure/tools/export-lui60-reference-data.php -- \
  --expected-version=1.3.0-dev \
  --artifact-sha256=SHA256 \
  --backup-evidence=/secure/evidence.json \
  --output=/protected/eventflow/lui60-reference.csv \
  --expected-invitations=137 \
  --confirm-protected-export

wp --path=/srv/www/wordpress --user=ADMIN eval-file /secure/tools/apply-lui60-reference-data.php -- \
  --expected-version=1.3.0-dev \
  --artifact-sha256=SHA256 \
  --backup-evidence=/secure/evidence.json \
  --source=/protected/eventflow/lui60-reference.csv \
  --source-sha256=SOURCE_SHA256 \
  --event-id=EVENT_ID \
  --expected-invitations=137 \
  --confirm-reference-apply

wp --path=/srv/www/wordpress eval-file /secure/tools/reconcile-lui60-reference-data.php -- \
  --expected-version=1.3.0-dev \
  --event-id=EVENT_ID \
  --import-job-id=IMPORT_JOB_ID \
  --expected-invitations=137
```

The reconciliation command fails closed and emits no names, email addresses, phone numbers, companion names, tokens, source paths, or raw rows.
