# PrymeStudy Connect PHP SDK

Server-side PHP 8.2+ SDK for PrymeStudy Connect.

## Configuration

```php
use PrymeStudy\Connect\ConnectClient;
use PrymeStudy\Connect\ConnectConfig;

$client = new ConnectClient(new ConnectConfig(
    clientId: $_ENV['PRYMESTUDY_CONNECT_CLIENT_ID'],
    privateKey: file_get_contents($_ENV['PRYMESTUDY_CONNECT_PRIVATE_KEY']),
    keyId: $_ENV['PRYMESTUDY_CONNECT_KEY_ID'],
    tokenEndpoint: $_ENV['PRYMESTUDY_CONNECT_TOKEN_ENDPOINT'],
    apiBaseUrl: $_ENV['PRYMESTUDY_CONNECT_API_BASE_URL'],
    scopes: ['connect:sso.launch', 'students:write'],
));
```

Private keys are server-side only.

## SSO launch

```php
$launch = $client->createLaunch([
    'identity' => [
        'sub' => (string) $student->connect_uuid,
        'email' => $student->email,
        'email_verified' => true,
        'matric_number' => $student->matric_number,
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
    ],
    'academic' => [
        'institution' => 'EXAMPLE_UNIVERSITY',
        'department' => 'COMPUTING',
        'programme' => 'BSC_COMPUTING',
        'level' => '300',
    ],
]);

return redirect()->away($launch['launch_url']);
```

## SIS resources

```php
$client->upsertStudents($students);
$client->upsertCourses($courses);
$client->upsertEnrollments($enrollments);
$job = $client->getSyncJob($jobId);
```

Mutating calls automatically generate an idempotency key unless one is supplied.

## Webhooks

```php
use PrymeStudy\Connect\WebhookVerifier;

$event = WebhookVerifier::verify(
    rawBody: $request->getContent(),
    headers: $request->headers->all(),
    publicKeyPem: file_get_contents('/secure/prymestudy-webhook-public.pem'),
    expectedKeyId: 'whk_live_example',
);
```

Record processed event IDs durably to prevent duplicate business processing.
