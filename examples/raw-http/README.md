# Raw HTTPS integration

PrymeStudy Connect does not require an SDK. Any trusted server-side runtime can implement the protocol.

Connect separates identity authentication from platform operations:

```text
auth.prymestudy.com            integration identity + token issuance
prymestudy.com/connect/v1      institution-scoped platform API
```

## 1. Create an ES256 client assertion

Create a JWT signed by the integration's registered P-256 private key.

Protected header:

```json
{ "alg": "ES256", "kid": "key_live_example", "typ": "JWT" }
```

Claims:

```json
{
  "iss": "ps_live_example",
  "sub": "ps_live_example",
  "aud": "https://auth.prymestudy.com/connect/v1/oauth/token",
  "iat": 1800000000,
  "exp": 1800000120,
  "jti": "jti_unique_random_value"
}
```

Use a fresh `jti` for every assertion. Assertion lifetime must be short.

## 2. Exchange for an access token

```http
POST /connect/v1/oauth/token HTTP/1.1
Host: auth.prymestudy.com
Content-Type: application/x-www-form-urlencoded
Accept: application/json

grant_type=client_credentials&client_id=ps_live_example&client_assertion_type=urn%3Aietf%3Aparams%3Aoauth%3Aclient-assertion-type%3Ajwt-bearer&client_assertion=eyJ...&scope=connect%3Asso.launch
```

PrymeStudy Identity authenticates the integration and returns a short-lived scoped bearer token.

## 3. Call the platform API

```http
POST /connect/v1/launches HTTP/1.1
Host: prymestudy.com
Authorization: Bearer <opaque-access-token>
Content-Type: application/json
Accept: application/json
Idempotency-Key: idem_01J...

{
  "identity": {
    "sub": "student-immutable-example",
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

A successful launch response contains a short-lived browser handoff such as:

```text
https://auth.prymestudy.com/connect/launch/psl_...
```

The browser should only receive the returned one-time `launch_url`; it must never receive the client assertion, access token or partner private key.

See `../../openapi/connect-v1.yaml` for the full machine-readable API.
