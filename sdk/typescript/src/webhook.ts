import { importSPKI } from "jose";

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

  const bytes = typeof rawBody === "string" ? new TextEncoder().encode(rawBody) : rawBody;
  const digest = await crypto.subtle.digest("SHA-256", bytes);
  const digestHex = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
  const message = new TextEncoder().encode(`v1\n${timestamp}\n${eventId}\n${digestHex}`);
  const signature = base64UrlDecode(signatureRaw);
  if (signature.length !== 64) throw new WebhookVerificationError("Webhook signature has an invalid length.", "webhook_signature_invalid");

  const key = await importSPKI(publicKeyPem, "ES256");
  const valid = await crypto.subtle.verify({ name: "ECDSA", hash: "SHA-256" }, key, signature, message);
  if (!valid) throw new WebhookVerificationError("Webhook signature verification failed.", "webhook_signature_invalid");

  let event: unknown;
  try {
    event = JSON.parse(new TextDecoder().decode(bytes));
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

function base64UrlDecode(value: string): Uint8Array {
  const normalized = value.replace(/-/g, "+").replace(/_/g, "/");
  const padded = normalized + "=".repeat((4 - (normalized.length % 4)) % 4);
  try {
    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
  } catch {
    return new Uint8Array();
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
