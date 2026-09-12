# Webhooks

## Purpose

PrymeStudy Connect webhooks deliver institution-approved events to registered HTTPS endpoints.

Webhook delivery is asynchronous and at-least-once. Consumers must therefore verify authenticity and process events idempotently.

## Endpoint requirements

Production webhook endpoints must:

- use HTTPS;
- be explicitly registered to one integration/environment;
- not redirect to unregistered hosts;
- return quickly and perform expensive work asynchronously;
- tolerate retries and out-of-order delivery where the event type permits it.

## Event envelope

Every event follows the public webhook schema.

Example:

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

Consumers must branch on `type`, not infer event meaning from arbitrary fields.

## Signing

PrymeStudy signs webhook deliveries at the HTTP layer.

The preferred signature profile uses standard HTTP Message Signatures with a registered PrymeStudy verification key.

The signed component set should include the request method/target and integrity-sensitive headers such as the content digest, event identifier and timestamp.

Consumers must reject a delivery when:

- signature verification fails;
- the signing key is unknown/revoked;
- the event timestamp is outside the accepted freshness window;
- the content digest does not match the body;
- the delivery/event identifier is a disallowed replay;
- the integration/environment binding is inconsistent.

## Delivery headers

Conceptual headers:

```http
Content-Type: application/json
Content-Digest: sha-256=:...:
X-PrymeStudy-Event-Id: evt_01J...
X-PrymeStudy-Timestamp: 2026-09-12T20:48:00Z
Signature-Input: ...
Signature: ...
```

Implementations should use the official SDK verifier rather than reconstructing signature canonicalization manually where an SDK exists.

## Idempotency

Use `id` as the durable event identity.

A consumer should record processed event IDs and safely return success when the same event is delivered again after successful processing.

Do not use timestamp alone as the deduplication key.

## Retries

PrymeStudy may retry eligible failures.

Consumers should distinguish:

- `2xx`: accepted/successful;
- `4xx`: generally permanent consumer/request failure unless documented otherwise;
- `429`: temporary rate-limit/backpressure signal;
- `5xx`: temporary consumer failure eligible for retry.

Retry schedules should use bounded exponential backoff with jitter.

## Ordering

Global ordering is not guaranteed.

Where ordering matters, event payloads may include a resource version or sequence value. Consumers must not assume that network arrival order equals canonical update order.

## Endpoint rotation

Changing a webhook endpoint or verification key is a security-sensitive operation and should be audited.

Key rotation should support an overlap window where both the old and new verification keys can be trusted according to published lifecycle status.

## Failure isolation

Repeated failures may cause an endpoint to be paused without disabling unrelated integrations or endpoints.

Operational dashboards should expose delivery status, failure counts and recent attempts without exposing full sensitive payloads by default.

## Secret and PII handling

Never include:

- access tokens;
- private keys;
- passwords;
- OTPs;
- unnecessary personal data.

Webhook payloads should carry only the information required by the event contract and approved integration scopes.
