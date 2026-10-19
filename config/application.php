<?php

use Roots\WPConfig\Config;

use function Env\env;

Env\Env::$options = Env\Env::CONVERT_BOOL | Env\Env::CONVERT_NULL | Env\Env::CONVERT_INT | Env\Env::STRIP_QUOTES | Env\Env::LOCAL_FIRST;

$root_dir = dirname(__DIR__);
$webroot_dir = $root_dir . '/web';

if (file_exists($root_dir . '/.env')) {
    $repository = Dotenv\Repository\RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(Dotenv\Repository\Adapter\EnvConstAdapter::class)
        ->addAdapter(Dotenv\Repository\Adapter\PutenvAdapter::class)
        ->immutable()
        ->make();
    $dotenv = Dotenv\Dotenv::create($repository, $root_dir, ['.env'], false);
    $dotenv->load();
    $dotenv->required(['WP_HOME', 'WP_SITEURL', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']);
}

define('WP_ENV', env('WP_ENV') ?: 'production');

if (!defined('WP_ENVIRONMENT_TYPE') && in_array(WP_ENV, ['production', 'staging', 'development', 'local'], true)) {
    Config::define('WP_ENVIRONMENT_TYPE', WP_ENV);
}

Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

Config::define('CONTENT_DIR', '/app');
Config::define('WP_CONTENT_DIR', $webroot_dir . Config::get('CONTENT_DIR'));
Config::define('WP_CONTENT_URL', Config::get('WP_HOME') . Config::get('CONTENT_DIR'));

Config::define('DB_NAME', env('DB_NAME'));
Config::define('DB_USER', env('DB_USER'));
Config::define('DB_PASSWORD', env('DB_PASSWORD'));
Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $salt) {
    Config::define($salt, env($salt));
}

Config::define('WP_REDIS_HOST', env('REDIS_HOST') ?: 'cache');
Config::define('WP_CACHE_KEY_SALT', env('WP_HOME'));

Config::define('AUTOMATIC_UPDATER_DISABLED', true);
Config::define('STOREWRIGHT_LOGIN_PATH', env('LOGIN_PATH') ?: 'sign-in');
Config::define('DISALLOW_FILE_EDIT', true);
Config::define('DISALLOW_FILE_MODS', env('PLUGIN_MANAGEMENT') !== 'open');
Config::define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?? true);
Config::define('WP_POST_REVISIONS', 10);
Config::define('CONCATENATE_SCRIPTS', false);

Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', false);
Config::define('SCRIPT_DEBUG', false);
ini_set('display_errors', '0');

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

Config::apply();

if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}
