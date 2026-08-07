# EF-DOC-009 — Developer Guide

## Git Workflow

- `main` — stable/releasable code
- `develop` — integration branch when needed
- `feature/<name>` — scoped feature branches
- `fix/<name>` — bug fixes
- `docs/<name>` — documentation-only work

## Commit Guidance

Prefer clear, scoped commits such as:

- `docs: establish EventFlow constitution`
- `feat: add attendee entity migration`
- `fix: preserve invitation token during re-import`

## Release Rule

A release requires requirements, architecture, QA, documentation, and release gates to pass.
