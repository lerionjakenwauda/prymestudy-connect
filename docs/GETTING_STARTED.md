# Getting started

PrymeStudy Connect lets an approved institution or academic partner connect an existing portal, SIS, LMS or backend service to PrymeStudy through one versioned integration contract.

## 1. Create an integration

An institution administrator creates a Connect integration from **Developer & Integrations → PrymeStudy Connect** in PrymeStudy.

Each integration is isolated by application and environment. Do not share credentials between unrelated systems.

Examples:

```text
Central SIS / Sandbox
Central SIS / Production
Student Portal / Production
Department Portal / Production
```

The integration defines:

- environment (`sandbox` or `production`);
- approved scopes;
- allowed return URLs for SSO;
- academic mappings;
- registered public signing keys;
- webhook configuration where required.

## 2. Know the two PrymeStudy authorities

Connect deliberately separates identity from academic/product authorization.

```text
auth.prymestudy.com
  PrymeStudy Identity
  - authenticates the integration/service identity
  - issues short-lived Connect access tokens
  - consumes browser identity handoffs
  - proves or establishes the PrymeStudy human identity

prymestudy.com/connect/v1
  PrymeStudy platform authority
  - creates institution-scoped SSO launches
  - synchronizes students, courses and enrolments
  - exposes synchronization status
  - enforces institution, scope, mapping and product policy
```

The production token endpoint is:

```text
https://auth.prymestudy.com/connect/v1/oauth/token
```

The production platform API base is:

```text
https://prymestudy.com
```

This separation does not create another user database. PrymeStudy Identity and the platform share the canonical PrymeStudy identity authority while keeping responsibilities explicit.

## 3. Generate a P-256 key pair

PrymeStudy Connect v1 uses ES256 client assertions. The **private key stays with the partner**. PrymeStudy stores only the public key.

OpenSSL example:

```bash
openssl ecparam -name prime256v1 -genkey -noout -out connect-private.pem
openssl ec -in connect-private.pem -pubout -out connect-public.pem
```

Protect the private key using the institution's secret-management system. Do not commit it to source control or ship it to browser/mobile code.

## 4. Register the public key

Register `connect-public.pem` on the integration and record the issued key ID (`kid`). Keys can overlap during rotation so integrations can move from an old key to a new key without downtime.

## 5. Configure an official SDK

Required configuration:

```text
client_id
key_id
private_key
OAuth token endpoint = https://auth.prymestudy.com/connect/v1/oauth/token
Connect API base URL = https://prymestudy.com
approved scopes
```

The SDK creates a short-lived ES256 `private_key_jwt` client assertion, exchanges it with PrymeStudy Identity for an opaque access token, caches the token until shortly before expiry and sends scoped API calls with `Authorization: Bearer ...`.

## 6. Use the required capability

### Partner SSO

Create a launch using a stable partner subject and academic codes:

```json
{
  "identity": {
    "sub": "student-immutable-id",
    "email": "student@example.edu",
    "email_verified": true,
    "matric_number": "2026/00001",
    "first_name": "Ada",
    "last_name": "Student"
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

The partner backend sends this request to:

```text
POST https://prymestudy.com/connect/v1/launches
```

The response contains a short-lived one-time browser URL on PrymeStudy Identity, for example:

```text
https://auth.prymestudy.com/connect/launch/psl_...
```

Redirect the user's browser to that URL. Never place the client assertion, access token, private key or raw identity JSON in a redirect URL.

### SIS / LMS synchronization

Use the resource methods exposed by the SDK against the platform API:

```text
POST /connect/v1/students:upsert
POST /connect/v1/courses:upsert
POST /connect/v1/enrollments:upsert
GET  /connect/v1/sync-jobs/{job_id}
```

Mutating requests use an `Idempotency-Key` so safe retries do not duplicate writes.

## 7. Verify webhooks

Use the SDK verifier against the exact raw request body before parsing/processing the event. Enforce event-ID idempotency in durable storage.

## 8. Move to production

Production uses separate credentials and policies from sandbox. Production enablement is controlled by the institution's PrymeStudy configuration and PrymeStudy's production-access policy.

Never reuse sandbox keys/tokens in production.

## Raw HTTP

SDKs are optional. Any backend can implement the published OpenAPI/protocol contract using standard HTTPS, ES256 and OAuth client credentials.

See:

- `PROTOCOL.md`
- `AUTHENTICATION.md`
- `SSO.md`
- `SIS_AND_LMS.md`
- `WEBHOOKS.md`
- `../openapi/connect-v1.yaml`
