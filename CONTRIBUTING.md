# Contributing to PrymeStudy Connect

PrymeStudy Connect is the public integration layer for institutional identity, SIS/LMS connectivity, APIs, webhooks and academic-system interoperability.

Contributions are welcome when they preserve the security, compatibility and institution-agnostic design of the protocol.

## Core rules

1. The protocol is the source of truth. SDKs must not invent language-specific behavior.
2. The core must remain institution-agnostic. Do not hard-code a university, department, association, SIS vendor or campus workflow into shared protocol code.
3. Secrets stay server-side. Never add examples that place Connect credentials or private keys in browser/mobile code.
4. Use synthetic data only. Never submit real student, lecturer or institutional records.
5. Preserve backward compatibility within a published major protocol version.
6. Prefer established standards and reviewed cryptographic libraries over custom cryptography.
7. Every externally visible field must have a defined purpose, validation rule and authority model.

## Repository areas

- `docs/` — public protocol and implementation guidance.
- `schemas/` — machine-readable public data contracts.
- `sdk/php/` — PHP/Laravel integration client.
- `sdk/typescript/` — Node.js/TypeScript integration client.
- `sdk/python/` — Python integration client.
- `examples/` — synthetic reference integrations.

## Changes to the protocol

A protocol change should explain:

- why the change is required;
- whether it is backward compatible;
- how it affects authentication, authorization, replay protection or data authority;
- schema changes;
- error behavior;
- migration requirements;
- SDK changes required across all supported languages.

Breaking changes require an explicit protocol-version boundary.

## Security-sensitive changes

Changes involving authentication, signing, token handling, callback validation, account linking, webhook verification, key management or replay protection require focused tests and review.

Do not weaken validation simply to make an example easier to run.

## SDK expectations

Official SDKs should provide equivalent capabilities and error semantics.

At minimum, SDK implementations should include tests for:

- configuration validation;
- authentication assertion/signature construction;
- timestamp and expiry handling;
- nonce/JTI uniqueness behavior;
- idempotency headers;
- request serialization;
- response/error normalization;
- webhook signature verification where supported.

## Style

Keep public interfaces small and explicit. Prefer typed request/response objects over loosely structured maps where the language supports them.

Examples should demonstrate secure defaults rather than minimum-effort shortcuts.

## Pull requests

A useful pull request includes:

- a focused change;
- tests where executable behavior changes;
- documentation for public behavior;
- no unrelated formatting churn;
- no credentials or real personal data.

By contributing, you agree that your contribution is licensed under the repository's Apache License 2.0.
