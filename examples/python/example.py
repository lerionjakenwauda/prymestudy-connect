import os
from pathlib import Path

from prymestudy_connect import ConnectClient, ConnectConfig

config = ConnectConfig(
    client_id=os.environ["PRYMESTUDY_CONNECT_CLIENT_ID"],
    private_key_pem=Path(os.environ["PRYMESTUDY_CONNECT_PRIVATE_KEY"]).read_text(),
    key_id=os.environ["PRYMESTUDY_CONNECT_KEY_ID"],
    token_endpoint=os.environ["PRYMESTUDY_CONNECT_TOKEN_ENDPOINT"],
    api_base_url=os.environ["PRYMESTUDY_CONNECT_API_BASE_URL"],
    scopes=("connect:sso.launch",),
)

with ConnectClient(config) as connect:
    launch = connect.create_launch(
        {
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
        }
    )
    print(launch["launch_url"])
