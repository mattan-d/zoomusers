<?php

declare(strict_types=1);

/**
 * לקוח מינימלי ל-Server-to-Server OAuth + עדכון משתמש ל-Licensed.
 */
final class ZoomApiClient
{
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly string $accountId,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function assignLicensedUser(string $userId): array
    {
        $token = $this->getAccessToken();

        $url = 'https://api.zoom.us/v2/users/' . rawurlencode($userId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['type' => 2], JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException('cURL: ' . $curlErr);
        }

        return [
            'http_code' => $httpCode,
            'body' => $responseBody !== false ? $responseBody : '',
        ];
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . rawurlencode($this->accountId);

        $ch = curl_init($url);
        $basic = base64_encode($this->clientId . ':' . $this->clientSecret);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $basic,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException('OAuth cURL: ' . $curlErr);
        }

        $data = json_decode($responseBody ?: 'null', true);
        if (! is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('OAuth token response invalid: ' . ($responseBody ?? ''));
        }

        $this->accessToken = $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $this->tokenExpiresAt = time() + $expiresIn;

        return $this->accessToken;
    }
}
