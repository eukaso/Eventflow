# IMP-070 — Hardened Import REST delivery

IMP-070 delivers the authenticated Import surface: upload/create, list/detail, mapping/validation, parse acknowledgement, dry-run result, apply, cancel, rows, and result.

Uploads enter through WordPress file parameters only. The guard requires a successful PHP upload, verifies reported and actual size (maximum 25 MiB), permits CSV/XLSX extensions with detected MIME allowlists, moves content to a random server-generated temporary path, and deletes that path after synchronous parsing. JSON or query-supplied server paths are never accepted.

Apply and cancel require `If-Match` and `Idempotency-Key`. Mapping/validation and upload require idempotency keys. Responses disable caching; mutable resources expose revision ETags. Because upload staging parses tabular syntax synchronously, `/parse` is an idempotent parsed-resource acknowledgement and `/dry-run` returns the current normalized result counters.
