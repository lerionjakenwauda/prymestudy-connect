# Versioning and compatibility

PrymeStudy Connect uses semantic versioning for the public integration contract and official SDK releases.

## Contract versions

The stable HTTP contract is versioned in the URL (`/connect/v1/...`). A breaking wire-contract change requires a new major API path such as `/connect/v2/...`.

SDK package versions use semantic versioning:

- patch: fixes that preserve public behavior;
- minor: backward-compatible functionality;
- major: changes requiring consumer action.

## Compatibility guarantees

Within a major API version, PrymeStudy may add optional response fields, new error detail fields, new webhook event types and new optional request capabilities. Consumers must ignore unknown response fields and webhook event types they have not subscribed to.

PrymeStudy does not silently change the meaning of an existing scope, claim, error code or required request field within a major version.

## Deprecation

A production feature is deprecated before removal. Deprecation documentation identifies:

- the affected endpoint/field/scope/SDK surface;
- the replacement;
- the earliest removal version/date;
- required migration steps.

Security emergencies may require faster disablement of a compromised algorithm, endpoint or behavior. Such changes are communicated as security actions rather than ordinary deprecations.

## SDK/runtime support

The repository currently targets:

- PHP 8.2+;
- Node.js 20+;
- Python 3.11+.

Dropping a runtime line is treated as a compatibility change and is documented in release notes.

## Releases

Production releases are Git-tagged using `vMAJOR.MINOR.PATCH`. The release workflow validates package versions, reruns security/test gates, builds SDK/protocol artifacts, produces SHA-256 checksums and attaches build provenance before creating the GitHub release.

Integrators should pin an SDK release or dependency range appropriate to their deployment process. Production systems should not depend directly on a moving `main` branch.
