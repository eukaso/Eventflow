# IMP-073 — Privacy administration contracts

IMP-073 adds Event-scoped application and persistence access for Privacy Actions and retention holds while retaining the existing restart-safe execution, legal-hold, and restore-reconciliation core.

All reads require `manage_privacy`, which is reserved to the Event primary owner by the current capability policy. Collections use bounded forward cursors and strict stored lifecycle filters; detail queries remain scoped by both Event and resource identifier.

The existing explicit privacy request, hold placement, and hold release operations now implement a narrow command port for delivery composition. This increment adds no HTTP routes; strict privacy administration delivery remains deferred to IMP-074.
