import { createPrivateKey } from "node:crypto";
import { importPKCS8, SignJWT } from "jose";

export type ConnectAlgorithm = "ES256";

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

interface ResolvedConnectConfig extends ConnectConfig {
  scopes: string[];
  algorithm: ConnectAlgorithm;
  assertionTtlSeconds: number;
  requestTimeoutMs: number;
}

export interface LaunchResponse {
  launch_id: string;
  launch_url: string;
  expires_at: string;
}

export interface UpsertResponse {
  job_id?: string;
  accepted: number;
  rejected: number;
  errors?: unknown[];
}

export class ConnectError extends Error {
  constructor(
    message: string,
    public readonly code = "connect_error",
    public readonly status = 0,
    public readonly requestId: string | undefined = undefined,
    public readonly details: unknown[] = [],
  ) {
    super(message);
    this.name = "ConnectError";
  }
}

export class ConnectClient {
  readonly #config: ResolvedConnectConfig;
  #signingKey: Awaited<ReturnType<typeof importPKCS8>> | null = null;
  #accessToken: string | null = null;
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

  async upsertStudents(items: Record<string, unknown>[], idempotencyKey = randomId("idem")): Promise<UpsertResponse> {
    return this.request<UpsertResponse>("POST", "/connect/v1/students:upsert", { items }, idempotencyKey);
  }

  async upsertCourses(items: Record<string, unknown>[], idempotencyKey = randomId("idem")): Promise<UpsertResponse> {
    return this.request<UpsertResponse>("POST", "/connect/v1/courses:upsert", { items }, idempotencyKey);
  }

  async upsertEnrollments(items: Record<string, unknown>[], idempotencyKey = randomId("idem")): Promise<UpsertResponse> {
    return this.request<UpsertResponse>("POST", "/connect/v1/enrollments:upsert", { items }, idempotencyKey);
  }

  async getSyncJob(jobId: string): Promise<Record<string, unknown>> {
    if (!jobId || jobId.includes("/")) throw new TypeError("jobId must be a non-empty opaque identifier.");
    return this.request<Record<string, unknown>>("GET", `/connect/v1/sync-jobs/${encodeURIComponent(jobId)}`);
  }

  async request<T = Record<string, unknown>>(
    method: string,
    path: string,
    body?: Record<string, unknown>,
    idempotencyKey?: string,
  ): Promise<T> {
    if (!path.startsWith("/")) throw new TypeError("Connect API paths must start with /.");

    const token = await this.#getAccessToken();
    const url = new URL(path.replace(/^\/+/, ""), ensureTrailingSlash(this.#config.apiBaseUrl));
    const headers = new Headers({
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      "User-Agent": "prymestudy-connect-node/1.0",
    });
    if (body !== undefined) headers.set("Content-Type", "application/json");
    if (idempotencyKey) headers.set("Idempotency-Key", idempotencyKey);

    const init: RequestInit = {
      method: method.toUpperCase(),
      headers,
      redirect: "error",
      signal: AbortSignal.timeout(this.#config.requestTimeoutMs),
    };
    if (body !== undefined) init.body = JSON.stringify(body);

    let response: Response;
    try {
      response = await fetch(url, init);
    } catch (error) {
      throw new ConnectError(error instanceof Error ? error.message : "Unable to reach PrymeStudy Connect.", "transport_error");
    }

    return decodeResponse<T>(response);
  }

  clearAccessToken(): void {
    this.#accessToken = null;
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
    if (this.#config.scopes.length > 0) params.set("scope", this.#config.scopes.join(" "));

    let response: Response;
    try {
      response = await fetch(this.#config.tokenEndpoint, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded",
          "User-Agent": "prymestudy-connect-node/1.0",
        },
        body: params,
        redirect: "error",
        signal: AbortSignal.timeout(this.#config.requestTimeoutMs),
      });
    } catch (error) {
      throw new ConnectError(error instanceof Error ? error.message : "Unable to reach the PrymeStudy authorization server.", "token_transport_error");
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
  if (!config.clientId || config.clientId.length > 120) throw new TypeError("clientId is required and must not exceed 120 characters.");
  if (!config.privateKeyPem) throw new TypeError("privateKeyPem is required.");
  if (!config.keyId || config.keyId.length > 120 || !/^[A-Za-z0-9._-]+$/.test(config.keyId)) {
    throw new TypeError("keyId must contain only letters, numbers, dot, underscore or hyphen and must not exceed 120 characters.");
  }
  if ((config.algorithm ?? "ES256") !== "ES256") throw new TypeError("PrymeStudy Connect v1 supports ES256 client assertions.");
  assertSecureEndpoint(config.tokenEndpoint, "tokenEndpoint", false);
  assertSecureEndpoint(config.apiBaseUrl, "apiBaseUrl", true);
  assertP256PrivateKey(config.privateKeyPem);

  const ttl = config.assertionTtlSeconds ?? 120;
  if (ttl < 30 || ttl > 300) throw new RangeError("assertionTtlSeconds must be between 30 and 300 seconds.");
  const timeout = config.requestTimeoutMs ?? 15_000;
  if (timeout < 1_000 || timeout > 120_000) throw new RangeError("requestTimeoutMs must be between 1000 and 120000 milliseconds.");
  for (const scope of config.scopes ?? []) if (!scope.trim()) throw new TypeError("scopes must contain non-empty strings.");
}

function assertP256PrivateKey(privateKeyPem: string): void {
  try {
    const key = createPrivateKey(privateKeyPem);
    const curve = key.asymmetricKeyDetails?.namedCurve;
    if (key.asymmetricKeyType !== "ec" || (curve !== "prime256v1" && curve !== "P-256")) throw new Error();
  } catch {
    throw new TypeError("privateKeyPem must contain an EC P-256 private key.");
  }
}

function assertSecureEndpoint(value: string, name: string, baseUrl: boolean): void {
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new TypeError(`${name} must be an absolute URL.`);
  }

  if (url.username || url.password || url.search || url.hash) {
    throw new TypeError(`${name} must not contain credentials, a query string or a fragment.`);
  }

  const loopback = isLoopbackHost(url.hostname);
  if (url.protocol !== "https:" && !(url.protocol === "http:" && loopback)) {
    throw new TypeError(`${name} must use HTTPS. HTTP is allowed only for loopback development hosts.`);
  }

  if (baseUrl && url.pathname !== "/") {
    throw new TypeError(`${name} must be an origin/base URL without a path.`);
  }
}

function isLoopbackHost(hostname: string): boolean {
  const host = hostname.toLowerCase().replace(/^\[|\]$/g, "");
  return host === "localhost" || host === "127.0.0.1" || host === "::1" || host.endsWith(".localhost");
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
    try { data = JSON.parse(text); } catch { data = {}; }
  }
  if (response.ok) return data as T;

  const envelope = isRecord(data) && isRecord(data.error) ? data.error : {};
  const message = typeof envelope.message === "string" ? envelope.message : `PrymeStudy Connect returned HTTP ${response.status}.`;
  const code = typeof envelope.code === "string" ? envelope.code : "connect_error";
  const requestId = typeof envelope.request_id === "string" ? envelope.request_id : response.headers.get("x-request-id") ?? undefined;
  const details = Array.isArray(envelope.details) ? envelope.details : [];
  throw new ConnectError(message, code, response.status, requestId, details);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
