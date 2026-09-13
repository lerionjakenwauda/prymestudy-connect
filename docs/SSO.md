# Partner SSO

## Goal

Partner SSO lets a user who has already authenticated to an approved institutional portal enter PrymeStudy without repeating an unsafe duplicate registration flow.

Connect does not share partner cookies, passwords or raw authentication tokens with PrymeStudy.

The browser identity handoff belongs to the central PrymeStudy Identity surface:

```text
auth.prymestudy.com
```

Academic/product authorization remains with the PrymeStudy platform.

## Flow

```text
User authenticated in partner portal
        ↓
Partner backend authenticates integration with PrymeStudy Identity
        ↓
auth.prymestudy.com issues short-lived scoped Connect token
        ↓
Partner backend requests a Connect launch from prymestudy.com
        ↓
Platform validates scope + institution + academic mappings
        ↓
Platform resolves the external identity state
        ↓
One-time Identity launch URL returned
        ↓
Browser follows https://auth.prymestudy.com/connect/launch/...
        ↓
PrymeStudy Identity resolves/provisions/verifies the human identity
        ↓
One-time launch consumed exactly once
        ↓
Browser returns to the approved PrymeStudy product destination
```

## Launch endpoint

```http
POST https://prymestudy.com/connect/v1/launches
```

The caller must hold the `connect:sso.launch` scope.

## Launch request

Example:

```json
{
  "identity": {
    "sub": "0b8a17e9-5d69-46e8-96e6-a0a7a71dfc3b",
    "first_name": "Ada",
    "last_name": "Student",
    "email": "ada.student@example.edu",
    "email_verified": true,
    "matric_number": "EXU/2026/001"
  },
  "academic": {
    "institution": "EXAMPLE_UNIVERSITY",
    "college": "SCIENCE",
    "department": "COMPUTING",
    "programme": "BSC_COMPUTING",
    "level": "300"
  },
  "return_url": "https://app.prymestudy.com/dashboard",
  "state": "partner-correlation-value"
}
```

The full machine contract is defined by `schemas/launch-request.schema.json`.

## External subject

`identity.sub` is required and must be:

- stable over the lifetime of the partner account;
- unique within that integration;
- non-secret;
- independent of mutable fields such as email, phone, surname or level.

UUID/ULID-style identifiers are recommended.

Sequential database IDs should not be exposed unless the partner has explicitly accepted that identifier as its durable public subject.

## Field authority

Connect distinguishes identity and academic fields by authority.

A verified institutional integration may be authoritative for fields such as:

- institution;
- department/programme;
- student/employee identifier;
- academic level/cohort.

PrymeStudy may remain authoritative or user-controlled for fields such as:

- preferred display name;
- avatar;
- bio;
- personal contact preferences.

Partner-provided email becomes trusted only according to the integration's email-verification policy.

## Existing identity link

If the tuple below already exists:

```text
(integration_id, identity.sub)
```

Connect resolves the same PrymeStudy account.

A single tuple must never link to multiple PrymeStudy users.

## Existing-account candidate

If no external link exists, PrymeStudy may detect a strong existing-account candidate using approved identifiers.

A candidate is not automatically proof of ownership.

Names are never sufficient to auto-link an account.

Where ownership is uncertain, the user remains on `auth.prymestudy.com` and completes account ownership verification before PrymeStudy creates the permanent external link.

## New account

If no safe candidate exists, PrymeStudy may provision a canonical PrymeStudy identity using the approved claims and academic mappings.

Identity linking and provisioning are performed atomically where practical so a partially completed transaction cannot create duplicate links.

Creating or proving an identity does not itself grant institution administration, course enrolment, billing entitlement or other product permissions.

## Launch response

Example:

```json
{
  "launch_id": "<launch-id>",
  "launch_url": "https://auth.prymestudy.com/connect/launch/psl_...",
  "expires_at": "2026-09-13T15:00:00Z"
}
```

The partner redirects the browser to `launch_url` exactly as returned. The partner must not reconstruct or modify it.

## Return URL security

`return_url` is optional and must be an exact allow-listed PrymeStudy first-party destination for the integration.

PrymeStudy does not perform arbitrary open redirects.

Typical destination:

```text
https://app.prymestudy.com/dashboard
```

Institution-specific first-party destinations can be used only when they are trusted and explicitly allow-listed for the integration.

## One-time launch security

Launch credentials are:

- opaque;
- short-lived;
- single use;
- securely generated;
- stored/compared without unnecessary plaintext persistence;
- bound to the integration and resolved identity state;
- invalid after success, expiry or policy revocation.

The browser never receives raw identity JSON, machine access tokens, client assertions or partner private keys in the URL.

## Failure behavior

Connect fails closed when:

- integration authentication fails;
- launch scope is missing;
- integration is suspended/revoked;
- required academic mapping is missing;
- a required claim is invalid;
- return destination is not allowed;
- identity linkage is ambiguous and cannot be safely resolved;
- environment policy is violated.

Partner-visible errors are actionable without disclosing sensitive security details.
