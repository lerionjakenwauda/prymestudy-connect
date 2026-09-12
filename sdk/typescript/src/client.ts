import { importPKCS8, SignJWT } from "jose";

export type ConnectAlgorithm = "ES256" | "EdDSA";

export interface ConnectConfig {
  clientId: string;
  privateKeyPem: string;
  keyId: string;
  tokenEndpoint: string;
  apiBaseUrl: string;
  scopes?: string[];
  algorithm?: ConnectAlgorithm;
  assertionTtlSeconds?: number;
  requestTimeoutMs?: number;
}

export interface LaunchResponse {
  launch_id: string;
  launch_url: string;
  expires_at: string;
}

export class ConnectError extends Error {
  constructor(
    message: string,
    public readonly code = "connect_error",
    public readonly status = 0,
    public readonly requestId?: string,
    public readonly details: unknown[] = [],
  ) {
    super(message);
    this.name = "ConnectError";
  }
}

export class ConnectClient {
  readonly #config: Required<Pick<ConnectConfig, "algorithm" | "assertionTtlSeconds" | "requestTimeoutMs">> & ConnectConfig;
  #signingKey?: CryptoKey;
  #accessToken?: string;
  #accessTokenExpiresAt = 0;

  constructor(config: ConnectConfig) {
    validateConfig(config);

    this.#config = {
      ...config,
      scopes: config.scopes ?? [],
      algorithm: config.algorithm ?? "ES256",
      assertionTtlSeconds: config.assertionTtlSeconds ?? 120,
      requestTimeoutMs: config.requestTimeoutMs ?? 15_000,
    };
  }

  async createLaunch(payload: Record<string, unknown>, idempotencyKey = randomId("idem")): Promise<LaunchResponse> {
    return this.request<LaunchResponse>("POST", "/connect/v1/launches", payload, idempotencyKey);
  }

  async request<T extends Record<string, unknown>>(
    method: string,
    path: string,
    body?: Record<string, unknown>,
    idempotencyKey?: string,
  ): Promise<T> {
    const token = await this.#getAccessToken();
    const url = new URL(path.replace(/^\//, ""), ensureTrailingSlash(this.#config.apiBaseUrl));

    const headers = new Headers({
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      "User-Agent": "prymestudy-connect-node/1",
    });

    if (body !== undefined) headers.set("Content-Type", "application/json");
    if (idempotencyKey) headers.set("Idempotency-Key", idempotencyKey);

    let response: Response;
    try {
      response = await fetch(url, {
        method: method.toUpperCase(),
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: AbortSignal.timeout(this.#config.requestTimeoutMs),
      });
    } catch (error) {
      throw new ConnectError(
        error instanceof Error ? error.message : "Unable to reach PrymeStudy Connect.",
        "transport_error",
      );
    }

    return decodeResponse<T>(response);
  }

  clearAccessToken(): void {
    this.#accessToken = undefined;
    this.#accessTokenExpiresAt = 0;
  }

  async #getAccessToken(): Promise<string> {
    const now = Math.floor(Date.now() / 1000);
    if (this.#accessToken && this.#accessTokenExpiresAt > now + 30) return this.#accessToken;

    const assertion = await this.#clientAssertion(now);
    const params = new URLSearchParams({
      grant_type: "client_credentials",
      client_id: this.#config.clientId,
      client_assertion_type: "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
      client_assertion: assertion,
    });

    if ((this.#config.scopes?.length ?? 0) > 0) {
      params.set("scope", this.#config.scopes!.join(" "));
    }

    let response: Response;
    try {
      response = await fetch(this.#config.tokenEndpoint, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded",
          "User-Agent": "prymestudy-connect-node/1",
        },
        body: params,
        signal: AbortSignal.timeout(this.#config.requestTimeoutMs),
      });
    } catch (error) {
      throw new ConnectError(
        error instanceof Error ? error.message : "Unable to reach the PrymeStudy authorization server.",
        "token_transport_error",
      );
    }

    const data = await decodeResponse<Record<string, unknown>>(response);
    const token = data.access_token;
    const expiresIn = Number(data.expires_in ?? 300);

    if (typeof token !== "string" || token.length === 0) {
      throw new ConnectError("Token response did not contain a valid access_token.", "invalid_token_response", response.status);
    }

    this.#accessToken = token;
    this.#accessTokenExpiresAt = now + (Number.isFinite(expiresIn) && expiresIn > 0 ? expiresIn : 300);
    return token;
  }

  async #clientAssertion(now: number): Promise<string> {
    if (!this.#signingKey) {
      this.#signingKey = await importPKCS8(this.#config.privateKeyPem, this.#config.algorithm);
    }

    return new SignJWT({})
      .setProtectedHeader({ alg: this.#config.algorithm, kid: this.#config.keyId, typ: "JWT" })
      .setIssuer(this.#config.clientId)
      .setSubject(this.#config.clientId)
      .setAudience(this.#config.tokenEndpoint)
      .setIssuedAt(now)
      .setExpirationTime(now + this.#config.assertionTtlSeconds)
      .setJti(randomId("jti"))
      .sign(this.#signingKey);
  }
}

function validateConfig(config: ConnectConfig): void {
  if (!config.clientId) throw new TypeError("clientId is required.");
  if (!config.privateKeyPem) throw new TypeError("privateKeyPem is required.");
  if (!config.keyId) throw new TypeError("keyId is required.");

  assertAbsoluteUrl(config.tokenEndpoint, "tokenEndpoint");
  assertAbsoluteUrl(config.apiBaseUrl, "apiBaseUrl");

  const ttl = config.assertionTtlSeconds ?? 120;
  if (ttl < 30 || ttl > 300) throw new RangeError("assertionTtlSeconds must be between 30 and 300 seconds.");

  const timeout = config.requestTimeoutMs ?? 15_000;
  if (timeout < 1_000 || timeout > 120_000) throw new RangeError("requestTimeoutMs must be between 1000 and 120000 milliseconds.");
}

function assertAbsoluteUrl(value: string, name: string): void {
  try {
    const url = new URL(value);
    if (!url.protocol.startsWith("http")) throw new Error();
  } catch {
    throw new TypeError(`${name} must be an absolute HTTP(S) URL.`);
  }
}

function ensureTrailingSlash(value: string): string {
  return value.endsWith("/") ? value : `${value}/`;
}

function randomId(prefix: string): string {
  return `${prefix}_${crypto.randomUUID()}`;
}

async function decodeResponse<T>(response: Response): Promise<T> {
  let data: unknown = {};
  const text = await response.text();

  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = {};
    }
  }

  if (response.ok) return data as T;

  const envelope = isRecord(data) && isRecord(data.error) ? data.error : {};
  const message = typeof envelope.message === "string"
    ? envelope.message
    : `PrymeStudy Connect returned HTTP ${response.status}.`;
  const code = typeof envelope.code === "string" ? envelope.code : "connect_error";
  const requestId = typeof envelope.request_id === "string"
    ? envelope.request_id
    : response.headers.get("x-request-id") ?? undefined;
  const details = Array.isArray(envelope.details) ? envelope.details : [];

  throw new ConnectError(message, code, response.status, requestId, details);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
