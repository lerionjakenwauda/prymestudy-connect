# Webhooks

PrymeStudy Connect webhooks deliver institution-approved events to registered HTTPS endpoints. Delivery is asynchronous and at-least-once, so consumers must verify authenticity and process events idempotently.

## Event envelope

```json
{
  "id": "evt_01J8Y9Z4M5J3Q3S6D1F8K2W0X9",
  "type": "student.enrollment.updated",
  "created_at": "2026-09-12T20:48:00Z",
  "environment": "production",
  "integration_id": "int_01J8Y8X3...",
  "data": {
    "student_external_id": "sis-student-002918",
    "course_external_id": "course-csc301",
    "status": "enrolled"
  }
}
```

Consumers branch on `type` and use `id` as the durable event/deduplication identifier.

## Connect Webhook Signature v1

Production webhook deliveries use a versioned ES256 signature profile. PrymeStudy holds the signing private key; consumers verify with the published/registered P-256 public key identified by `kid`.

Headers:

```http
Content-Type: application/json
X-PrymeStudy-Signature-Version: v1
X-PrymeStudy-Event-Id: evt_01J...
X-PrymeStudy-Timestamp: 1800000000
X-PrymeStudy-Key-Id: whk_live_...
X-PrymeStudy-Signature: <base64url P1363 ES256 signature>
```

The exact **raw HTTP body bytes** are hashed with SHA-256. PrymeStudy signs this UTF-8 canonical message:

```text
v1\n{unix_timestamp}\n{event_id}\n{sha256_hex(raw_body)}
```

The ES256 signature is encoded as the 64-byte IEEE-P1363 value (`R || S`) and then base64url encoded without padding.

Official SDKs implement this verification profile. Consumers should use the SDK verifier rather than reimplementing ECDSA/canonicalization.

## Required verification order

Before processing an event:

1. require signature version `v1`;
2. require event ID, timestamp, key ID and signature headers;
3. resolve the signing public key for the key ID;
4. reject timestamps outside the configured freshness window (300 seconds by default);
5. hash the exact raw body and reconstruct the canonical message;
6. verify the ES256 signature;
7. parse JSON only after signature verification;
8. require the JSON `id` to match the signed event ID header;
9. check durable replay/idempotency storage for that event ID;
10. process the event transactionally and record the event ID as processed.

A cryptographically valid event may still be unauthorized for a consumer if its integration/environment does not match local configuration; enforce that binding too.

## Key rotation

Webhook signing keys have IDs and lifecycle status. Rotation should allow a bounded overlap period in which both the retiring and new public keys are available for verification. Private signing keys are never distributed to consumers.

## Retries

PrymeStudy may retry eligible failures using bounded backoff.

- `2xx`: accepted/successful;
- `429`: temporary backpressure/rate limit;
- `5xx`: temporary consumer failure;
- most other `4xx`: permanent consumer/request failure.

Global ordering is not guaranteed. Where ordering matters, event data can include resource version/sequence information.

## Endpoint requirements

Production endpoints must:

- use HTTPS;
- belong to one approved integration/environment;
- avoid unregistered redirect chains;
- return quickly and perform expensive work asynchronously;
- tolerate retries;
- reject oversized bodies according to the published event limit.

## Sensitive data

Webhook payloads must not contain private keys, access tokens, passwords, OTPs or unnecessary personal data. Consumers should also avoid logging full sensitive payloads by default.
