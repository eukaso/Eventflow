# ADR-001 — Product Identity is EventFlow

**Status:** Accepted  
**Date:** 2026-08-06  
**Category:** Product

## Context

The initial implementation began as a guest-management solution for the Lui @ 60 celebration. The feature set and intended reuse expanded beyond a single event.

## Decision

The product will be developed under the generic name **EventFlow**. Lui @ 60 is the first implementation/event, not the product identity.

## Consequences

- Event-specific values move to configuration.
- Source code and database naming should progressively become event-neutral.
- Future workflows should support multiple event types without event-specific code forks.
