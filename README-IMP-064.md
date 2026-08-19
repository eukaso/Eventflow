# EventFlow IMP-064 — Communication Template REST Completion

IMP-064 exposes the accepted IMP-063 Template-access contracts while preserving the existing create and publish commands.

## Routes

- `GET/POST /wp-json/eventflow/v1/events/{event_id}/communication-templates`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/communication-templates/{template_id}`
- `POST .../{template_id}/publish|new-version|archive|preview`

The new routes are registered only in full/ready mode. List reads use positive stable cursors and limits from 1 through 100. Identifiers are strictly parsed from route parameters. PATCH accepts only controlled content fields, rejects empty/unknown bodies, merges omitted fields with the authorized current resource, and delegates a complete revision-bound replacement.

PATCH, new-version, and archive require both `If-Match` and `Idempotency-Key`; new-version and archive require empty bodies. Preview is non-mutating, requires exactly a `values` object, and accepts only string keys and values before the application service enforces declared merge fields.

Detail and concrete mutation responses carry strong revision ETags. Preview carries a strong content ETag; collection responses intentionally omit a single revision. Responses expose controlled content, status, revision, and UTC lifecycle timestamps with normalized request IDs and `Cache-Control: no-store, max-age=0`.

No additional migration is required beyond schema 12.
