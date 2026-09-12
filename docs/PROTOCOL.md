# PrymeStudy Connect Protocol

## 1. Scope

PrymeStudy Connect is a versioned, institution-agnostic protocol for integrating approved external academic systems with PrymeStudy.

It supports four primary integration classes:

- partner SSO and identity handoff;
- SIS/LMS and academic-data APIs;
- event delivery through signed webhooks;
- controlled import/export and reconciliation workflows.

The protocol is server-to-server. Browser and mobile clients may participate in user-facing handoffs, but must never hold partner credentials or signing keys.

## 2. Protocol versioning

The public API uses a major-version namespace:

```text
/connect/v1/...
```

Within a major version:

- additive optional fields may be introduced;
- new error codes may be introduced when existing semantics are insufficient;
- existing required fields must not change meaning;
- fields must not be silently repurposed;
- enum expansion must be treated defensively by SDKs.

Breaking wire-contract changes require a new major version.

## 3. Trust model

Every request is bound to:

- one partner/institution relationship;
- one integration/application identity;
- one environment (`sandbox` or `production`);
- an approved scope set;
- active credentials/key material;
- applicable academic and operational policy.

An integration identity is never shared between unrelated external systems.

## 4. Authentication profiles

### 4.1 High-assurance profile

The preferred production profile uses asymmetric client authentication. The partner keeps its private key; PrymeStudy stores only public verification material and credential metadata.

OAuth 2.0 client authentication using a signed JWT client assertion is the standard machine-authentication profile. The assertion must contain, at minimum:

```json
{
  "iss": "<client_id>",
  "sub": "<client_id>",
  "aud": "<token_endpoint>",
  "iat": 1789231120,
  "exp": 1789231240,
  "jti": "<cryptographically-random-unique-id>"
}
```

Requirements:

- `iss` and `sub` must equal the registered client ID;
- `aud` must exactly match the expected token endpoint audience;
- assertion lifetime must be short;
- `jti` must be unique and replay-protected;
- signing key must be active and belong to the same integration/environment;
- algorithm must be explicitly allowed for the registered key.

PrymeStudy may additionally require mTLS or certificate-bound access tokens for high-assurance institutional deployments.

### 4.2 Compatibility profile

A shared-secret/HMAC profile may be enabled for approved integrations that cannot use asymmetric client authentication.

Compatibility credentials must still be:

- environment-specific;
- scoped;
- rotatable/revocable;
- server-side only;
- replay protected;
- excluded from logs and browser code.

Compatibility mode is not a reason to weaken callback validation, nonce/JTI checks, idempotency or tenant isolation.

## 5. Access tokens and authorization

Machine access tokens are short-lived and limited to explicitly approved scopes.

Example scope set:

```text
connect:sso.launch
students:read
students:write
courses:read
enrollments:read
enrollments:write
webhooks:manage
```

Possession of one scope does not imply another.

An SSO-capable integration does not automatically gain academic-data write access.

## 6. Standard request requirements

Every authenticated request must use HTTPS in production.

Mutation requests should include:

```http
Authorization: Bearer <short-lived-access-token>
Content-Type: application/json
Accept: application/json
Idempotency-Key: <uuid-or-equivalent-unique-value>
X-PrymeStudy-Request-Id: <optional-caller-request-id>
```

Servers must enforce sensible request body limits and reject invalid media types and malformed JSON.

### Idempotency

Create/update operations that can produce duplicate side effects must support an idempotency key.

A repeated request with the same idempotency key and equivalent payload should return the original logical result. Reusing the same key with a materially different payload must fail.

## 7. Partner SSO launch

Canonical endpoint:

```http
POST /connect/v1/launches
```

Request shape is defined by `schemas/launch-request.schema.json`.

The request binds:

- immutable external subject;
- approved identity claims;
- academic mapping codes;
- requested return destination, if one is allowed;
- optional partner correlation metadata that contains no secrets.

Successful response:

```json
{
  "launch_id": "psl_01J...",
  "launch_url": "https://prymestudy.com/connect/launch/psl_01J...",
  "expires_at": "2026-09-12T20:48:30Z"
}
```

The launch material must be:

- opaque;
- cryptographically random;
- short-lived;
- one-time consumable;
- bound to the creating integration;
- protected against replay;
- removed from the visible URL as soon as the handoff is completed.

Raw student identity data and partner credentials must never be embedded in the launch URL.

## 8. Identity resolution

Resolution order:

1. existing `(integration, external_subject)` link;
2. strong existing-account candidate according to PrymeStudy policy;
3. controlled new-account provisioning.

Name matching is never sufficient proof of account ownership.

The public protocol does not expose PrymeStudy's internal account-matching/risk heuristics.

## 9. Academic mapping

Partners send stable external codes, not PrymeStudy database primary keys.

Example:

```json
{
  "institution": "EXAMPLE_UNIVERSITY",
  "division": "SCIENCE",
  "department": "COMPUTING",
  "programme": "BSC_COMPUTING",
  "level": "300"
}
```

PrymeStudy resolves the external codes to canonical records configured for that integration.

Missing or ambiguous required mappings fail safely. The platform must not silently guess a department, programme, level or course.

## 10. Academic data APIs

SIS/LMS APIs are scope-controlled and tenant-bound.

Resource families may include:

```text
students
academic-structures
courses
course-offerings
enrollments
attendance
```

Resource identifiers exposed to partners should use stable public identifiers. Internal database primary keys must not become integration contracts.

Bulk endpoints should support:

- bounded batch sizes;
- per-record validation results;
- idempotency;
- deterministic external identifiers;
- partial-failure reporting without silently discarding errors;
- asynchronous processing where required for scale.

## 11. Webhooks

Webhook envelopes are defined by `schemas/webhook-event.schema.json`.

Every delivery has a globally unique event/delivery identity and timestamp.

PrymeStudy signs webhook HTTP messages. Consumers must verify the signature before trusting the payload, validate freshness and process idempotently.

See `docs/WEBHOOKS.md`.

## 12. Error model

Errors use a stable machine-readable envelope:

```json
{
  "error": {
    "code": "invalid_academic_mapping",
    "message": "A required academic mapping could not be resolved.",
    "request_id": "req_01J...",
    "details": []
  }
}
```

`message` is for developers and may evolve. Clients must branch on `code`, not on message text.

Authentication failures must not reveal sensitive credential-validation details.

## 13. Observability and audit

PrymeStudy and partner implementations should propagate request/correlation identifiers without logging secrets.

Security-sensitive events include:

- credential creation/rotation/revocation;
- integration enable/disable;
- production activation;
- academic mapping changes;
- SSO launch acceptance/rejection;
- identity link creation/removal;
- API authorization failures;
- webhook verification/delivery failures.

## 14. Data minimization

An integration sends only claims required for the approved purpose.

Do not send large profile payloads simply because the field exists in the source system.

Sensitive fields must have an explicit business purpose, authority rule, retention policy and permission scope.
