<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use PrymeStudy\Connect\ConnectClient;
use PrymeStudy\Connect\ConnectConfig;

final class ConnectController
{
    public function __invoke(ConnectClient $connect): RedirectResponse
    {
        $student = auth()->user();

        $launch = $connect->createLaunch([
            'identity' => [
                'sub' => (string) $student->connect_uuid,
                'email' => $student->email,
                'email_verified' => (bool) $student->email_verified_at,
                'matric_number' => $student->matric_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
            ],
            'academic' => [
                'institution' => 'EXAMPLE_UNIVERSITY',
                'department' => 'COMPUTING',
                'programme' => 'BSC_COMPUTING',
                'level' => (string) $student->level,
            ],
        ]);

        return redirect()->away($launch['launch_url']);
    }

    public static function clientFromEnvironment(): ConnectClient
    {
        return new ConnectClient(new ConnectConfig(
            clientId: (string) env('PRYMESTUDY_CONNECT_CLIENT_ID'),
            privateKey: file_get_contents((string) env('PRYMESTUDY_CONNECT_PRIVATE_KEY')),
            keyId: (string) env('PRYMESTUDY_CONNECT_KEY_ID'),
            tokenEndpoint: (string) env('PRYMESTUDY_CONNECT_TOKEN_ENDPOINT'),
            apiBaseUrl: (string) env('PRYMESTUDY_CONNECT_API_BASE_URL'),
            scopes: ['connect:sso.launch'],
        ));
    }
}
