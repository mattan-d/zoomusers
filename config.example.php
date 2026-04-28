<?php

/**
 * העתק ל-config.php והשלם ערכים (אל תעלה את config.php ל-git).
 *
 * Secret Token — רק לאימות שבקשות ה-webhook באמת מ-Zoom. זה לא מפתח API;
 * לא ניתן לשנות רישיון למשתמש בלי אפליקציית API (ראו למטה).
 *
 * Zoom → Feature → Webhooks → Secret Token
 *
 * להקצאת רישיון אוטומטית דרך REST API צריך בנוסף Server-to-Server OAuth
 * (App type: Server-to-Server) — Create → App Credentials: Account ID, Client ID, Client Secret.
 */

declare(strict_types=1);

return [
    // אימות חתימת webhook (מספיק כדי להריץ listener + url_validation)
    'webhook_secret_token' => getenv('ZOOM_WEBHOOK_SECRET_TOKEN') ?: 'gqjG-z7PTOKGgU7DsmUsEA',

    // אופציונלי — רק אם רוצים לקרוא ל-API ולהקצות Licensed
    'account_id' => getenv('ZOOM_ACCOUNT_ID') ?: '',
    'client_id' => getenv('ZOOM_CLIENT_ID') ?: '',
    'client_secret' => getenv('ZOOM_CLIENT_SECRET') ?: '',

    // אירועים שבהם להקצות רישיון (שמות כפי שמופיעים ב-Zoom)
    'license_on_events' => [
        'user.created',
        'user.invitation_accepted',
        'user.signed_in',
    ],

    // אחרי הקצאת רישיון — הוספת המשתמש לקבוצה ב-Zoom (User Management → Groups)
    // באפליקציית S2S יש להוסיף scopes: group:read:admin, group:write:admin
    'licensed_users_group_name' => getenv('ZOOM_LICENSED_USERS_GROUP_NAME') ?: 'Users Licensed',
    // אופציונלי — אם מוגדר, מדלגים על חיפוש לפי שם (מומלץ בפרודקשן)
    'licensed_users_group_id' => getenv('ZOOM_LICENSED_USERS_GROUP_ID') ?: '',

    // לוג לדיבוג (בפרודקשן כבה או הפנה לקובץ מאובטח)
    'log_file' => __DIR__ . '/storage/webhook.log',
];
