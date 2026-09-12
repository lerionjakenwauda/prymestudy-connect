# Error contract

Connect API errors use one predictable envelope.

```json
{
  "error": {
    "code": "mapping_missing",
    "message": "A required academic mapping is not configured.",
    "request_id": "req_01J...",
    "details": [
      {
        "field": "academic.programme",
        "external_code": "BSC_COMPUTING"
      }
    ]
  }
}
```

## Request IDs

Every API response should include/associate a request ID. SDK exceptions expose it when available. Partners should provide the request ID when contacting support; never send private keys, tokens or full student payloads in support messages.

## Common codes

| Code | Meaning | Typical action |
|---|---|---|
| `invalid_client` | Client ID/assertion/key could not be authenticated | Check integration, key ID, audience and key lifecycle |
| `assertion_expired` | Client assertion is outside its accepted lifetime | Correct system clock and issue a new assertion |
| `assertion_replayed` | Assertion JTI has already been consumed | Create a new assertion with a fresh JTI |
| `invalid_scope` | Requested token/API scope is not approved | Request only approved scopes |
| `insufficient_scope` | Access token lacks required capability | Use an appropriately scoped integration/token |
| `integration_inactive` | Integration is paused/suspended/revoked | Resolve status in Developer & Integrations |
| `mapping_missing` | External academic code has no canonical mapping | Configure the missing mapping |
| `mapping_ambiguous` | Mapping configuration cannot resolve one canonical target | Correct integration mappings |
| `invalid_return_url` | SSO return URL is not allow-listed | Register/use an approved return URL |
| `identity_conflict` | Identity cannot be safely linked automatically | Complete controlled account verification/review |
| `idempotency_conflict` | Same key reused with a different request | Use the original payload or a new idempotency key |
| `validation_failed` | Payload does not satisfy the API/schema contract | Correct fields listed in `details` |
| `rate_limited` | Integration exceeded its rate policy | Respect `Retry-After` and back off |

## HTTP status guidance

- `400`: malformed protocol/auth request;
- `401`: unauthenticated/invalid token or client assertion;
- `403`: authenticated but not permitted/scoped;
- `404`: resource unavailable within the integration's tenant boundary;
- `409`: idempotency/state/identity conflict;
- `422`: structurally valid request with invalid resource fields/mappings;
- `429`: rate limit/backpressure;
- `5xx`: PrymeStudy service failure; retry only where the operation is idempotent or an idempotency key is present.

SDKs normalize API error envelopes into language-native Connect exceptions.
