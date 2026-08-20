# IMP-071 — Export access contracts

IMP-071 adds the application and persistence contracts required to expose Event Export resources safely. Authorized callers can list bounded cursor pages and read Event-scoped Export records without exposing cross-Event data.

Collection access is explicit about PII. A `contains_pii=false` query requires `view_reports`; PII-only or mixed collections require `export_pii`. Detail reads derive the required capability from the authoritative stored Export classification.

Status filters are restricted to the persisted Export lifecycle catalogue. The existing protected-artifact download flow continues to reauthorize every retrieval and remains intentionally outside HTTP delivery until IMP-072.
