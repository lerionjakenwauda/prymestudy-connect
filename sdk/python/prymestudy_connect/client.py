from __future__ import annotations

from dataclasses import dataclass, field
from time import time
from typing import Any, Mapping, Sequence
from urllib.parse import quote, urljoin
from uuid import uuid4

import httpx
import jwt


class ConnectError(RuntimeError):
    def __init__(
        self,
        message: str,
        *,
        code: str = "connect_error",
        status_code: int = 0,
        request_id: str | None = None,
        details: list[Any] | None = None,
    ) -> None:
        super().__init__(message)
        self.code = code
        self.status_code = status_code
        self.request_id = request_id
        self.details = details or []


@dataclass(frozen=True, slots=True)
class ConnectConfig:
    client_id: str
    private_key_pem: str
    key_id: str
    token_endpoint: str
    api_base_url: str
    scopes: tuple[str, ...] = field(default_factory=tuple)
    algorithm: str = "ES256"
    assertion_ttl_seconds: int = 120
    request_timeout_seconds: float = 15.0

    def __post_init__(self) -> None:
        if not self.client_id:
            raise ValueError("client_id is required")
        if not self.private_key_pem:
            raise ValueError("private_key_pem is required")
        if not self.key_id:
            raise ValueError("key_id is required")
        if self.algorithm != "ES256":
            raise ValueError("PrymeStudy Connect v1 supports ES256 client assertions")
        if not self.token_endpoint.startswith(("https://", "http://")):
            raise ValueError("token_endpoint must be an absolute HTTP(S) URL")
        if not self.api_base_url.startswith(("https://", "http://")):
            raise ValueError("api_base_url must be an absolute HTTP(S) URL")
        if not 30 <= self.assertion_ttl_seconds <= 300:
            raise ValueError("assertion_ttl_seconds must be between 30 and 300")
        if not 1 <= self.request_timeout_seconds <= 120:
            raise ValueError("request_timeout_seconds must be between 1 and 120")
        if any(not isinstance(scope, str) or not scope.strip() for scope in self.scopes):
            raise ValueError("scopes must contain non-empty strings")


class ConnectClient:
    def __init__(self, config: ConnectConfig, http: httpx.Client | None = None) -> None:
        self.config = config
        self._http = http or httpx.Client(timeout=config.request_timeout_seconds)
        self._owns_http = http is None
        self._access_token: str | None = None
        self._access_token_expires_at = 0

    def __enter__(self) -> "ConnectClient":
        return self

    def __exit__(self, exc_type: object, exc: object, tb: object) -> None:
        self.close()

    def close(self) -> None:
        if self._owns_http:
            self._http.close()

    def clear_access_token(self) -> None:
        self._access_token = None
        self._access_token_expires_at = 0

    def create_launch(self, payload: Mapping[str, Any], *, idempotency_key: str | None = None) -> dict[str, Any]:
        return self.request(
            "POST",
            "/connect/v1/launches",
            json=dict(payload),
            idempotency_key=idempotency_key or _random_id("idem"),
        )

    def upsert_students(self, items: Sequence[Mapping[str, Any]], *, idempotency_key: str | None = None) -> dict[str, Any]:
        return self.request(
            "POST",
            "/connect/v1/students:upsert",
            json={"items": [dict(item) for item in items]},
            idempotency_key=idempotency_key or _random_id("idem"),
        )

    def upsert_courses(self, items: Sequence[Mapping[str, Any]], *, idempotency_key: str | None = None) -> dict[str, Any]:
        return self.request(
            "POST",
            "/connect/v1/courses:upsert",
            json={"items": [dict(item) for item in items]},
            idempotency_key=idempotency_key or _random_id("idem"),
        )

    def upsert_enrollments(self, items: Sequence[Mapping[str, Any]], *, idempotency_key: str | None = None) -> dict[str, Any]:
        return self.request(
            "POST",
            "/connect/v1/enrollments:upsert",
            json={"items": [dict(item) for item in items]},
            idempotency_key=idempotency_key or _random_id("idem"),
        )

    def get_sync_job(self, job_id: str) -> dict[str, Any]:
        if not job_id or "/" in job_id:
            raise ValueError("job_id must be a non-empty opaque identifier")
        return self.request("GET", f"/connect/v1/sync-jobs/{quote(job_id, safe='')}")

    def request(
        self,
        method: str,
        path: str,
        *,
        json: Mapping[str, Any] | None = None,
        idempotency_key: str | None = None,
    ) -> dict[str, Any]:
        if not path.startswith("/"):
            raise ValueError("Connect API paths must start with /")

        headers = {
            "Accept": "application/json",
            "Authorization": f"Bearer {self._get_access_token()}",
            "User-Agent": "prymestudy-connect-python/1.0",
        }
        if idempotency_key:
            headers["Idempotency-Key"] = idempotency_key

        url = urljoin(self.config.api_base_url.rstrip("/") + "/", path.lstrip("/"))
        kwargs: dict[str, Any] = {"headers": headers}
        if json is not None:
            kwargs["json"] = dict(json)

        try:
            response = self._http.request(method.upper(), url, **kwargs)
        except httpx.HTTPError as exc:
            raise ConnectError(str(exc), code="transport_error") from exc

        return _decode_response(response)

    def _get_access_token(self) -> str:
        now = int(time())
        if self._access_token and self._access_token_expires_at > now + 30:
            return self._access_token

        assertion = self._client_assertion(now)
        form: dict[str, str] = {
            "grant_type": "client_credentials",
            "client_id": self.config.client_id,
            "client_assertion_type": "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
            "client_assertion": assertion,
        }
        if self.config.scopes:
            form["scope"] = " ".join(self.config.scopes)

        try:
            response = self._http.post(
                self.config.token_endpoint,
                headers={"Accept": "application/json", "User-Agent": "prymestudy-connect-python/1.0"},
                data=form,
            )
        except httpx.HTTPError as exc:
            raise ConnectError(str(exc), code="token_transport_error") from exc

        data = _decode_response(response)
        token = data.get("access_token")
        expires_in_raw = data.get("expires_in", 300)
        if not isinstance(token, str) or not token:
            raise ConnectError(
                "Token response did not contain a valid access_token.",
                code="invalid_token_response",
                status_code=response.status_code,
            )

        try:
            expires_in = max(1, int(expires_in_raw))
        except (TypeError, ValueError):
            expires_in = 300

        self._access_token = token
        self._access_token_expires_at = now + expires_in
        return token

    def _client_assertion(self, now: int) -> str:
        return jwt.encode(
            {
                "iss": self.config.client_id,
                "sub": self.config.client_id,
                "aud": self.config.token_endpoint,
                "iat": now,
                "exp": now + self.config.assertion_ttl_seconds,
                "jti": _random_id("jti"),
            },
            self.config.private_key_pem,
            algorithm="ES256",
            headers={"kid": self.config.key_id, "typ": "JWT"},
        )


def _decode_response(response: httpx.Response) -> dict[str, Any]:
    try:
        data = response.json() if response.content else {}
    except ValueError:
        data = {}

    if 200 <= response.status_code < 300:
        return data if isinstance(data, dict) else {}

    envelope = data.get("error", {}) if isinstance(data, dict) else {}
    envelope = envelope if isinstance(envelope, dict) else {}
    message = envelope.get("message")
    code = envelope.get("code")
    request_id = envelope.get("request_id") or response.headers.get("x-request-id")
    details = envelope.get("details")

    raise ConnectError(
        message if isinstance(message, str) else f"PrymeStudy Connect returned HTTP {response.status_code}.",
        code=code if isinstance(code, str) else "connect_error",
        status_code=response.status_code,
        request_id=request_id if isinstance(request_id, str) else None,
        details=details if isinstance(details, list) else [],
    )


def _random_id(prefix: str) -> str:
    return f"{prefix}_{uuid4()}"
