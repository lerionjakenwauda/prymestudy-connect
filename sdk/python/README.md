# PrymeStudy Connect Python SDK

Framework-neutral server-side Python 3.11+ client for Django, Flask, FastAPI, workers and institutional services.

## Production endpoints

PrymeStudy Connect separates machine identity from platform operations:

```text
Token endpoint: https://auth.prymestudy.com/connect/v1/oauth/token
API base:       https://prymestudy.com
```

The partner private key remains server-side and never enters PrymeStudy.

```python
from pathlib import Path
from prymestudy_connect import ConnectClient, ConnectConfig

config = ConnectConfig(
    client_id="ps_live_example",
    private_key_pem=Path("/secure/connect-private.pem").read_text(),
    key_id="key_live_example",
    token_endpoint="https://auth.prymestudy.com/connect/v1/oauth/token",
    api_base_url="https://prymestudy.com",
    scopes=("connect:sso.launch", "students:write"),
)

with ConnectClient(config) as connect:
    launch = connect.create_launch({
        "identity": {
            "sub": "student-immutable-example",
            "email": "student@example.edu",
            "email_verified": True,
        },
        "academic": {
            "institution": "EXAMPLE_UNIVERSITY",
            "department": "COMPUTING",
            "programme": "BSC_COMPUTING",
            "level": "300",
        },
    })
```

Redirect the user's browser only to `launch["launch_url"]`. Do not expose the Connect access token, client assertion or private key to browser code.

## SIS / LMS resources

```python
with ConnectClient(config) as connect:
    connect.upsert_students(students)
    connect.upsert_courses(courses)
    connect.upsert_enrollments(enrollments)
    job = connect.get_sync_job(job_id)
```

Mutating operations support idempotency keys so safe retries do not duplicate writes.

## Webhooks

Use the SDK's webhook verifier against the exact raw request body before parsing or processing the event. Store processed event IDs durably so retries cannot duplicate business actions.
