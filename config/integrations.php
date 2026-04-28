<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Website Listing Sync
    |--------------------------------------------------------------------------
    | One-way queue-based push from Nexus to the public website.
    | Set WEBSITE_SYNC_ENABLED=false to disable syncing without removing code.
    */
    'website_sync_url'     => env('WEBSITE_SYNC_URL', ''),
    'website_sync_token'   => env('WEBSITE_SYNC_TOKEN', ''),
    'website_sync_enabled' => (bool) env('WEBSITE_SYNC_ENABLED', false),

    // Public-facing URL for the website (used for "View on HFC Premium" link).
    // Listing detail pattern: {website_public_url}/listings/{external_id}
    'website_public_url'   => env('WEBSITE_PUBLIC_URL', env('WEBSITE_SYNC_URL', '')),
];
