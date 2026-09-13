# Integration coverage

PrymeStudy Connect applications are issued for an explicit academic coverage boundary. The coverage boundary answers a separate question from API scopes:

- **Scopes** decide what an application may do.
- **Coverage** decides where in the institution it may do it.

An application should be created for the smallest independently operated system that needs access. Do not reuse one credential across unrelated departmental portals, association websites, SIS services, or vendor backends.

## Supported coverage

| Coverage | Use it for | Example |
| --- | --- | --- |
| `institution` | A central university portal, SIS, LMS, ERP, or institution-wide backend | University central SIS |
| `college` | A faculty/college portal or backend that only serves that academic unit | College of Science portal |
| `department` | A departmental website, portal, association backend, or department-owned service | Computer Science department portal |
| `programme` | A system intentionally limited to one canonical academic programme | BSc Mathematics programme service |

A department or programme integration is not merely labelled with that unit. PrymeStudy validates resolved academic mappings against the approved coverage and rejects records outside it.

## Example: department portal

A Computer Science department website could receive a Connect application with:

```text
Environment: sandbox
System type: department_portal
Coverage: department
Coverage target: Computer Science
Scopes:
  connect:sso.launch
  students:write
  sync:read
```

That application may launch or synchronize users whose academic claims resolve into the approved Computer Science boundary. It must not use a mapping for Mathematics, Accounting, Engineering, or another academic unit to bypass that boundary.

If a second department has its own independently operated website, create a second application with separate credentials and its own coverage target.

## Example: institution-wide SIS

A central university SIS can instead be issued:

```text
System type: sis
Coverage: institution
Scopes:
  students:write
  courses:write
  enrollments:write
  sync:read
```

The application may work across the institution, subject to its approved scopes and mappings.

Institution-wide access should be reserved for systems that genuinely require it. A departmental integration should not receive institution-wide coverage simply to avoid maintaining mappings.

## Relationship to academic mappings

Coverage does not replace academic mappings.

Partners still submit stable external codes such as:

```json
{
  "institution": "LASUSTECH",
  "department": "COMPUTER_SCIENCE",
  "programme": "BSC_COMPUTER_SCIENCE",
  "level": "300"
}
```

PrymeStudy maps those codes to canonical academic records and then evaluates the resulting records against the application's approved coverage.

Conceptually:

```text
Partner academic code
        ↓
Canonical PrymeStudy mapping
        ↓
Coverage authorization
        ↓
Allowed or rejected
```

A mapping existing does **not** automatically make that mapping usable by every application belonging to the institution.

## Association and departmental integrations

A student or academic association may integrate when the institution authorizes it. The system type can identify it as an association portal while its academic coverage remains independently constrained.

For example, an association serving one department may use:

```text
System type: association_portal
Coverage: department
Coverage target: Mathematical Sciences
```

If an association legitimately serves the whole institution, institution-wide coverage can be considered during certification instead of being assumed.

## Sandbox and production

Coverage is selected while creating the sandbox application and is carried into the separate production application during promotion.

Production approval is a PrymeStudy-controlled step. A partner administrator can request production access but cannot self-approve it.

Changes that materially expand access—for example moving from one department to the whole institution—should be treated as a new security review rather than an informal configuration change.

## Isolation rules

Every independently operated integration should have its own:

- client identity;
- P-256 signing keys;
- environment;
- scopes;
- coverage boundary;
- mappings;
- allowed SSO destinations;
- audit history;
- revocation lifecycle.

This isolation allows PrymeStudy to suspend or rotate one compromised departmental integration without disabling the university's other systems.

## Choosing the right model

Use the following rule:

```text
One central institutional system?
    → institution coverage

One faculty/college system?
    → college coverage

One department website/backend?
    → department coverage

One programme-specific system?
    → programme coverage

Several independently operated systems?
    → separate Connect applications
```

For production access, the selected coverage is reviewed as part of [partner certification](PARTNER_CERTIFICATION.md) and the [production-readiness gate](PRODUCTION_READINESS.md).
