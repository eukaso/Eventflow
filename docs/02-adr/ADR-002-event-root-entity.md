# ADR-002 — Event is the Root Business Entity

**Status:** Accepted  
**Date:** 2026-08-06  
**Category:** Architecture

## Context

EventFlow is intended to evolve from a single-event implementation to a reusable and eventually multi-event platform.

## Decision

Every operational business record shall belong, directly or through a documented ownership chain, to an Event.

## Consequences

- Future schema design must include event ownership.
- Cross-event data access must be explicit.
- Multi-event support can be added without redefining core ownership.
