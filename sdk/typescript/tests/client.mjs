import assert from "node:assert/strict";
import { generateKeyPairSync } from "node:crypto";
import test from "node:test";

import { ConnectClient } from "../dist/index.js";

function privateKeyPem() {
  return generateKeyPairSync("ec", {
    namedCurve: "P-256",
    privateKeyEncoding: { type: "pkcs8", format: "pem" },
    publicKeyEncoding: { type: "spki", format: "pem" },
  }).privateKey;
}

function config(overrides = {}) {
  return {
    clientId: "ps_test_example",
    privateKeyPem: privateKeyPem(),
    keyId: "key_test",
    tokenEndpoint: "https://auth.prymestudy.com/connect/v1/oauth/token",
    apiBaseUrl: "https://prymestudy.com",
    scopes: ["connect:sso.launch"],
    ...overrides,
  };
}

test("rejects remote plaintext HTTP but permits loopback development", () => {
  assert.throws(
    () => new ConnectClient(config({ tokenEndpoint: "http://identity.example.edu/connect/v1/oauth/token" })),
    /must use HTTPS/,
  );

  assert.doesNotThrow(() => new ConnectClient(config({
    tokenEndpoint: "http://127.0.0.1:8000/connect/v1/oauth/token",
    apiBaseUrl: "http://localhost:8000",
  })));
});

test("uses central Auth audience, caches token and refuses redirects", async () => {
  const originalFetch = globalThis.fetch;
  const calls = [];

  globalThis.fetch = async (input, init = {}) => {
    const url = String(input);
    calls.push({ url, init });

    if (url === "https://auth.prymestudy.com/connect/v1/oauth/token") {
      return new Response(JSON.stringify({
        access_token: "pct_test_example_access_token",
        token_type: "Bearer",
        expires_in: 300,
        scope: "connect:sso.launch",
      }), { status: 200, headers: { "Content-Type": "application/json" } });
    }

    return new Response(JSON.stringify({
      launch_id: `launch-${calls.length}`,
      launch_url: "https://auth.prymestudy.com/connect/launch/psl_example",
      expires_at: "2026-09-13T16:00:00Z",
    }), { status: 201, headers: { "Content-Type": "application/json" } });
  };

  try {
    const client = new ConnectClient(config());
    await client.createLaunch({
      identity: { sub: "student-example" },
      academic: { institution: "EXAMPLE_UNIVERSITY" },
    }, "idem_first_123456");
    await client.createLaunch({
      identity: { sub: "student-example" },
      academic: { institution: "EXAMPLE_UNIVERSITY" },
    }, "idem_second_123456");

    assert.equal(calls.length, 3, "second API call should reuse the cached access token");
    assert.equal(calls[0].init.redirect, "error");

    const tokenForm = calls[0].init.body;
    assert.ok(tokenForm instanceof URLSearchParams);
    const assertion = tokenForm.get("client_assertion");
    assert.ok(assertion);
    const parts = assertion.split(".");
    assert.equal(parts.length, 3);
    const claims = JSON.parse(Buffer.from(parts[1], "base64url").toString("utf8"));
    assert.equal(claims.aud, "https://auth.prymestudy.com/connect/v1/oauth/token");
    assert.equal(claims.iss, "ps_test_example");
    assert.ok(claims.jti);

    assert.equal(calls[1].url, "https://prymestudy.com/connect/v1/launches");
    assert.equal(calls[1].init.redirect, "error");
    const headers = new Headers(calls[1].init.headers);
    assert.equal(headers.get("authorization"), "Bearer pct_test_example_access_token");
    assert.equal(headers.get("idempotency-key"), "idem_first_123456");
  } finally {
    globalThis.fetch = originalFetch;
  }
});
