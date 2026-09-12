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
  <a href="#official-sdks">SDKs</a>
  &nbsp;&middot;&nbsp;
  <a href="#security-model">Security</a>
  &nbsp;&middot;&nbsp;
  <a href="#protocol">Protocol</a>
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

---

## Capabilities

### Partner SSO

A user who is already authenticated in an approved partner portal can enter PrymeStudy without creating a second registration flow.

```text
Authenticated partner user
        ↓
Partner backend requests a Connect launch
        ↓
Integration identity + scope verified
        ↓
External identity and academic mapping resolved
        ↓
One-time opaque launch created
        ↓
PrymeStudy consumes launch exactly once
        ↓
Normal PrymeStudy session
```

No partner secret or raw student identity payload is placed in the browser redirect URL.

### SIS and LMS integration

Scoped APIs and controlled synchronization workflows support institution-approved data domains such as:

- students and institutional identities;
- academic structures;
- programmes and levels/cohorts;
- courses and course offerings;
- enrolments and registrations;
- attendance where authorized;
- other resources exposed by an approved integration scope.

External systems use stable source identifiers and academic codes. They do not depend on PrymeStudy database primary keys.

### APIs

API access is isolated per integration, environment and scope.

Example capability scopes:

```text
connect:sso.launch
students:read
students:write
courses:read
courses:write
enrollments:read
enrollments:write
webhooks:manage
```

Identity federation never automatically grants academic-data write access.

### Webhooks

PrymeStudy Connect delivers signed HTTPS events to registered endpoints. Consumers verify the delivery signature, timestamp and content integrity before processing the event and must process duplicate deliveries idempotently.

### Data exchange and reconciliation

Bulk synchronization is designed for bounded batches, stable external identifiers, idempotency, per-record validation, asynchronous processing where necessary and deterministic reconciliation results.

---

## Protocol

The public protocol is the source of truth. Official SDKs implement the same protocol rather than defining language-specific behavior.

Core documentation:

- [`docs/PROTOCOL.md`](docs/PROTOCOL.md) — Connect v1 wire and trust contract.
- [`docs/AUTHENTICATION.md`](docs/AUTHENTICATION.md) — machine authentication, keys, tokens and rotation.
- [`docs/SSO.md`](docs/SSO.md) — partner SSO and one-time launch flow.
- [`docs/SIS_AND_LMS.md`](docs/SIS_AND_LMS.md) — academic synchronization and source authority.
- [`docs/WEBHOOKS.md`](docs/WEBHOOKS.md) — signed event delivery and replay handling.

Machine-readable contracts:

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
| **Raw HTTPS** | Protocol + schemas | Java, .NET, Go, Ruby, Rust and other backend stacks |

All official SDKs follow the same core behavior:

1. validate integration configuration;
2. authenticate the integration server-to-server;
3. obtain short-lived scoped access;
4. apply request idempotency where required;
5. call the versioned Connect API;
6. normalize machine-readable errors;
7. keep credentials and private signing material out of browser/mobile clients.

---

## Authentication model

High-assurance integrations use asymmetric client authentication.

The partner keeps its private signing key. PrymeStudy stores public verification material and credential metadata.

The default machine-authentication profile uses OAuth 2.0 client credentials with a short-lived signed JWT client assertion. Assertions are bound to:

- the registered client ID;
- the exact token audience;
- a short validity window;
- a unique replay-protected `jti`;
- an active registered key and key ID;
- the integration environment.

For high-assurance deployments, mTLS and certificate-bound access can be layered onto the integration policy.

A shared-secret/HMAC compatibility profile may be enabled for approved systems that cannot support asymmetric credentials, but compatibility mode does not weaken scope enforcement, replay protection, environment isolation or callback validation.

---

## Security model

PrymeStudy Connect is designed around **least privilege, tenant isolation, short-lived trust and auditable security state**.

Security requirements include:

- TLS for all production traffic;
- separate sandbox and production trust domains;
- asymmetric production credentials where supported;
- short-lived client assertions and access tokens;
- nonce/JTI replay prevention;
- one-time SSO launch consumption;
- exact callback/return destination policy;
- scoped API authorization;
- institution and integration isolation;
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
  "division": "SCIENCE",
  "department": "COMPUTING",
  "programme": "BSC_COMPUTING",
  "level": "300"
}
```

PrymeStudy maps those values to canonical PrymeStudy academic records.

If a required mapping is missing or ambiguous, Connect fails safely. It must never guess an institution, department, programme, level or course.

---

## Identity contract

Every federated identity has a stable external subject that belongs to the partner integration.

```json
{
  "external_subject": "0b8a17e9-5d69-46e8-96e6-a0a7a71dfc3b",
  "identity": {
    "first_name": "Ada",
    "last_name": "Student",
    "email": "ada.student@example.edu",
    "email_verified_by_partner": true,
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

The external subject remains stable when mutable attributes such as surname, email, phone or academic level change.

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
│   ├── PROTOCOL.md
│   ├── AUTHENTICATION.md
│   ├── SSO.md
│   ├── SIS_AND_LMS.md
│   └── WEBHOOKS.md
├── schemas/
│   ├── academic-claims.schema.json
│   ├── launch-request.schema.json
│   └── webhook-event.schema.json
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
