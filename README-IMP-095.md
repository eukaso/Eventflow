# EventFlow IMP-095 — Backup-gated migration and rollback rehearsal

IMP-095 supplies the controlled fresh-install database path required before EventFlow can become ready on WordPress. It does not run migrations during plugin activation or ordinary bootstrap.

A migration invocation is accepted only when a recent external evidence file binds the exact EventFlow artifact to readable database and site-files backups by byte count and SHA-256, records a named restore procedure, and confirms a successful isolated restore rehearsal matching those archives. The same evidence is verified immediately before database execution.

The deployment tool is explicitly limited to a fresh EventFlow installation. It fails if any `eventflow_*` table already exists, acquires the database migration lock, applies the ordered 15-entry forward-only catalogue, and verifies schema version 15, every immutable migration checksum, every registered table, InnoDB, and utf8mb4 before reporting success.

The legacy `lui60_event_guests` table is outside EventFlow's namespace. IMP-095 neither reads nor deletes it. For the Lui60 cutover it remains a rollback resource until the authoritative guest list is re-imported into EventFlow and reconciled in IMP-096.

Repository CI proves the controls and failure behavior but does not claim that a DreamHost backup, restore rehearsal, or live schema execution occurred. The live gate remains **BLOCKED** until sanitized evidence from the authorized target is retained outside Git.
