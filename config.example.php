<?php

/**
 * העתק ל-config.php והשלם ערכים (אל תעלה את config.php ל-git).
 *
 * Zoom Marketplace → האפליקציה שלך → Feature → Webhooks → Secret Token
 * Zoom Marketplace → האפליקציה שלך → App Credentials (Server-to-Server OAuth)
 */

declare(strict_types=1);

return [
    // אימות חתימת webhook (חובה לפרודקשן)
    'webhook_secret_token' => getenv('ZOOM_WEBHOOK_SECRET_TOKEN') ?: '',

    // Server-to-Server OAuth — להקצאת רישיון דרך REST API
    'account_id' => getenv('ZOOM_ACCOUNT_ID') ?: '',
    'client_id' => getenv('ZOOM_CLIENT_ID') ?: '',
    'client_secret' => getenv('ZOOM_CLIENT_SECRET') ?: '',

    // אירועים שבהם להקצות רישיון (שמות כפי שמופיעים ב-Zoom)
    'license_on_events' => [
        'user.created',
        'user.invitation_accepted',
    ],

    // לוג לדיבוג (בפרודקשן כבה או הפנה לקובץ מאובטח)
    'log_file' => __DIR__ . '/storage/webhook.log',
];
