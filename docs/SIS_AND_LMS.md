# SIS and LMS Integration

## Purpose

PrymeStudy Connect allows approved institutional systems to synchronize academic data without coupling partners to PrymeStudy's internal database schema.

The external system remains authoritative only for the fields and resources explicitly assigned to it.

## Resource families

Connect exposes resource-oriented APIs for institution-approved domains such as:

```text
students
academic-structures
courses
course-offerings
enrollments
attendance
```

Not every integration receives every resource.

## External identifiers

Every synchronized record should include a stable identifier from the source system.

Example:

```json
{
  "external_id": "sis-student-002918",
  "matric_number": "EXU/2026/001",
  "status": "active"
}
```

PrymeStudy preserves the source relationship so subsequent updates modify the same logical record rather than creating duplicates.

Partners must not depend on PrymeStudy's internal database primary keys.

## Scopes

Read and write capabilities are separate.

Examples:

```text
students:read
students:write
courses:read
courses:write
enrollments:read
enrollments:write
attendance:read
```

An integration with `students:read` cannot write students.

An integration with SSO launch permission receives no academic-data permission unless explicitly granted.

## Bulk synchronization

Enterprise SIS integrations often require high-volume synchronization.

Bulk endpoints should support:

- bounded request sizes;
- per-record external IDs;
- idempotency;
- per-record validation outcomes;
- asynchronous job processing for large payloads;
- status polling or webhook completion events;
- retry without duplication;
- deterministic error reporting.

Conceptual bulk request:

```json
{
  "records": [
    {
      "external_id": "sis-student-002918",
      "matric_number": "EXU/2026/001",
      "first_name": "Ada",
      "last_name": "Student",
      "department": "COMPUTING",
      "programme": "BSC_COMPUTING",
      "level": "300",
      "status": "active"
    }
  ]
}
```

## Validation before canonical writes

An accepted HTTP request does not imply every academic record is valid.

Validation includes:

- required fields;
- allowed enum values;
- source identifier uniqueness;
- academic mapping availability;
- relationship consistency;
- scope/field authority;
- institution boundary;
- active academic periods where applicable.

Invalid records must not be silently coerced into unrelated academic entities.

## Academic mappings

External systems send stable source codes.

Example:

```text
SCIENCE       -> PrymeStudy division/faculty record
COMPUTING     -> PrymeStudy department record
BSC_COMPUTING -> PrymeStudy programme record
300           -> PrymeStudy level/cohort record
```

Mappings are configured per integration and may differ between systems belonging to the same institution.

## Reconciliation

Connect must support reconciliation between source truth and PrymeStudy state.

A synchronization job should expose enough information to answer:

- how many records were received;
- how many were created;
- how many were updated;
- how many were unchanged;
- how many failed validation;
- which records failed and why;
- whether the job is final or still processing.

## Deactivation and deletion

Source-system deletion must not automatically mean destructive deletion inside PrymeStudy.

Depending on resource policy, a source record may become:

```text
inactive
withdrawn
archived
ended
```

Destructive deletion requires explicit policy because PrymeStudy may need to preserve academic history, audit records, attendance or legal/operational evidence.

## Webhook feedback

Where useful, PrymeStudy can notify the source system when asynchronous synchronization completes or when a partner-relevant canonical event occurs.

The partner must verify webhook signatures and process duplicate deliveries idempotently.

## Rate limits and backpressure

Partners must respect published rate-limit headers and back off on `429 Too Many Requests`.

Large periodic synchronization should use bulk/job workflows rather than thousands of uncoordinated single-record requests.

## Data ownership

A partner write should only update fields for which that integration is authoritative.

For example, an SIS may control:

```text
matric_number
programme
level
institutional_status
```

while a student may control:

```text
preferred_name
avatar
bio
personal_preferences
```

Connect must not let one system silently overwrite fields owned by another authority.
