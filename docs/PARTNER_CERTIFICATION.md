# Partner certification

Every external institution, department, association, SIS/LMS provider or approved third party completes sandbox certification before PrymeStudy enables a production Connect integration.

## Certification identity

Certification is performed against a dedicated sandbox integration with sandbox-only keys and test data. The partner must identify a technical owner and security contact before production review.

Certification is specific to the application's approved **system type, scopes and academic coverage**. Passing certification for one department does not certify another department or grant institution-wide access.

Record the certification target before testing:

```text
Partner / institution:
Application name:
Sandbox client ID:
System type:
Coverage: institution | college | department | programme
Coverage target:
Requested scopes:
Technical owner:
Security contact:
```

## Required tests

### Authentication and key management

1. Valid ES256 client assertion obtains a short-lived Connect access token.
2. Wrong `client_id` is rejected.
3. Unknown or revoked `kid` is rejected.
4. Invalid signature is rejected.
5. Wrong assertion audience is rejected.
6. Expired assertion is rejected.
7. Excessively long assertion lifetime is rejected.
8. Reuse of the same assertion `jti` is rejected.
9. A rotated key works during the approved overlap period.
10. A revoked key immediately stops obtaining new tokens.

### Scope, coverage and tenant isolation

1. `connect:sso.launch` cannot write SIS records.
2. A data-write scope cannot imply another write scope.
3. A token issued to one integration cannot access another integration's tenant context.
4. Sandbox credentials cannot authenticate as production credentials.
5. Revoked/suspended integrations are denied.
6. A department-scoped application accepts records that resolve to its approved department.
7. The same department-scoped application rejects a correctly mapped record belonging to a different department.
8. A programme-scoped application rejects another programme, including one in the same department.
9. A college/faculty-scoped application accepts only canonical departments/programmes belonging to the approved college/faculty.
10. A unit-scoped application's valid mapping cannot be used to bypass its coverage boundary.
11. A departmental or association application is not silently widened to institution coverage during production promotion.

See [`INTEGRATION_COVERAGE.md`](INTEGRATION_COVERAGE.md) for the authorization model.

### Partner SSO

1. A valid new user can receive a one-time launch and enter PrymeStudy.
2. The launch URL contains no raw identity payload, access token or partner credential.
3. The same launch cannot be consumed twice.
4. Expired launches fail closed.
5. An unapproved return URL is rejected.
6. A returning external subject resolves to the same PrymeStudy identity.
7. An existing-account candidate is not silently linked on name alone.
8. Ownership verification is required when an existing-account link is ambiguous.
9. Missing required academic mappings fail closed.
10. Invalid/mismatched institution claims fail closed.
11. Academic claims outside a unit-scoped integration's approved coverage fail closed.

### SIS/LMS synchronization

Where data scopes are enabled:

1. Student upsert is idempotent.
2. Course upsert is idempotent.
3. Enrollment upsert is idempotent.
4. Duplicate retries with the same idempotency key do not create duplicate records.
5. Invalid academic mapping rejects the affected write.
6. Cross-institution external IDs cannot escape the integration tenant.
7. Sync-job status is visible only to the Connect integration that created the job.
8. Batch-size limits are enforced.
9. Student and course writes outside the approved academic coverage are rejected.
10. Enrollment references cannot use an otherwise valid course mapping to escape department/programme coverage.

### Webhooks

Where webhooks are enabled:

1. Valid signatures verify against the published PrymeStudy public key.
2. Modified body fails verification.
3. Modified event ID fails verification.
4. Stale timestamp fails verification.
5. Wrong key ID fails verification.
6. Duplicate event IDs are processed idempotently by the partner.

## Production approval evidence

A production request should include:

- sandbox client ID;
- certification date;
- partner technical owner;
- partner security contact;
- system type;
- approved coverage scope and canonical coverage target;
- production callback/return destinations;
- requested production scopes;
- public-key fingerprint for the production key;
- academic mapping review result;
- explicit evidence that out-of-coverage access was rejected for unit-scoped applications;
- any deviations or approved compensating controls.

## Approval rule

A sandbox pass does not activate production. PrymeStudy creates or approves a separate production integration after review. Production uses separate client identity, keys, policy, mappings and audit state.

A material expansion of coverage—for example department → institution—requires a new security review/certification rather than being treated as a routine metadata edit.
