from __future__ import annotations

import unittest
from urllib.parse import parse_qs

import httpx
import jwt
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import ec

from prymestudy_connect import ConnectClient, ConnectConfig, ConnectError


def private_key_pem() -> str:
    key = ec.generate_private_key(ec.SECP256R1())
    return key.private_bytes(
        encoding=serialization.Encoding.PEM,
        format=serialization.PrivateFormat.PKCS8,
        encryption_algorithm=serialization.NoEncryption(),
    ).decode("ascii")


def config(**overrides) -> ConnectConfig:
    values = {
        "client_id": "ps_test_example",
        "private_key_pem": private_key_pem(),
        "key_id": "key_test",
        "token_endpoint": "https://auth.prymestudy.com/connect/v1/oauth/token",
        "api_base_url": "https://prymestudy.com",
        "scopes": ("connect:sso.launch",),
    }
    values.update(overrides)
    return ConnectConfig(**values)


class ConnectClientTest(unittest.TestCase):
    def test_remote_plaintext_http_is_rejected_but_loopback_is_allowed(self) -> None:
        with self.assertRaisesRegex(ValueError, "must use HTTPS"):
            config(token_endpoint="http://identity.example.edu/connect/v1/oauth/token")

        local = config(
            token_endpoint="http://127.0.0.1:8000/connect/v1/oauth/token",
            api_base_url="http://localhost:8000",
        )
        self.assertEqual(local.api_base_url, "http://localhost:8000")

    def test_auth_audience_token_cache_and_idempotency(self) -> None:
        requests: list[httpx.Request] = []

        def handler(request: httpx.Request) -> httpx.Response:
            requests.append(request)
            if str(request.url) == "https://auth.prymestudy.com/connect/v1/oauth/token":
                return httpx.Response(
                    200,
                    json={
                        "access_token": "pct_test_example_access_token",
                        "token_type": "Bearer",
                        "expires_in": 300,
                        "scope": "connect:sso.launch",
                    },
                )
            return httpx.Response(
                201,
                json={
                    "launch_id": f"launch-{len(requests)}",
                    "launch_url": "https://auth.prymestudy.com/connect/launch/psl_example",
                    "expires_at": "2026-09-13T16:00:00Z",
                },
            )

        transport = httpx.MockTransport(handler)
        http = httpx.Client(transport=transport, follow_redirects=False)
        try:
            client = ConnectClient(config(), http=http)
            client.create_launch(
                {"identity": {"sub": "student-example"}, "academic": {"institution": "EXAMPLE_UNIVERSITY"}},
                idempotency_key="idem_first_123456",
            )
            client.create_launch(
                {"identity": {"sub": "student-example"}, "academic": {"institution": "EXAMPLE_UNIVERSITY"}},
                idempotency_key="idem_second_123456",
            )
        finally:
            http.close()

        self.assertEqual(len(requests), 3, "second API call should reuse the cached access token")
        token_form = parse_qs(requests[0].content.decode("utf-8"))
        assertion = token_form["client_assertion"][0]
        claims = jwt.decode(assertion, options={"verify_signature": False, "verify_aud": False})
        self.assertEqual(claims["aud"], "https://auth.prymestudy.com/connect/v1/oauth/token")
        self.assertEqual(claims["iss"], "ps_test_example")
        self.assertTrue(claims["jti"])

        api_request = requests[1]
        self.assertEqual(str(api_request.url), "https://prymestudy.com/connect/v1/launches")
        self.assertEqual(api_request.headers["Authorization"], "Bearer pct_test_example_access_token")
        self.assertEqual(api_request.headers["Idempotency-Key"], "idem_first_123456")

    def test_redirect_response_is_not_followed(self) -> None:
        requests: list[httpx.Request] = []

        def handler(request: httpx.Request) -> httpx.Response:
            requests.append(request)
            if len(requests) == 1:
                return httpx.Response(200, json={"access_token": "pct_test_example", "expires_in": 300})
            return httpx.Response(302, headers={"Location": "https://example.invalid/capture"})

        http = httpx.Client(transport=httpx.MockTransport(handler), follow_redirects=False)
        try:
            client = ConnectClient(config(), http=http)
            with self.assertRaises(ConnectError) as caught:
                client.create_launch(
                    {"identity": {"sub": "student-example"}, "academic": {"institution": "EXAMPLE_UNIVERSITY"}},
                )
        finally:
            http.close()

        self.assertEqual(caught.exception.status_code, 302)
        self.assertEqual(len(requests), 2)


if __name__ == "__main__":
    unittest.main()
