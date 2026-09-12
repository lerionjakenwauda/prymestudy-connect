# Partner SSO

## Goal

Partner SSO lets a user who has already authenticated to an approved institutional portal enter PrymeStudy without repeating registration or sign-in.

Connect does not share partner cookies, passwords or raw authentication tokens with PrymeStudy.

## Flow

```text
User authenticated in partner portal
        ↓
Partner backend requests Connect launch
        ↓
Connect authenticates the integration
        ↓
Connect validates claims + academic mappings
        ↓
PrymeStudy resolves/creates/links identity
        ↓
Connect returns one-time launch URL
        ↓
Browser follows opaque launch URL
        ↓
PrymeStudy consumes launch exactly once
        ↓
Normal PrymeStudy session is established
```

## Launch endpoint

```http
POST /connect/v1/launches
```

The caller must hold the `connect:sso.launch` scope.

## Launch request

Example:

```json
{
  "external_subject": "0b8a17e9-5d69-46e8-96e6-a0a7a71dfc3b",
  "identity": {
    "first_name": "Ada",
    "last_name": "Student",
    "email": "ada.student@example.edu",
    "email_verified_by_partner": true,
    "matric_number": "EXU/2026/001"
  },
  "academic": {
    "institution": "EXAMPLE_UNIVERSITY",
    "division": "SCIENCE",
    "department": "COMPUTING",
    "programme": "BSC_COMPUTING",
    "level": "300"
  },
  "return_url": "https://prymestudy.com/study-room",
  "correlation_id": "partner-request-7d9301"
}
```

The full machine contract is defined by `schemas/launch-request.schema.json`.

## External subject

`external_subject` is required and must be:

- stable over the lifetime of the partner account;
- unique within that integration;
- non-secret;
- independent of mutable fields such as email, phone, surname or level.

UUID/ULID-style identifiers are recommended.

Sequential database IDs should not be exposed unless the partner has explicitly accepted that identifier as its durable public subject.

## Field authority

Connect distinguishes identity/academic fields by authority.

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
(integration_id, external_subject)
```

Connect resolves the same PrymeStudy account.

A single tuple must never link to multiple PrymeStudy users.

## Existing-account candidate

If no external link exists, PrymeStudy may detect a strong existing-account candidate using approved identifiers.

A candidate is not automatically proof of ownership.

Names are never sufficient to auto-link an account.

Where ownership is uncertain, PrymeStudy performs an account-verification flow before creating the permanent external link.

## New account

If no safe candidate exists, PrymeStudy may provision a new account using the approved claims and academic mappings.

Identity linking and provisioning should be performed atomically where practical so a partially completed transaction cannot create duplicate links.

## Return URL security

`return_url` is optional and must match an allowed destination policy.

PrymeStudy must not perform arbitrary open redirects.

Allowed return destinations are configured per integration or resolved to PrymeStudy-owned route identifiers.

## One-time launch security

Launch credentials are:

- opaque;
- short-lived;
- single use;
- securely generated;
- stored/compared in a way that avoids unnecessary plaintext persistence;
- bound to the integration and resolved identity;
- invalid after success, expiry or policy revocation.

The browser must not receive raw identity JSON or partner credentials in the URL.

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

Partner-visible errors should be actionable without disclosing sensitive security details.
