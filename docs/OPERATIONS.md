# Operations and incident response

This runbook defines the minimum operational controls for a production PrymeStudy Connect integration.

## Normal operations

Each production integration has a named PrymeStudy owner, partner technical owner and security contact. Operationally significant changes are audited.

Monitor at minimum:

- token issuance success/failure rate;
- invalid/replayed assertion rate;
- launch creation and consumption failures;
- 401/403/409/422/429 response rates;
- sync-job failures and rejected rows;
- webhook delivery failures where enabled;
- key expiry/rotation windows;
- unusual request volume per integration.

Never log raw access tokens, client assertions, OTPs, private keys or full authentication payloads.

## Key rotation

1. Partner generates a new P-256 key pair in its own secret-management environment.
2. Partner registers only the new public key with PrymeStudy.
3. Both old and new public keys may remain active for a short approved overlap period.
4. Partner deploys the new private key and `kid`.
5. Confirm successful token issuance with the new key.
6. Revoke the old key.
7. Verify subsequent use of the old key fails.
8. Record the rotation in the partner change log.

Private keys are never transmitted to PrymeStudy.

## Suspected key compromise

1. Revoke the affected public-key credential immediately.
2. Revoke outstanding Connect access tokens for the integration.
3. If compromise scope is unclear, suspend/revoke the integration.
4. Preserve relevant audit records and request IDs.
5. Contact the partner security owner through the agreed channel.
6. Determine the first/last suspicious activity window.
7. Require a fresh production key pair before reactivation.
8. Document the incident and corrective action.

Do not reactivate a compromised credential.

## Partner compromise or runaway integration

PrymeStudy can contain an incident at the integration boundary without disabling unrelated institutions:

- revoke one key;
- revoke all active tokens for one integration;
- suspend/revoke one integration;
- disable its legacy data-exchange binding;
- preserve existing PrymeStudy user accounts and identity links for investigation.

## SSO incident

If launch replay, account-link ambiguity or unexpected identity resolution is observed:

1. suspend SSO launch capability for the affected integration;
2. preserve audit/launch records;
3. do not delete identity links during the initial investigation;
4. review external subject, integration ID, mapping state and ownership-verification path;
5. rotate partner trust material if authenticity is uncertain;
6. restore only after a controlled sandbox reproduction passes.

## Data synchronization incident

For incorrect SIS/LMS writes:

1. revoke or remove the affected write scope;
2. stop further sync jobs from the integration;
3. identify the idempotency keys and sync-job IDs involved;
4. reconcile against the authoritative source;
5. roll back through supported product/data workflows rather than direct ad-hoc database edits where possible;
6. restore the scope only after the mapping/source defect is corrected.

## Emergency disable

Production access must be removable without code deployment. Revocation/suspension controls are the first response; deployment rollback is not the primary kill switch.

## Recovery validation

Before re-enabling a production integration after an incident, repeat the relevant subset of `PARTNER_CERTIFICATION.md` in sandbox and confirm the production configuration differs only where expected (production keys, allowed destinations, environment and approved policy).
