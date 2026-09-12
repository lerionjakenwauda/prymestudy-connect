from __future__ import annotations

import base64
import hashlib
import json
import time
from typing import Any, Mapping

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec
from cryptography.hazmat.primitives.asymmetric.utils import encode_dss_signature


class WebhookVerificationError(ValueError):
    def __init__(self, message: str, code: str) -> None:
        super().__init__(message)
        self.code = code


def verify_webhook(
    raw_body: bytes | str,
    headers: Mapping[str, str],
    public_key_pem: str,
    *,
    tolerance_seconds: int = 300,
    expected_key_id: str | None = None,
    now: int | None = None,
) -> dict[str, Any]:
    normalized = {str(key).lower(): str(value).strip() for key, value in headers.items()}
    version = normalized.get("x-prymestudy-signature-version", "")
    event_id = normalized.get("x-prymestudy-event-id", "")
    timestamp_raw = normalized.get("x-prymestudy-timestamp", "")
    key_id = normalized.get("x-prymestudy-key-id", "")
    signature_raw = normalized.get("x-prymestudy-signature", "")

    if version != "v1":
        raise WebhookVerificationError("Unsupported or missing webhook signature version.", "webhook_signature_version")
    if not event_id or not key_id or not signature_raw or not timestamp_raw.isdigit():
        raise WebhookVerificationError("Webhook signature headers are incomplete.", "webhook_signature_headers")
    if expected_key_id is not None and expected_key_id != key_id:
        raise WebhookVerificationError("Webhook signing key does not match the expected key.", "webhook_key_mismatch")

    timestamp = int(timestamp_raw)
    clock = int(time.time()) if now is None else now
    if tolerance_seconds < 1 or abs(clock - timestamp) > tolerance_seconds:
        raise WebhookVerificationError("Webhook timestamp is outside the accepted freshness window.", "webhook_stale")

    body = raw_body.encode("utf-8") if isinstance(raw_body, str) else raw_body
    digest = hashlib.sha256(body).hexdigest()
    message = f"v1\n{timestamp}\n{event_id}\n{digest}".encode("utf-8")
    signature = _base64url_decode(signature_raw)
    if len(signature) != 64:
        raise WebhookVerificationError("Webhook signature has an invalid length.", "webhook_signature_invalid")

    r = int.from_bytes(signature[:32], "big")
    s = int.from_bytes(signature[32:], "big")
    der_signature = encode_dss_signature(r, s)

    try:
        public_key = serialization.load_pem_public_key(public_key_pem.encode("utf-8"))
    except (ValueError, TypeError) as exc:
        raise WebhookVerificationError("Webhook verification key is invalid.", "webhook_key_invalid") from exc
    if not isinstance(public_key, ec.EllipticCurvePublicKey) or not isinstance(public_key.curve, ec.SECP256R1):
        raise WebhookVerificationError("Webhook verification key must be P-256.", "webhook_key_invalid")

    try:
        public_key.verify(der_signature, message, ec.ECDSA(hashes.SHA256()))
    except InvalidSignature as exc:
        raise WebhookVerificationError("Webhook signature verification failed.", "webhook_signature_invalid") from exc

    try:
        event = json.loads(body)
    except (json.JSONDecodeError, UnicodeDecodeError) as exc:
        raise WebhookVerificationError("Webhook body is not valid JSON.", "webhook_invalid_json") from exc
    if not isinstance(event, dict) or event.get("id") != event_id:
        raise WebhookVerificationError("Webhook event ID does not match the signed header.", "webhook_event_id_mismatch")

    return event


def _base64url_decode(value: str) -> bytes:
    try:
        padding = "=" * ((4 - len(value) % 4) % 4)
        return base64.urlsafe_b64decode(value + padding)
    except (ValueError, base64.binascii.Error):
        return b""
