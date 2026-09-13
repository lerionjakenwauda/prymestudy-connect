# Production readiness

PrymeStudy Connect is released to external institutions only when every gate in this document is satisfied for the release candidate and the matching PrymeStudy server deployment.

## Release gates

### 1. Protocol and supply-chain integrity

- OpenAPI 3.1 validates.
- Every public JSON Schema validates against its metaschema.
- SDK behavior matches the versioned protocol.
- No institution-specific assumption exists in the shared protocol.
- Breaking protocol changes require a new major version.
- Third-party GitHub Actions used by CI, security and release workflows are pinned to reviewed immutable commit SHAs.
- Release artifacts are produced from an immutable version tag, checksummed and supplied with build provenance/attestation.

### 2. SDK quality

The supported SDKs must pass their test/build gates on the supported runtime line:

- PHP 8.2+;
- Node.js 20+;
- Python 3.11+.

The release build must be reproducible from a Git tag and must emit checksummed release artifacts.

Official SDKs must also enforce the shared client-security baseline, including secure remote endpoints, bounded requests, correct token audience, P-256 signing-key validation and no automatic redirect following for authenticated Connect requests.

### 3. Security

A release candidate must pass:

- repository credential guard;
- Composer dependency audit;
- npm dependency audit at high severity or above;
- Python dependency audit;
- CodeQL analysis for supported languages;
- authentication/replay tests;
- webhook signature/tamper tests;
- one-time launch replay tests on the server;
- tenant/scope isolation tests on the server;
- academic coverage isolation tests for college/faculty, department and programme applications;
- synchronization-job isolation between Connect applications;
- return-URL allow-list tests;
- key revocation tests.

A known critical/high vulnerability in a runtime dependency blocks a production release unless a documented, reviewed mitigation demonstrates that the affected code path is not reachable.

### 4. Server deployment

The matching PrymeStudy Web deployment must confirm:

- database migrations applied successfully;
- `auth.prymestudy.com` serves Connect machine authentication and browser launch handoff;
- `prymestudy.com` serves Connect platform operations;
- canonical assertion audience is pinned to the Auth token endpoint;
- production integration activation requires PrymeStudy central platform approval;
- institution administrators cannot self-approve production access;
- short-lived access tokens are stored only as hashes;
- assertion JTI replay protection is active;
- launch credentials are one-time and short-lived;
- rate limits are enabled;
- unit-scoped applications are rejected when academic records resolve outside the approved coverage target;
- synchronization jobs are visible only to the Connect integration that created them;
- audit events are emitted for security-sensitive state changes;
- production credentials are isolated from sandbox credentials.

### 5. Partner certification

Before production access is approved, the partner must pass the certification matrix in [`PARTNER_CERTIFICATION.md`](PARTNER_CERTIFICATION.md) using a sandbox integration.

Certification records the exact system type, scopes and academic coverage. A partner certified for one department is not automatically certified for another department or for institution-wide access.

Production uses a separate integration identity and separate production key material. Sandbox credentials are never promoted into production credentials.

### 6. Operational readiness

PrymeStudy must have:

- a named operational owner for the integration;
- an incident/revocation procedure;
- a partner technical contact;
- a security contact path;
- documented key rotation procedure;
- audit/log access for incident investigation;
- rollback/disable controls that do not require database surgery;
- a tested way to suspend one integration without disabling unrelated departments or institution systems.

## Release decision

A green repository CI run is necessary but is not sufficient by itself. Production approval requires the public release gates, the matching PrymeStudy server deployment gates, and partner certification.

The production approval record is the final authorization boundary. Institutions cannot self-activate a production Connect integration.

Do not tag a general-availability release or invite broad external production onboarding while a required server test has not executed, the candidate is not deployed, or the first controlled partner certification remains incomplete.
