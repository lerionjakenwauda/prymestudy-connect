import { readFileSync } from "node:fs";
import http from "node:http";
import { ConnectClient } from "../../sdk/typescript/dist/index.js";

const connect = new ConnectClient({
  clientId: process.env.PRYMESTUDY_CONNECT_CLIENT_ID,
  privateKeyPem: readFileSync(process.env.PRYMESTUDY_CONNECT_PRIVATE_KEY, "utf8"),
  keyId: process.env.PRYMESTUDY_CONNECT_KEY_ID,
  tokenEndpoint: process.env.PRYMESTUDY_CONNECT_TOKEN_ENDPOINT,
  apiBaseUrl: process.env.PRYMESTUDY_CONNECT_API_BASE_URL,
  scopes: ["connect:sso.launch"],
});

http.createServer(async (_request, response) => {
  try {
    const launch = await connect.createLaunch({
      identity: {
        sub: "student-immutable-example",
        email: "student@example.edu",
        email_verified: true,
      },
      academic: {
        institution: "EXAMPLE_UNIVERSITY",
        department: "COMPUTING",
        programme: "BSC_COMPUTING",
        level: "300",
      },
    });

    response.writeHead(302, { Location: launch.launch_url });
    response.end();
  } catch (error) {
    response.writeHead(502, { "Content-Type": "application/json" });
    response.end(JSON.stringify({ error: "connect_launch_failed" }));
  }
}).listen(3000);
