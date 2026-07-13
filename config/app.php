<?php
/**
 * Centralized configuration loader.
 * Reads /config/app.php and returns it.
 */
return [
    'name'     => getenv('APP_NAME') ?: 'Matrimony',
    'env'      => getenv('APP_ENV') ?: 'production',
    'debug'    => (getenv('APP_DEBUG') ?: 'false') === 'true',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'locale'   => getenv('APP_LOCALE') ?: 'en',
    'url'      => getenv('APP_URL') ?: '',  // auto-detected by url() helper when empty
];
