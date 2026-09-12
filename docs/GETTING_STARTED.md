# Getting started

PrymeStudy Connect lets an approved institution or academic partner connect an existing portal, SIS, LMS or backend service to PrymeStudy through one versioned integration contract.

## 1. Create an integration

An institution administrator creates a Connect integration from **Developer & Integrations** in PrymeStudy.

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

## 2. Generate a P-256 key pair

PrymeStudy Connect v1 uses ES256 client assertions. The **private key stays with the partner**. PrymeStudy stores only the public key.

OpenSSL example:

```bash
openssl ecparam -name prime256v1 -genkey -noout -out connect-private.pem
openssl ec -in connect-private.pem -pubout -out connect-public.pem
```

Protect the private key using the institution's secret-management system. Do not commit it to source control or ship it to browser/mobile code.

## 3. Register the public key

Register `connect-public.pem` on the integration and record the issued key ID (`kid`). Keys can overlap during rotation so integrations can move from an old key to a new key without downtime.

## 4. Configure an official SDK

Required configuration:

```text
client_id
key_id
private_key
OAuth token endpoint
Connect API base URL
approved scopes
```

The SDK creates a short-lived ES256 `private_key_jwt` client assertion, exchanges it for an opaque access token, caches the token until shortly before expiry and sends scoped API calls with `Authorization: Bearer ...`.

## 5. Use the required capability

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
  "return_url": "https://prymestudy.com/dashboard"
}
```

The response contains a short-lived one-time launch URL. Redirect the user's browser to that URL. Never place the client assertion, access token, private key or raw identity JSON in a redirect URL.

### SIS / LMS synchronization

Use the resource methods exposed by the SDK:

```text
upsert students
upsert courses
upsert enrollments
read sync-job status
```

Mutating requests use an `Idempotency-Key` so safe retries do not duplicate writes.

## 6. Verify webhooks

Use the SDK verifier against the exact raw request body before parsing/processing the event. Enforce event-ID idempotency in durable storage.

## 7. Move to production

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
