# ADR-003 — Invitations Group Attendees

**Status:** Accepted  
**Date:** 2026-08-06  
**Category:** Data Model

## Context

A single invitation may reserve multiple seats for a primary guest and companions. Seating and reception later operate on individual people rather than only invitation groups.

## Decision

An Invitation represents the invitation/group relationship. Known people within the invitation are represented as Attendees, including the primary guest and submitted companions.

## Consequences

- Invitation-level communication remains possible.
- Seating and check-in operate at attendee level.
- Companions shall not remain only serialized text once submitted.
