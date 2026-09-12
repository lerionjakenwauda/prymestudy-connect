# Academic mapping

PrymeStudy Connect never requires a partner to know PrymeStudy database primary keys.

Partners send stable codes from their own systems. PrymeStudy maps those codes to canonical institution-owned academic records.

## Example

```text
Partner code              PrymeStudy canonical target
EXAMPLE_UNIVERSITY      → Institution
SCIENCE                 → College / Faculty / School
COMPUTING               → Department
BSC_COMPUTING           → Programme
300                     → Level / Cohort
```

## Rules

1. Codes are scoped to one Connect integration.
2. Codes are case-sensitive unless the integration explicitly normalizes them.
3. A mapping is explicit; Connect must not guess a canonical record from a similar name.
4. Missing required mappings fail safely with a mapping error.
5. Mapping changes are security/academic-governance events and must be audited.
6. Production mappings are independent from sandbox mappings.
7. Deleting a mapping must not silently rewrite already-linked users or historical records.

## Mapping types

Connect v1 recognizes these logical mapping types:

- `institution`
- `college`
- `department`
- `programme`
- `level`
- `course` where an institution chooses mapping rather than API-created external records

An institution may not use every layer. For example, a school that has no college/faculty layer can omit it.

## SSO behavior

A launch that requires an unresolved academic code is rejected or placed in a controlled review/setup state. Connect never assigns a user to an arbitrary department/programme simply to complete login.

## Data synchronization behavior

SIS resources preserve their partner external IDs. This allows reconciliation and safe upserts without coupling the partner to PrymeStudy numeric IDs.

## Field authority

Mapping does not automatically make every user field institution-controlled.

Typical institution-authoritative fields can include:

- institution;
- matric/registration identifier;
- academic programme;
- department;
- level/cohort;
- enrollment state.

Typical user-controlled fields can include:

- preferred name;
- avatar;
- bio;
- personal preferences.

Email/phone authority depends on the verified claims policy configured for the integration.
