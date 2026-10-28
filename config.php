<?php
/**
 * App configurations - Drop IT
 */

// Set APP_ENV=production on Render; local development defaults to true.
define('IS_DEV', getenv('APP_ENV') !== 'production');

//App branding
define('APP_NAME', 'Drop IT');
define('APP_TAGLINE', 'Your parcel, our priority.');

// Database settings come from environment variables in production.
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'dropit');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('DB_PREFIX', getenv('DB_PREFIX') ?: '');
define('DB_PATH', getenv('DB_PATH') ?: '');
    
