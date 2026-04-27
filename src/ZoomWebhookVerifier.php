<?php

declare(strict_types=1);

/**
 * אימות חתימת Zoom לפי השכבה הרשמית:
 * הודעה: v0:{timestamp}:{request_body}
 */
final class ZoomWebhookVerifier
{
    public function __construct(
        private readonly string $secretToken,
    ) {
    }

    public function verify(string $rawBody, ?string $timestamp, ?string $signatureHeader): bool
    {
        if ($this->secretToken === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $message = 'v0:' . ($timestamp ?? '') . ':' . $rawBody;
        $hash = hash_hmac('sha256', $message, $this->secretToken);
        $expected = 'v0=' . $hash;

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * תשובה לאימות URL בעת הגדרת ה-webhook ב-Marketplace.
     *
     * @return array{plainToken: string, encryptedToken: string}
     */
    public function urlValidationResponse(string $plainToken): array
    {
        return [
            'plainToken' => $plainToken,
            'encryptedToken' => hash_hmac('sha256', $plainToken, $this->secretToken),
        ];
    }
}
