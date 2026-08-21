# Sprint 12 backup, migration, and rollback

## Non-negotiable order

1. Verify the exact IMP-093 artifact SHA-256.
2. Create full database and site-files backups using deployment-managed hosting facilities.
3. Restore both backups into an isolated location and run basic database/files integrity checks.
4. Create the bounded evidence JSON outside the repository.
5. Verify the evidence locally.
6. Install and activate EventFlow; migration-required mode is expected.
7. Run the explicit fresh-install migration command.
8. Run health/readiness preflight and retain sanitized evidence.
9. Keep the rollback window open until the reference-data import reconciles.

Ordinary activation and web requests must never execute migrations. Do not paste SQL into the WordPress plugin editor.

## Evidence contract

Store the real evidence and archives outside the web root and outside Git. `deployment-evidence/` is ignored as an additional guard. The JSON shape is:

```json
{
  "format_version": 1,
  "evidence_id": "backup-yyyymmdd-sequence",
  "target_environment": "production",
  "artifact_sha256": "64 lowercase hexadecimal characters",
  "created_at": "YYYY-MM-DDTHH:MM:SSZ",
  "restore_procedure_id": "restore-runbook-identifier",
  "database_backup": {"path": "/secure/path/database.sql.gz", "bytes": 1, "sha256": "..."},
  "files_backup": {"path": "/secure/path/site-files.tar.gz", "bytes": 1, "sha256": "..."},
  "restore_rehearsal": {
    "status": "passed",
    "completed_at": "YYYY-MM-DDTHH:MM:SSZ",
    "database_sha256": "...",
    "database_bytes": 1,
    "files_sha256": "...",
    "files_bytes": 1
  }
}
```

The evidence must be no more than 24 hours old when migration begins. Paths are read for verification but are never printed by EventFlow tools.

## Execution

Verify backup evidence, then run the WordPress-side command shown in [the tools guide](../../tools/README.md). The fresh-install confirmation is deliberately explicit. The command blocks partial or previously initialized EventFlow schemas; repair and upgrades require a separately reviewed path.

## Rollback boundary

EventFlow migrations are forward-only and no automated reverse SQL is provided. Before reference data is accepted, rollback means:

1. stop EventFlow traffic and workers;
2. deactivate EventFlow;
3. restore the verified database and site-files backups using the named procedure;
4. reactivate the previous plugin if required;
5. verify the public site and legacy guest workflow;
6. record the rollback decision, operator, timestamps, and verification outcome outside Git.

The old `lui60_event_guests` table must not be deleted merely because its plugin is deactivated. Delete it only as a separately authorized destructive action after IMP-096 import/reconciliation passes and the rollback window closes.
