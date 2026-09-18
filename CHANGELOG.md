# Changelog

All notable changes to PrymeStudy Connect are documented here.

The project follows semantic versioning and the compatibility policy in `docs/VERSIONING.md`.

## [Unreleased]

## [1.0.0] - 2026-09-18

### Added

- Enterprise Connect v1 protocol and OpenAPI contract.
- PHP/Laravel, TypeScript/Node.js and Python SDKs.
- ES256 `private_key_jwt` machine authentication against PrymeStudy Identity.
- Short-lived scoped Connect access tokens.
- Partner SSO with one-time browser launch handoff through `auth.prymestudy.com`.
- SIS/LMS student, course and enrollment synchronization surfaces.
- Academic mapping contract and institution/faculty/department/programme coverage isolation.
- Signed webhook verification helpers.
- Sandbox/production isolation model.
- Continuous protocol and SDK CI.
- Automated dependency updates and security-audit workflow.
- Credential-material guard.
- Reproducible tagged release pipeline with SHA-256 checksums and provenance attestations.
- Production-readiness, partner-certification, operations and versioning policies.
- Registry-publishing workflow for npm and PyPI.
- Root Composer package metadata for Packagist distribution of `prymestudy/connect`.

### Release boundary

Version 1.0.0 stabilizes the public Connect v1 protocol and official SDK contract. Production integration access remains approval-gated and still requires the matching PrymeStudy server deployment, sandbox certification, academic coverage validation and central PrymeStudy approval.
