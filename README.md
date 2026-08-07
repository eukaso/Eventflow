# EventFlow

**EventFlow** is a professional event operations platform for managing the full guest lifecycle — from invitation through communications, attendee management, seating, reception check-in, and reporting.

> **Product motto:** Every Guest Matters. Every Event Flows.

## Current Status

EventFlow is in active product-foundation development. The first live implementation is the **Lui @ 60** event, which serves as the proving ground for the platform architecture and user experience.

## Product Direction

EventFlow is designed as a reusable event operations platform, not as a single-purpose RSVP or birthday plugin. Event-specific information belongs in configuration so the platform can support birthdays, weddings, conferences, church events, fundraisers, reunions, and corporate functions without code changes.

## Repository Structure

- `docs/` — authoritative product and engineering documentation
- `src/` — application/plugin source code
- `tests/` — automated and manual test assets
- `database/` — schema specifications and migrations
- `assets/` — shared product assets
- `tools/` — development and operational utilities
- `.github/` — GitHub templates and repository automation

## Documentation Hierarchy

1. Constitution
2. Vision & Design Principles
3. ADR Register
4. Product Requirements Document
5. Software Architecture
6. Database Design
7. API Specification
8. UI/UX Guide
9. Security Guide
10. Developer Guide
11. Testing Strategy
12. Release Documentation
13. Lessons Learned

See [`docs/README.md`](docs/README.md) for the documentation governance model.

## Versioning

EventFlow uses Semantic Versioning (`MAJOR.MINOR.PATCH`). Documentation is also versioned and released alongside software milestones.

## Current Foundation

The existing proof-of-concept has demonstrated:

- guest/import database
- unique invitation tokens
- personalized invitation URLs
- reserved-seat tracking
- companion-name collection
- dashboard statistics

These capabilities will be migrated into the formal EventFlow architecture as the product evolves.
