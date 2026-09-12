# Environments

PrymeStudy Connect isolates sandbox and production trust.

## Sandbox

Use sandbox for development, automated integration tests and academic mapping validation.

Sandbox has its own:

- integration ID and client ID;
- public signing keys;
- access tokens;
- return URL allow-list;
- academic mappings;
- webhooks;
- rate limits and data policy.

Sandbox credentials cannot authenticate against production resources.

## Production

Production integrations operate against real institutional/student records and therefore require production-level policy, scopes and keys.

Production configuration is independent from sandbox. Copying a sandbox configuration does not copy its trust material.

## Environment identifiers

Public IDs and credentials should make accidental mixing difficult. Examples:

```text
ps_test_...
ps_live_...
```

The prefix is a usability guard, not a security control. The server always enforces the environment associated with the stored integration.

## Return URLs

SSO return destinations are exact allow-listed URLs/origins according to the integration policy. Connect does not accept arbitrary caller-provided redirect destinations.

## Data

Do not use real sensitive student data in sandbox unless the institution's approved policy explicitly allows it. Prefer synthetic records.

## Promotion

Moving an integration to production means creating/approving production trust, not converting a test credential in place.

A production review can include:

- institution/partner verification;
- scope review;
- return URL/domain review;
- registered key validation;
- academic mapping review;
- webhook endpoint review;
- security/contact ownership;
- successful sandbox test evidence.
