# Authentication and Client Trust

## Purpose

PrymeStudy Connect authenticates external systems, not end users. A partner application's backend proves its own identity to Connect before it can create SSO launches, call academic-data APIs or manage webhooks.

Human authentication remains the responsibility of the relevant identity authority. Partner portals authenticate their own users before presenting approved claims to Connect.

## Integration identity

Each external system receives a distinct integration identity.

Do not reuse one client across:

- production and sandbox;
- unrelated departments;
- unrelated portals;
- SIS and unrelated custom applications;
- separate institutions.

This allows independent scope assignment, key rotation, revocation, audit and incident containment.

## Preferred production authentication

High-assurance integrations use asymmetric client authentication.

The partner generates and protects a private key. PrymeStudy stores the corresponding public key or JWKS material.

The client authenticates to the token endpoint with a short-lived JWT client assertion. The assertion is signed by the partner and includes:

- `iss`: registered client ID;
- `sub`: registered client ID;
- `aud`: exact token endpoint audience;
- `iat`: issued-at time;
- `exp`: short expiration;
- `jti`: unique replay-protected identifier.

The token endpoint validates all claims, the registered key, allowed algorithm, integration status, environment and scope policy before returning a short-lived access token.

## Token request

Conceptual request:

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&
client_id=ps_live_example&
scope=connect:sso.launch students:read&
client_assertion_type=urn:ietf:params:oauth:client-assertion-type:jwt-bearer&
client_assertion=<signed-jwt>
```

The exact token endpoint base URL is supplied by the PrymeStudy developer/integration environment.

## Access token handling

Access tokens are bearer credentials and must be protected accordingly.

Clients must:

- store them only in trusted server memory/storage as appropriate;
- never expose them to frontend JavaScript;
- never place them in query strings;
- avoid logging full token values;
- request only required scopes;
- tolerate expiry and obtain a new token rather than attempting to extend an expired token locally.

## Key registration and rotation

A production integration may have multiple verification keys during a controlled rotation window.

Each key has a stable key identifier (`kid`) and lifecycle state such as:

```text
pending
active
retiring
revoked
expired
```

Rotation should allow the new key to become valid before the old key is retired, preventing downtime.

A revoked key must fail immediately regardless of an otherwise valid signature.

## Replay protection

JWT client assertions must have a unique `jti` and a tightly bounded lifetime.

The authorization server stores or otherwise tracks recently accepted assertion identifiers long enough to reject replay.

Clock-skew tolerance must be bounded and documented. A large tolerance window weakens replay protection.

## mTLS

For high-assurance enterprise integrations, PrymeStudy may require mutual TLS in addition to asymmetric client authentication or may issue certificate-bound tokens.

Certificate identity is bound to the integration and environment. Rotating a certificate must be an audited operation.

## Compatibility authentication

Where a partner cannot support asymmetric credentials, Connect may issue a scoped shared-secret compatibility credential.

Compatibility mode must still enforce:

- TLS;
- environment separation;
- strong random secrets;
- secret display/storage controls;
- rotation and revocation;
- short-lived derived access where supported;
- nonce/timestamp replay protection where direct request signing is used;
- least-privilege scopes.

## Browser and mobile boundary

The following are prohibited:

```text
React bundle contains client secret      ✗
Mobile application embeds private key    ✗
Client secret in URL                     ✗
Private key copied into frontend config  ✗
```

Correct pattern:

```text
Browser/mobile
     ↓
Partner backend
     ↓ authenticates as integration
PrymeStudy Connect
```

## Authorization is separate from authentication

A successfully authenticated client can still receive `403 Forbidden` when it lacks the required scope, is outside the allowed institution/academic scope, attempts a prohibited environment operation or violates policy.

Authentication answers "which integration is calling?"
Authorization answers "may this integration perform this action on this resource?"
