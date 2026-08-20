# EventFlow IMP-083 — Event Setup and Venue Configuration UI

IMP-083 adds revision-safe organizer setup workflows to the Sprint 11 admin experience.

## Delivered

- Draft Event details can be edited through the accepted Event PATCH route, including name, slug, timezone, schedule, and Venue assignment.
- Guest messaging, RSVP window, guest-edit policy, seating mode, and assisted-seating settings can be updated through the Event-configuration resource.
- Organizers with server-authorized Venue access can list and create Venues, then assign a selected Venue to a draft Event.
- Event and configuration reads retain their response ETags; updates send both `If-Match` and a CSPRNG-backed `Idempotency-Key`.
- Venue creation sends an idempotency key and validates name, country code, and positive capacity through browser constraints and the authoritative API.
- Activated Events expose their setup data read-only while configuration authority remains determined by the API.
- Venue-access failure is isolated from Event-configuration loading rather than disabling the entire setup workspace.
- Successful mutations re-read authoritative Event and configuration state before enabling another edit.

## Security and concurrency

The browser never receives database access, capability grants, or embedded Event data. Client-side field disabling is a usability behavior only. EventFlow application services continue to enforce current membership, operation capabilities, lifecycle rules, strict request fields, optimistic concurrency, idempotency, transactions, and audit requirements.
