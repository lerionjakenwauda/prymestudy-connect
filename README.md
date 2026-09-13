<p align="center">
  <a href="https://prymestudy.com" aria-label="PrymeStudy">
    <img src="https://prymestudy.com/assets/images/logo.png" alt="PrymeStudy" width="360">
  </a>
</p>

<h1 align="center">PrymeStudy Connect</h1>

<p align="center">
  <strong>Enterprise identity, SIS/LMS, API and event integration for the PrymeStudy ecosystem.</strong>
</p>

<p align="center">
  One secure integration layer for universities, departments, academic organisations, student portals, SIS/LMS platforms and approved third-party services.
</p>

<p align="center">
  <a href="#capabilities">Capabilities</a>
  &nbsp;&middot;&nbsp;
  <a href="#integration-coverage">Coverage</a>
  &nbsp;&middot;&nbsp;
  <a href="#official-sdks">SDKs</a>
  &nbsp;&middot;&nbsp;
  <a href="#security-model">Security</a>
  &nbsp;&middot;&nbsp;
  <a href="#production-readiness">Production</a>
  &nbsp;&middot;&nbsp;
  <a href="SECURITY.md">Report a vulnerability</a>
</p>

---

## What is PrymeStudy Connect?

**PrymeStudy Connect** is PrymeStudy's public institutional and developer integration platform.

It lets an approved external system integrate with PrymeStudy without sharing passwords, browser cookies, private database IDs or internal PrymeStudy security implementation.

```text
External academic systems
        │
        ├── Student / staff portals
        ├── SIS
        ├── LMS
        ├── ERP / academic systems
        ├── Departmental systems
        └── Custom institutional services
                    │
                    ▼
             PrymeStudy Connect
          ┌─────────┼──────────┐
          │         │          │
          ▼         ▼          ▼
       Identity    APIs     Webhooks
          │         │          │
          └─────────┼──────────┘
                    ▼
                PrymeStudy
```

The partner remains authoritative for the identity or academic data it is explicitly approved to provide. PrymeStudy remains authoritative for PrymeStudy accounts, permissions, sessions, entitlements, canonical academic records and internal security policy.

## PrymeStudy authority boundary

Connect uses the existing central PrymeStudy Identity surface rather than creating a second authentication system.

```text
auth.prymestudy.com
  PrymeStudy Identity
  ├── authenticates Connect integration/service identities
  ├── issues short-lived scoped Connect access tokens
  └── owns browser identity handoff/account ownership verification

prymestudy.com/connect/v1
  PrymeStudy platform authority
  ├── creates SSO launches
  ├── applies institution and academic mappings
  ├── synchronizes SIS/LMS data
  └── enforces tenant, scope, entitlement and product policy
```

**Auth proves identity. The PrymeStudy platform decides academic and product authorization.**

Production endpoints:

```text
Token authority
https://auth.prymestudy.com/connect/v1/oauth/token

Platform API base
https://prymestudy.com
```

A successful SSO launch returns a short-lived browser handoff on:

```text
https://auth.prymestudy.com/connect/launch/...
```

---

## Capabilities

### Partner SSO

A user who is already authenticated in an approved partner portal can enter PrymeStudy through a controlled identity handoff.

```text
Authenticated partner user
        ↓
Partner backend authenticates its Connect application
        ↓
PrymeStudy Identity issues short-lived scoped access
        ↓
Partner backend requests a Connect launch
        ↓
Institution + academic mapping validated
        ↓
One-time auth.prymestudy.com launch returned
        ↓
PrymeStudy identity resolved / provisioned / verified
        ↓
Approved PrymeStudy product destination
```

No partner private key, access token or raw student identity payload is placed in the browser redirect URL.

### SIS and LMS integration

Scoped APIs and controlled synchronization workflows support institution-approved data domains such as:

- students and institutional identities;
- academic structures and mappings;
- programmes and levels/cohorts;
- courses and course offerings;
- enrolments and registrations;
- synchronization/reconciliation status;
- other resources exposed by an approved integration scope.

External systems use stable source identifiers and academic codes. They do not depend on PrymeStudy database primary keys.

### APIs

API access is isolated per integration, environment and scope.

Current Connect v1 capability scopes include:

```text
connect:sso.launch
students:write
courses:write
enrollments:write
sync:read
```

Identity federation never automatically grants academic-data write access.

### Webhooks

PrymeStudy Connect supports signed HTTPS event delivery. Consumers verify signature, key identity, timestamp and content integrity before processing and must handle duplicate deliveries idempotently.

### Data exchange and reconciliation

Bulk synchronization is designed for bounded batches, stable external identifiers, idempotency, per-record validation and deterministic reconciliation through PrymeStudy's canonical institutional data layer.

---

## Integration coverage

Every Connect application has two independent authorization dimensions:

```text
Scopes   → what the application may do
Coverage → where in the academic structure it may do it
```

Approved coverage can be:

| Coverage | Typical use |
|---|---|
| **Institution** | Central university portal, SIS, LMS or ERP |
| **College / faculty** | Faculty-owned portal or backend |
| **Department** | Departmental website, portal, association or backend |
| **Programme** | Programme-specific academic service |

A departmental or programme application is not merely labelled with that unit. PrymeStudy resolves partner academic codes to canonical records and rejects activity outside the application's approved coverage.

Several independently operated departmental websites should receive separate Connect applications, credentials, keys and audit histories instead of sharing one institution-wide credential.

See [`docs/INTEGRATION_COVERAGE.md`](docs/INTEGRATION_COVERAGE.md) for the full model.

---

## Protocol and endpoints

The public protocol is the source of truth. Official SDKs implement the same protocol rather than defining language-specific behavior.

```text
POST https://auth.prymestudy.com/connect/v1/oauth/token

POST https://prymestudy.com/connect/v1/launches
POST https://prymestudy.com/connect/v1/students:upsert
POST https://prymestudy.com/connect/v1/courses:upsert
POST https://prymestudy.com/connect/v1/enrollments:upsert
GET  https://prymestudy.com/connect/v1/sync-jobs/{job_id}
```

Core documentation:

- [`docs/GETTING_STARTED.md`](docs/GETTING_STARTED.md) — integration setup from key creation to production.
- [`docs/PROTOCOL.md`](docs/PROTOCOL.md) — Connect v1 wire and trust contract.
- [`docs/AUTHENTICATION.md`](docs/AUTHENTICATION.md) — machine authentication, keys, tokens and rotation.
- [`docs/SSO.md`](docs/SSO.md) — partner SSO and central Identity handoff.
- [`docs/SIS_AND_LMS.md`](docs/SIS_AND_LMS.md) — academic synchronization and source authority.
- [`docs/ACADEMIC_MAPPING.md`](docs/ACADEMIC_MAPPING.md) — external-code to canonical PrymeStudy mapping.
- [`docs/INTEGRATION_COVERAGE.md`](docs/INTEGRATION_COVERAGE.md) — institution, faculty, department and programme authorization boundaries.
- [`docs/ENVIRONMENTS.md`](docs/ENVIRONMENTS.md) — sandbox/production isolation.
- [`docs/ERRORS.md`](docs/ERRORS.md) — stable machine-readable errors.
- [`docs/WEBHOOKS.md`](docs/WEBHOOKS.md) — signed event delivery and replay handling.
- [`docs/PRODUCTION_READINESS.md`](docs/PRODUCTION_READINESS.md) — mandatory controls before production access.
- [`docs/PARTNER_CERTIFICATION.md`](docs/PARTNER_CERTIFICATION.md) — partner sandbox certification and acceptance tests.
- [`docs/OPERATIONS.md`](docs/OPERATIONS.md) — key rotation, incident containment and operating procedures.
- [`docs/VERSIONING.md`](docs/VERSIONING.md) — compatibility, deprecation and release policy.
- [`openapi/connect-v1.yaml`](openapi/connect-v1.yaml) — machine-readable OpenAPI 3.1 contract.

Machine-readable JSON Schemas:

- [`schemas/academic-claims.schema.json`](schemas/academic-claims.schema.json)
- [`schemas/launch-request.schema.json`](schemas/launch-request.schema.json)
- [`schemas/webhook-event.schema.json`](schemas/webhook-event.schema.json)

---

## Official SDKs

PrymeStudy Connect is language-agnostic at the protocol level. Any secure server-side system capable of HTTPS and the required cryptographic operations can integrate directly.

This repository maintains first-class SDK implementations for:

| Runtime | Repository package | Intended use |
|---|---|---|
| **PHP / Laravel** | [`sdk/php`](sdk/php) | Laravel and PHP institutional backends |
| **TypeScript / Node.js** | [`sdk/typescript`](sdk/typescript) | Express, NestJS, Fastify, Next.js server runtimes and Node services |
| **Python** | [`sdk/python`](sdk/python) | Django, Flask, FastAPI and Python services |
| **Raw HTTPS** | [`examples/raw-http`](examples/raw-http) | Java, .NET, Go, Ruby, Rust and other backend stacks |

All official SDKs follow the same core behavior:

1. validate integration configuration;
2. authenticate the integration server-to-server through PrymeStudy Identity;
3. obtain short-lived scoped access;
4. apply request idempotency where required;
5. call the versioned Connect platform API;
6. normalize machine-readable errors;
7. keep credentials and private signing material out of browser/mobile clients.

---

## Authentication model

High-assurance integrations use asymmetric client authentication.

The partner keeps its P-256 private signing key. PrymeStudy stores public verification material and credential metadata.

The production machine-authentication profile uses OAuth client credentials with a short-lived ES256 signed JWT client assertion. Assertions are bound to:

- the registered client ID;
- `https://auth.prymestudy.com/connect/v1/oauth/token` as the exact production audience;
- a short validity window;
- a unique replay-protected `jti`;
- an active registered key and key ID;
- the integration environment.

For high-assurance deployments, mTLS and certificate-bound access can be layered onto integration policy.

---

## Security model

PrymeStudy Connect is designed around **least privilege, tenant isolation, short-lived trust and auditable security state**.

Security requirements include:

- TLS for all production traffic;
- separate sandbox and production trust;
- asymmetric production credentials;
- short-lived client assertions and access tokens;
- nonce/JTI replay prevention;
- one-time SSO launch consumption;
- exact callback/return destination policy;
- scoped API authorization;
- institution and integration isolation;
- academic coverage isolation for faculty, department and programme integrations;
- idempotency for mutation workflows;
- request and payload bounds;
- key rotation, expiry and revocation;
- signed webhook delivery;
- step-up controls for sensitive administrative operations;
- security and administrative audit trails;
- no automatic account linking based on a person's name;
- no secrets, OTPs, private keys or full authentication tokens in logs;
- no partner credentials in frontend JavaScript or mobile bundles.

See [`SECURITY.md`](SECURITY.md) for the repository security policy.

---

## Academic mapping

Partners send codes they own:

```json
{
  "institution": "EXAMPLE_UNIVERSITY",
  "college": "SCIENCE",
  "department": "COMPUTING",
  "programme": "BSC_COMPUTING",
  "level": "300"
}
```

PrymeStudy maps those values to canonical PrymeStudy academic records.

If a required mapping is missing or ambiguous, Connect fails safely. It does not guess an institution, department, programme, level or course.

A valid mapping does not grant an application access beyond its approved integration coverage.

---

## Identity contract

Every federated identity has a stable external subject owned by the partner integration:

```json
{
  "identity": {
    "sub": "0b8a17e9-5d69-46e8-96e6-a0a7a71dfc3b",
    "first_name": "Ada",
    "last_name": "Student",
    "email": "ada.student@example.edu",
    "email_verified": true,
    "matric_number": "EXU/2026/001"
  },
  "academic": {
    "institution": "EXAMPLE_UNIVERSITY",
    "department": "COMPUTING",
    "programme": "BSC_COMPUTING",
    "level": "300"
  }
}
```

`identity.sub` remains stable when mutable attributes such as surname, email, phone or academic level change.

Account resolution follows this order:

```text
Existing external identity link
        ↓ otherwise
Strong existing-account candidate + ownership verification where required
        ↓ otherwise
Controlled new-account provisioning
```

Names are supporting profile data, never proof of account ownership.

---

## Environment isolation

Sandbox and production are separate security domains.

```text
Sandbox
├── sandbox integration identity
├── sandbox keys/credentials
├── sandbox callbacks
├── sandbox mappings
└── sandbox data policy

Production
├── production integration identity
├── production keys/credentials
├── approved production callbacks
├── production mappings
└── production policy/audit
```

Sandbox credentials cannot authenticate against production resources.

A production incident affecting one integration can be contained by rotating or revoking that integration without disabling unrelated systems.

---

## Production readiness

Creating a sandbox application does not grant production access.

The production lifecycle is intentionally controlled:

```text
Create sandbox application
        ↓
Register public key
        ↓
Configure academic mappings
        ↓
Pass partner certification
        ↓
Request production
        ↓
PrymeStudy platform review
        ↓
Separate production application + production key
        ↓
Approval and controlled smoke test
```

Institution administrators can request production access but cannot self-approve it. Production approval, suspension and reactivation remain central PrymeStudy platform operations.

Before any university, department or association is approved for production, use [`docs/PRODUCTION_READINESS.md`](docs/PRODUCTION_READINESS.md) and [`docs/PARTNER_CERTIFICATION.md`](docs/PARTNER_CERTIFICATION.md) as the release gates.

---

## Open-source boundary

PrymeStudy Connect is open source at the integration layer so institutions can inspect, audit and implement the client-side protocol without relying on hidden SDK behavior.

### Public in this repository

- protocol documentation;
- official SDK source;
- schemas and public data contracts;
- authentication client code;
- request/response models;
- signing and verification helpers;
- webhook verification behavior;
- error contracts;
- secure reference examples;
- compatibility and migration guidance.

### Private inside PrymeStudy

- internal account-matching heuristics;
- fraud and anti-abuse systems;
- risk scoring;
- internal session implementation;
- production secrets and private signing material;
- private operations/admin tooling;
- sensitive production infrastructure;
- controls whose disclosure would materially weaken the platform.

Open source means the integration contract is inspectable and interoperable. It does not expose PrymeStudy's private security authority.

---

## Repository structure

```text
prymestudy-connect/
├── README.md
├── LICENSE
├── SECURITY.md
├── CONTRIBUTING.md
├── docs/
├── examples/
├── openapi/
├── schemas/
└── sdk/
    ├── php/
    ├── typescript/
    └── python/
```

---

## Contributing

Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before changing protocol or SDK behavior.

The shared core must remain institution-agnostic. Real student data, production credentials, private PrymeStudy server internals and unsafe browser-side credential examples do not belong in this repository.

---

## License

PrymeStudy Connect is licensed under the [Apache License 2.0](LICENSE).

---

<p align="center">
  <strong>One secure integration contract. Any institution. Any approved backend stack.</strong>
</p>
