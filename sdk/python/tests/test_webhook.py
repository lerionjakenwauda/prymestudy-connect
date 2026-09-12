import base64
import hashlib
import json
import unittest

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec
from cryptography.hazmat.primitives.asymmetric.utils import decode_dss_signature

from prymestudy_connect import WebhookVerificationError, verify_webhook


class WebhookVerifierTest(unittest.TestCase):
    def setUp(self) -> None:
        self.private_key = ec.generate_private_key(ec.SECP256R1())
        self.public_pem = self.private_key.public_key().public_bytes(
            serialization.Encoding.PEM,
            serialization.PublicFormat.SubjectPublicKeyInfo,
        ).decode("utf-8")

    def headers(self, body: bytes, timestamp: int = 1_800_000_000) -> dict[str, str]:
        event = json.loads(body)
        digest = hashlib.sha256(body).hexdigest()
        message = f"v1\n{timestamp}\n{event['id']}\n{digest}".encode()
        der = self.private_key.sign(message, ec.ECDSA(hashes.SHA256()))
        r, s = decode_dss_signature(der)
        raw = r.to_bytes(32, "big") + s.to_bytes(32, "big")
        signature = base64.urlsafe_b64encode(raw).rstrip(b"=").decode()
        return {
            "X-PrymeStudy-Signature-Version": "v1",
            "X-PrymeStudy-Event-Id": event["id"],
            "X-PrymeStudy-Timestamp": str(timestamp),
            "X-PrymeStudy-Key-Id": "whk_test",
            "X-PrymeStudy-Signature": signature,
        }

    def test_valid_delivery(self) -> None:
        body = json.dumps({"id": "evt_test_1", "type": "student.updated", "data": {"ok": True}}, separators=(",", ":")).encode()
        event = verify_webhook(
            body,
            self.headers(body),
            self.public_pem,
            now=1_800_000_000,
            expected_key_id="whk_test",
        )
        self.assertEqual("student.updated", event["type"])

    def test_tamper_rejected(self) -> None:
        body = json.dumps({"id": "evt_test_2", "type": "course.updated", "data": {}}, separators=(",", ":")).encode()
        with self.assertRaises(WebhookVerificationError):
            verify_webhook(body + b" ", self.headers(body), self.public_pem, now=1_800_000_000)


if __name__ == "__main__":
    unittest.main()
