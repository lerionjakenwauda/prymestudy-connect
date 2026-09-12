# Security Policy

PrymeStudy Connect handles institutional identity, academic data exchange and privileged server-to-server integrations. Security reports must be handled privately and must never expose credentials, student data or exploit details in a public issue.

## Reporting a vulnerability

Use GitHub's private security reporting / Security Advisory flow for this repository when available.

If private reporting is unavailable, contact PrymeStudy through an official support channel and request a security escalation. Do not include production secrets, access tokens, private keys, student records or full exploit payloads in an unencrypted public message.

A useful report should include:

- the affected component and version or commit;
- the security impact;
- reproducible steps using non-production data;
- relevant request/response metadata with secrets removed;
- whether the issue affects sandbox, production or both;
- any known mitigations.

## Security boundary

This repository is the public integration layer. It contains SDKs, schemas, protocol documentation, signing/verification code and examples.

It must not contain:

- PrymeStudy production secrets or private signing material;
- live student or lecturer records;
- private anti-abuse or fraud controls;
- internal account-matching heuristics;
- privileged production administration tooling;
- internal session implementation details whose disclosure would materially weaken the platform.

## Supported security model

PrymeStudy Connect is designed around:

- TLS for all production traffic;
- isolated sandbox and production trust domains;
- least-privilege scopes;
- asymmetric client authentication for high-assurance integrations;
- short-lived credentials and grants;
- nonce/JTI replay protection;
- idempotency for mutation endpoints;
- strict callback/return URL allow-lists;
- explicit institution, integration and environment binding;
- key rotation and revocation;
- signed webhook delivery;
- auditable administrative and security events;
- no browser-side partner secrets.

## Secret handling

Never commit:

- client secrets;
- private keys;
- refresh tokens;
- access tokens;
- webhook secrets;
- database credentials;
- production environment files.

Examples must use obviously synthetic values.

If a credential is accidentally committed, treat it as compromised and rotate/revoke it immediately. Removing it from Git history is not a substitute for rotation.

## Dependency and supply-chain security

SDK packages should:

- minimize dependencies;
- pin or constrain dependencies appropriately;
- avoid dynamic code execution from untrusted input;
- use reproducible release processes where practical;
- publish checksums/signatures when release infrastructure supports them;
- run automated tests and dependency/security scans before release.

## Cryptography

Do not invent new cryptographic primitives.

Protocol implementations should use established, reviewed libraries and approved algorithms. High-assurance Connect integrations use asymmetric trust so a partner's private signing key does not need to be stored by PrymeStudy.

Compatibility profiles using shared secrets must enforce strict key rotation, bounded validity, replay protection and server-side secret storage.

## Student and institutional data

Examples, tests and bug reports must use synthetic data.

Implementations should minimize data collection and transmission, validate claims against the approved integration scope and avoid logging sensitive payloads unless there is a documented operational need and appropriate protection.

## Disclosure

PrymeStudy coordinates remediation and disclosure based on severity, exploitability and affected systems. Public disclosure should occur only after an appropriate fix or mitigation is available.
