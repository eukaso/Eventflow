# EventFlow IMP-026 — Error Handling & Observability Integration

IMP-026 completes Sprint 8 SVC-015 and S6-R69 through S6-R73 while preserving the narrow-port and non-authoritative read-model rules in S6-R74 and S6-R75.

## Authoritative errors and typed responses

- The existing Sprint 4 error catalogue remains the single public authority for codes, HTTP status, retryability, messages, and allowed detail kinds.
- Controlled internal failures continue to normalize through `ErrorCodeMapper`; unknown failures collapse to `internal_error` without exposing exception messages, traces, SQL, or paths.
- Validation, version-conflict, precondition, and retry-after details remain typed and are emitted only when their type matches the catalogue definition.
- The API translator now records failures through the observability service while preserving the same safe public response and request ID.

## Structured logging and centralized redaction

- Operational records have a fixed envelope: UTC timestamp, level, bounded event name, validated request ID, and structured context.
- `ObservabilityRedactor` recursively removes credential, session, token, PII, message-content, raw-data, and diagnostic-message fields. It also detects email/Bearer values, neutralizes CR/LF log injection, bounds depth/cardinality/string length, and handles invalid UTF-8 and unsupported values.
- Raw exception messages and stack traces are never passed to the operational sink. Failure records contain the safe public code, HTTP status family, and implementation class only.
- The WordPress sink emits one JSON object per record. Sink failure cannot replace or change the underlying application response.

## Low-cardinality metrics

- Every metric must be registered through a `MetricDefinition` with an exact label set and an explicit finite allowlist for every label value.
- Core counters cover public-code failures and request outcome by bounded transport. Arbitrary Event IDs, user IDs, request IDs, exception text, URLs, provider identifiers, or caller-defined label values are rejected.
- WordPress integrations receive validated metrics through the `eventflow_metric_increment` action. Metric transport failure cannot alter application behavior.

## Sanitized privileged diagnostics

- `DiagnosticService` requires current Event `view_audit` authority and returns a generated bundle for a validated request ID.
- The default sources expose only bounded runtime versions/environment flags, schema compatibility, and readiness identifiers/status codes.
- Every source is redacted again at the service boundary. A failing source contributes only `diagnostic_source_failed`; its exception, path, SQL, and raw logs are discarded.
- No raw-log, arbitrary-file, configuration-secret, database-dump, or stack-trace endpoint is introduced. Diagnostic sources are narrow read-only ports and cannot authorize mutations.

## Verification

Coverage exercises secret/PII removal, email and Bearer detection, log-injection neutralization, opaque unknown failures, metric label rejection, privileged diagnostics, source-failure containment, container composition, and the existing health/readiness distinction. No database migration is required. The standard `composer test` gate remains authoritative.
