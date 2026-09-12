import { createHash, createPublicKey, verify as verifySignature } from "node:crypto";

export interface WebhookVerificationOptions {
  toleranceSeconds?: number;
  expectedKeyId?: string;
  now?: number;
}

export class WebhookVerificationError extends Error {
  constructor(message: string, public readonly code: string) {
    super(message);
    this.name = "WebhookVerificationError";
  }
}

export async function verifyWebhook(
  rawBody: string | Uint8Array,
  headers: Headers | Record<string, string | string[] | undefined>,
  publicKeyPem: string,
  options: WebhookVerificationOptions = {},
): Promise<Record<string, unknown>> {
  const get = headerReader(headers);
  const version = get("x-prymestudy-signature-version");
  const eventId = get("x-prymestudy-event-id");
  const timestampRaw = get("x-prymestudy-timestamp");
  const keyId = get("x-prymestudy-key-id");
  const signatureRaw = get("x-prymestudy-signature");

  if (version !== "v1") throw new WebhookVerificationError("Unsupported or missing webhook signature version.", "webhook_signature_version");
  if (!eventId || !keyId || !signatureRaw || !/^\d+$/.test(timestampRaw)) {
    throw new WebhookVerificationError("Webhook signature headers are incomplete.", "webhook_signature_headers");
  }
  if (options.expectedKeyId && options.expectedKeyId !== keyId) {
    throw new WebhookVerificationError("Webhook signing key does not match the expected key.", "webhook_key_mismatch");
  }

  const timestamp = Number(timestampRaw);
  const now = options.now ?? Math.floor(Date.now() / 1000);
  const tolerance = options.toleranceSeconds ?? 300;
  if (!Number.isSafeInteger(timestamp) || tolerance < 1 || Math.abs(now - timestamp) > tolerance) {
    throw new WebhookVerificationError("Webhook timestamp is outside the accepted freshness window.", "webhook_stale");
  }

  const body = typeof rawBody === "string" ? Buffer.from(rawBody, "utf8") : Buffer.from(rawBody);
  const digestHex = createHash("sha256").update(body).digest("hex");
  const message = Buffer.from(`v1\n${timestamp}\n${eventId}\n${digestHex}`, "utf8");
  let signature: Buffer;
  try {
    signature = Buffer.from(signatureRaw, "base64url");
  } catch {
    throw new WebhookVerificationError("Webhook signature has an invalid encoding.", "webhook_signature_invalid");
  }
  if (signature.length !== 64) throw new WebhookVerificationError("Webhook signature has an invalid length.", "webhook_signature_invalid");

  let valid = false;
  try {
    valid = verifySignature("sha256", message, {
      key: createPublicKey(publicKeyPem),
      dsaEncoding: "ieee-p1363",
    }, signature);
  } catch {
    throw new WebhookVerificationError("Webhook verification key is invalid.", "webhook_key_invalid");
  }
  if (!valid) throw new WebhookVerificationError("Webhook signature verification failed.", "webhook_signature_invalid");

  let event: unknown;
  try {
    event = JSON.parse(body.toString("utf8"));
  } catch {
    throw new WebhookVerificationError("Webhook body is not valid JSON.", "webhook_invalid_json");
  }
  if (!isRecord(event) || typeof event.id !== "string" || event.id !== eventId) {
    throw new WebhookVerificationError("Webhook event ID does not match the signed header.", "webhook_event_id_mismatch");
  }
  return event;
}

function headerReader(headers: Headers | Record<string, string | string[] | undefined>): (name: string) => string {
  if (headers instanceof Headers) return (name) => headers.get(name)?.trim() ?? "";
  const normalized = new Map<string, string>();
  for (const [name, value] of Object.entries(headers)) {
    if (value === undefined) continue;
    normalized.set(name.toLowerCase(), Array.isArray(value) ? value.join(",") : value);
  }
  return (name) => normalized.get(name.toLowerCase())?.trim() ?? "";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
