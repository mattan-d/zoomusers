<?php

declare(strict_types=1);

/**
 * לקוח מינימלי ל-Server-to-Server OAuth + עדכון משתמש ל-Licensed + קבוצות.
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
        return $this->apiRequest(
            'PATCH',
            '/users/' . rawurlencode($userId),
            ['type' => 2],
        );
    }

    /**
     * מחזיר את מזהה הקבוצה לפי שם (התאמה ללא תלות ברישיות), או null אם לא נמצאה.
     * דורש scope: group:read:admin
     */
    public function findGroupIdByName(string $name): ?string
    {
        $needle = strtolower(trim($name));
        $nextPageToken = '';

        do {
            $path = '/groups?page_size=100';
            if ($nextPageToken !== '') {
                $path .= '&next_page_token=' . rawurlencode($nextPageToken);
            }

            $result = $this->apiRequest('GET', $path, null);
            if ($result['http_code'] >= 400) {
                throw new RuntimeException(
                    'List groups failed: HTTP ' . $result['http_code'] . ' ' . $result['body'],
                );
            }

            $data = json_decode($result['body'], true);
            if (! is_array($data)) {
                return null;
            }

            $groups = $data['groups'] ?? [];
            if (! is_array($groups)) {
                return null;
            }

            foreach ($groups as $g) {
                if (! is_array($g)) {
                    continue;
                }
                $gname = isset($g['name']) ? strtolower(trim((string) $g['name'])) : '';
                if ($gname === $needle && isset($g['id']) && is_string($g['id'])) {
                    return $g['id'];
                }
            }

            $nextPageToken = '';
            if (isset($data['next_page_token']) && is_string($data['next_page_token'])) {
                $nextPageToken = $data['next_page_token'];
            }
        } while ($nextPageToken !== '');

        return null;
    }

    /**
     * מוסיף משתמש לקבוצה. דורש scope: group:write:admin
     *
     * @return array{http_code: int, body: string}
     */
    public function addUserToGroup(string $groupId, string $userId): array
    {
        return $this->apiRequest(
            'POST',
            '/groups/' . rawurlencode($groupId) . '/members',
            ['members' => [['id' => $userId]]],
        );
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @return array{http_code: int, body: string}
     */
    private function apiRequest(string $method, string $path, ?array $jsonBody): array
    {
        $token = $this->getAccessToken();
        $url = 'https://api.zoom.us/v2' . $path;

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ];

        if ($method === 'GET') {
            $opts[CURLOPT_HTTPGET] = true;
        } else {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody ?? [], JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $opts);

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
