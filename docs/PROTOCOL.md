# PrymeStudy Connect Protocol

## 1. Scope

PrymeStudy Connect is a versioned, institution-agnostic protocol for integrating approved external academic systems with PrymeStudy.

It supports four primary integration classes:

- partner SSO and identity handoff;
- SIS/LMS and academic-data APIs;
- event delivery through signed webhooks;
- controlled import/export and reconciliation workflows.

The protocol is server-to-server. Browser and mobile clients may participate in user-facing handoffs, but must never hold partner credentials or signing keys.

## 2. Authority surfaces

Connect uses two PrymeStudy authority surfaces with deliberately different responsibilities.

```text
auth.prymestudy.com
  PrymeStudy Identity
  - integration/service authentication
  - Connect machine-token issuance
  - browser identity handoff and account ownership verification

prymestudy.com/connect/v1
  PrymeStudy platform authority
  - SSO launch creation
  - academic mappings
  - SIS/LMS data operations
  - institution/tenant policy
  - synchronization jobs
```

Auth proves identities. The platform decides academic and product authorization. These surfaces share PrymeStudy's canonical identity/platform authority; Connect does not create a second user database.

## 3. Protocol versioning

The public platform API uses a major-version namespace:

```text
/connect/v1/...
```

The Connect token authority uses the same major version on Identity:

```text
https://auth.prymestudy.com/connect/v1/oauth/token
```

Within a major version:

- additive optional fields may be introduced;
- new error codes may be introduced when existing semantics are insufficient;
- existing required fields must not change meaning;
- fields must not be silently repurposed;
- enum expansion must be treated defensively by SDKs.

Breaking wire-contract changes require a new major version.

## 4. Trust model

Every request is bound to:

- one partner/institution relationship;
- one integration/application identity;
- one environment (`sandbox` or `production`);
- an approved scope set;
- active credentials/key material;
- applicable academic and operational policy.

An integration identity is never shared between unrelated external systems.

## 5. Authentication profiles

### 5.1 High-assurance profile

The production profile uses asymmetric client authentication. The partner keeps its private key; PrymeStudy stores only public verification material and credential metadata.

OAuth 2.0 client authentication using a signed JWT client assertion is the machine-authentication profile. The assertion must contain, at minimum:

```json
{
  "iss": "<client_id>",
  "sub": "<client_id>",
  "aud": "https://auth.prymestudy.com/connect/v1/oauth/token",
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

### 5.2 Compatibility profile

A compatibility credential may be enabled for approved integrations that cannot use asymmetric client authentication.

Compatibility credentials must still be:

- environment-specific;
- scoped;
- rotatable/revocable;
- server-side only;
- replay protected;
- excluded from logs and browser code.

Compatibility mode is not a reason to weaken callback validation, nonce/JTI checks, idempotency or tenant isolation.

## 6. Access tokens and authorization

PrymeStudy Identity issues short-lived machine access tokens limited to explicitly approved scopes.

Example scope set:

```text
connect:sso.launch
students:write
courses:write
enrollments:write
sync:read
```

Possession of one scope does not imply another.

An SSO-capable integration does not automatically gain academic-data write access. The platform API independently verifies token validity, integration status, tenant binding and required scope.

## 7. Standard request requirements

Every authenticated request must use HTTPS in production.

Mutation requests should include:

```http
Authorization: Bearer <short-lived-access-token>
Content-Type: application/json
Accept: application/json
Idempotency-Key: <uuid-or-equivalent-unique-value>
X-PrymeStudy-Request-Id: <optional-caller-request-id>
```

Servers enforce request body limits and reject invalid media types and malformed JSON.

### Idempotency

Create/update operations that can produce duplicate side effects support an idempotency key.

A repeated request with the same idempotency key and equivalent payload returns the original logical result. Reusing the same key with a materially different payload must fail.

## 8. Partner SSO launch

Canonical platform endpoint:

```http
POST https://prymestudy.com/connect/v1/launches
```

The caller must hold the `connect:sso.launch` scope.

Request shape is defined by `schemas/launch-request.schema.json` and uses `identity.sub` as the immutable external subject:

```json
{
  "identity": {
    "sub": "student-immutable-id",
    "email": "student@example.edu",
    "email_verified": true
  },
  "academic": {
    "institution": "EXAMPLE_UNIVERSITY",
    "department": "COMPUTING",
    "programme": "BSC_COMPUTING",
    "level": "300"
  },
  "return_url": "https://app.prymestudy.com/dashboard"
}
```

A successful response contains an Identity-host browser handoff:

```json
{
  "launch_id": "<public-launch-id>",
  "launch_url": "https://auth.prymestudy.com/connect/launch/psl_...",
  "expires_at": "2026-09-13T15:00:00Z"
}
```

The launch material is:

- opaque;
- cryptographically random;
- short-lived;
- one-time consumable;
- bound to the creating integration;
- protected against replay;
- removed from the active handoff after completion.

Raw student identity data and partner credentials are never embedded in the launch URL.

## 9. Identity resolution

Resolution order:

1. existing `(integration, identity.sub)` link;
2. strong existing-account candidate according to PrymeStudy policy;
3. controlled new-account provisioning.

Name matching is never sufficient proof of account ownership.

When ownership verification is required, the browser remains on the central Identity surface until the user has proved control of the existing PrymeStudy identity. Auth proves the human; the platform still determines academic/product access.

The public protocol does not expose PrymeStudy's internal account-matching/risk heuristics.

## 10. Academic mapping

Partners send stable external codes, not PrymeStudy database primary keys.

Example:

```json
{
  "institution": "EXAMPLE_UNIVERSITY",
  "college": "SCIENCE",
  "department": "COMPUTING",
  "programme": "BSC_COMPUTING",
  "level": "300"
}
```

PrymeStudy resolves the external codes to canonical records configured for that integration.

Missing or ambiguous required mappings fail safely. The platform must not silently guess a department, programme, level or course.

## 11. Academic data APIs

SIS/LMS APIs are scope-controlled and tenant-bound.

Current Connect v1 platform resources are:

```text
POST /connect/v1/students:upsert
POST /connect/v1/courses:upsert
POST /connect/v1/enrollments:upsert
GET  /connect/v1/sync-jobs/{job_id}
```

Resource identifiers exposed to partners use stable public/source identifiers. Internal database primary keys do not become integration contracts.

Bulk endpoints enforce:

- bounded batch sizes;
- per-record validation results;
- idempotency;
- deterministic external identifiers;
- partial-failure reporting without silently discarding errors;
- reconciliation through PrymeStudy's canonical institutional data layer.

## 12. Webhooks

Webhook envelopes are defined by `schemas/webhook-event.schema.json`.

Every delivery has a globally unique event/delivery identity and timestamp.

PrymeStudy signs webhook HTTP messages. Consumers must verify the signature before trusting the payload, validate freshness and process idempotently.

See `docs/WEBHOOKS.md`.

## 13. Error model

Errors use a stable machine-readable envelope:

```json
{
  "error": {
    "code": "mapping_missing",
    "message": "A required academic mapping could not be resolved.",
    "request_id": "req_...",
    "details": []
  }
}
```

`message` is for developers and may evolve. Clients must branch on `code`, not message text.

Authentication failures must not reveal sensitive credential-validation details.

## 14. Observability and audit

PrymeStudy and partner implementations should propagate request/correlation identifiers without logging secrets.

Security-sensitive events include:

- credential creation/rotation/revocation;
- integration enable/disable;
- production activation;
- academic mapping changes;
- token issuance/rejection;
- SSO launch acceptance/rejection;
- identity link creation/removal;
- API authorization failures;
- webhook verification/delivery failures.

## 15. Data minimization

An integration sends only claims required for the approved purpose.

Do not send large profile payloads simply because a field exists in the source system.

Sensitive fields require an explicit business purpose, authority rule, retention policy and permission scope.
