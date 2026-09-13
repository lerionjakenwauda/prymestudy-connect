# Authentication and Client Trust

## Purpose

PrymeStudy Connect authenticates external systems, not end users. A partner application's backend proves its own service identity before it can create SSO launches, call academic-data APIs or consume other approved Connect capabilities.

PrymeStudy uses a central identity boundary:

```text
auth.prymestudy.com
```

For Connect, PrymeStudy Identity authenticates the external integration identity and issues short-lived scoped machine access. The PrymeStudy platform remains authoritative for institutions, academic mappings, enrolments, permissions, entitlements and other product policy.

Human authentication also belongs to PrymeStudy Identity when a Connect browser handoff needs to establish or verify a PrymeStudy user account. Partner portals continue to authenticate their own users before presenting approved claims to Connect.

## Production endpoints

Machine token endpoint:

```text
https://auth.prymestudy.com/connect/v1/oauth/token
```

Platform API base:

```text
https://prymestudy.com
```

The signed JWT `aud` claim must exactly equal the token endpoint URL above in production.

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

The partner generates and protects a private P-256 key. PrymeStudy stores the corresponding public verification material and credential metadata. Partner private keys never enter PrymeStudy.

The client authenticates to PrymeStudy Identity with a short-lived JWT client assertion signed with ES256. The assertion includes:

- `iss`: registered client ID;
- `sub`: registered client ID;
- `aud`: exact token endpoint audience;
- `iat`: issued-at time;
- `exp`: short expiration;
- `jti`: unique replay-protected identifier.

The token authority validates all claims, the registered key, allowed algorithm, integration status, environment and scope policy before returning a short-lived opaque access token.

## Token request

```http
POST https://auth.prymestudy.com/connect/v1/oauth/token
Content-Type: application/x-www-form-urlencoded
Accept: application/json

grant_type=client_credentials&
client_id=ps_live_example&
scope=connect:sso.launch students:write&
client_assertion_type=urn:ietf:params:oauth:client-assertion-type:jwt-bearer&
client_assertion=<signed-jwt>
```

The client assertion audience is:

```text
https://auth.prymestudy.com/connect/v1/oauth/token
```

A successful response contains a short-lived bearer token that is then presented to the scoped platform API on `https://prymestudy.com/connect/v1/...`.

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

Clock-skew tolerance must remain bounded. A large tolerance window weakens replay protection.

## mTLS

For high-assurance enterprise integrations, PrymeStudy may require mutual TLS in addition to asymmetric client authentication or may issue certificate-bound tokens.

Certificate identity is bound to the integration and environment. Rotating a certificate must be an audited operation.

## Compatibility authentication

Where an approved partner cannot support asymmetric credentials, a compatibility credential may be made available under an explicit integration policy.

Compatibility mode must still enforce:

- TLS;
- environment separation;
- strong random secrets;
- secret display/storage controls;
- rotation and revocation;
- short-lived derived access where supported;
- nonce/timestamp replay protection where direct request signing is used;
- least-privilege scopes.

Compatibility support must never become the default production trust profile.

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
     ↓ authenticates integration at PrymeStudy Identity
auth.prymestudy.com
     ↓ short-lived scoped token
PrymeStudy Connect platform API
```

## Human identity handoff

When a partner creates an SSO launch, the platform returns a short-lived one-time URL on the Identity host:

```text
https://auth.prymestudy.com/connect/launch/psl_...
```

That browser flow may establish a new PrymeStudy identity, reuse an existing external identity link, or require ownership verification for an existing account. It does not give the Auth surface authority to decide academic membership, billing entitlement or other product permissions.

## Authorization is separate from authentication

A successfully authenticated client can still receive `403 Forbidden` when it lacks the required scope, is outside the allowed institution/academic scope, attempts a prohibited environment operation or violates platform policy.

Authentication answers **which integration is calling?**
Authorization answers **may this integration perform this action on this institution-scoped resource?**
