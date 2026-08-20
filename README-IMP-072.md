# IMP-072 — Protected Export REST delivery

IMP-072 exposes authenticated Event Export creation, bounded list/detail reads, and protected artifact downloads. Creation requires an `Idempotency-Key`; list access preserves IMP-071's explicit PII filtering and capability rules.

Download authorization is evaluated from current Event membership and authoritative stored classification. The protected locator never enters JSON. Before delivery, storage resolves the allowlisted locator beneath the configured private root, rejects links, and verifies the exact byte count and SHA-256 digest. Only after that verification does the service reauthorize, record the download, and write required audit evidence.

WordPress REST delivery intercepts the typed binary response before JSON serialization and serves the verified bytes with attachment, digest, no-store, and `nosniff` headers.
