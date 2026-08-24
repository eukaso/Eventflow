# EventFlow IMP-098 — Organizer invitation workspace

IMP-098 replaces the technical communication-first entry point with a single organizer workflow for composing, testing, reviewing, and sending invitations.

The primary screen now provides channel selection, a friendly subject and message editor, WordPress Media Library invitation-card selection, a personal test address, searchable recipient checkboxes, channel eligibility, and North America/international SMS filtering. The organizer must review an authoritative server-calculated audience before the final send button is enabled, and sending still requires an explicit confirmation. Templates, Campaigns, Messages, scheduling, and retries remain available under Advanced communication records.

Personal tests use a dedicated authenticated and idempotent Message route. A test Message has no Campaign, Invitation, or Attendee association, passes the normal Event capability and provider dispatch gates, is queued through the durable Message worker, and records a minimized audit event. It never expands to the guest list.

Bulk sending continues to use published Communication Templates, snapshot Campaigns, server-side recipient resolution, unique secure RSVP credentials, durable delivery jobs, provider gates, and Message status records. The simplified interface therefore changes usability without weakening EventFlow's delivery, privacy, or replay controls.

No automated test or build operation sends email or SMS. Provider delivery occurs only after an organizer deliberately activates the test or final send control in WordPress.
