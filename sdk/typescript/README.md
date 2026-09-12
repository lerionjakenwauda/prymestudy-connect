# PrymeStudy Connect TypeScript / Node.js SDK

Server-side Node.js SDK for PrymeStudy Connect. Requires Node.js 20+.

```ts
import { ConnectClient } from "@prymestudy/connect";
import { readFileSync } from "node:fs";

const connect = new ConnectClient({
  clientId: process.env.PRYMESTUDY_CONNECT_CLIENT_ID!,
  privateKeyPem: readFileSync(process.env.PRYMESTUDY_CONNECT_PRIVATE_KEY!, "utf8"),
  keyId: process.env.PRYMESTUDY_CONNECT_KEY_ID!,
  tokenEndpoint: process.env.PRYMESTUDY_CONNECT_TOKEN_ENDPOINT!,
  apiBaseUrl: process.env.PRYMESTUDY_CONNECT_API_BASE_URL!,
  scopes: ["connect:sso.launch", "students:write"],
});
```

## SSO

```ts
const launch = await connect.createLaunch({
  identity: {
    sub: student.connectId,
    email: student.email,
    email_verified: true,
  },
  academic: {
    institution: "EXAMPLE_UNIVERSITY",
    department: "COMPUTING",
    programme: "BSC_COMPUTING",
    level: "300",
  },
});
```

Redirect the browser to `launch.launch_url`.

## SIS / LMS

```ts
await connect.upsertStudents(students);
await connect.upsertCourses(courses);
await connect.upsertEnrollments(enrollments);
const job = await connect.getSyncJob(jobId);
```

## Webhooks

```ts
import { verifyWebhook } from "@prymestudy/connect";

const event = await verifyWebhook(rawBody, request.headers, publicKeyPem, {
  expectedKeyId: "whk_live_example",
});
```

Keep Connect private keys on the server. Never include them in React/Next.js client bundles, browser JavaScript or mobile applications.
