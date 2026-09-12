import assert from "node:assert/strict";
import { createHash, generateKeyPairSync, sign } from "node:crypto";
import test from "node:test";
import { verifyWebhook, WebhookVerificationError } from "../dist/index.js";

const { privateKey, publicKey } = generateKeyPairSync("ec", { namedCurve: "P-256" });
const privatePem = privateKey.export({ type: "pkcs8", format: "pem" });
const publicPem = publicKey.export({ type: "spki", format: "pem" });

function delivery(body, timestamp = 1_800_000_000) {
  const event = JSON.parse(body);
  const digest = createHash("sha256").update(body).digest("hex");
  const message = Buffer.from(`v1\n${timestamp}\n${event.id}\n${digest}`, "utf8");
  const signature = sign("sha256", message, { key: privatePem, dsaEncoding: "ieee-p1363" }).toString("base64url");
  return {
    "x-prymestudy-signature-version": "v1",
    "x-prymestudy-event-id": event.id,
    "x-prymestudy-timestamp": String(timestamp),
    "x-prymestudy-key-id": "whk_test",
    "x-prymestudy-signature": signature,
  };
}

test("verifyWebhook accepts a valid signed event", async () => {
  const body = JSON.stringify({ id: "evt_test_1", type: "student.updated", data: { ok: true } });
  const event = await verifyWebhook(body, delivery(body), publicPem, {
    now: 1_800_000_000,
    expectedKeyId: "whk_test",
  });
  assert.equal(event.type, "student.updated");
});

test("verifyWebhook rejects a tampered body", async () => {
  const body = JSON.stringify({ id: "evt_test_2", type: "course.updated", data: {} });
  await assert.rejects(
    () => verifyWebhook(`${body} `, delivery(body), publicPem, { now: 1_800_000_000 }),
    (error) => error instanceof WebhookVerificationError && error.code === "webhook_signature_invalid",
  );
});
