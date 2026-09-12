# PrymeStudy Connect Python SDK

Framework-neutral server-side Python 3.11+ client for Django, Flask, FastAPI, workers and institutional services.

```python
from pathlib import Path
from prymestudy_connect import ConnectClient, ConnectConfig

config = ConnectConfig(
    client_id="ps_live_example",
    private_key_pem=Path("/secure/connect-private.pem").read_text(),
    key_id="key_live_example",
    token_endpoint="https://api.prymestudy.com/connect/v1/oauth/token",
    api_base_url="https://api.prymestudy.com",
    scopes=("connect:sso.launch", "students:write"),
)

with ConnectClient(config) as connect:
    launch = connect.create_launch({
        "identity": {
            "sub": student.connect_id,
            "email": student.email,
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

## SIS / LMS

```python
connect.upsert_students(students)
connect.upsert_courses(courses)
connect.upsert_enrollments(enrollments)
job = connect.get_sync_job(job_id)
```

## Webhooks

```python
from prymestudy_connect import verify_webhook

event = verify_webhook(
    raw_request_body,
    request_headers,
    prymestudy_webhook_public_key,
    expected_key_id="whk_live_example",
)
```

Store partner private keys in a real secret-management system and never expose them to browser/mobile code.
