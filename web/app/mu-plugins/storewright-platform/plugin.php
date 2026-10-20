<?php

declare(strict_types=1);

namespace Storewright\Platform;

spl_autoload_register(
	static function ( string $className ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $className, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $className, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

const VERSION = '0.8.0';

Health::register();
Heartbeat::register();
Hardening::register();
Required::register();
PluginManagement::register();
LoginPath::register();
LoginThrottle::register();
Mail::register();
GatewayCredentials::register();
HttpCertificates::register();
Brand\ThemeJson::register();
Brand\Tokens::register();
Brand\Emails::register();
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'storewright brand', Brand\Command::class );
}
WhiteLabel\Wording::register();
WhiteLabel\Login::register();
WhiteLabel\AdminChrome::register();
WhiteLabel\AdminSkin::register();
WhiteLabel\PluginList::register();
WhiteLabel\Promotions::register();
WhiteLabel\OperatorMenus::register();
WhiteLabel\StoreMenu::register();
WhiteLabel\StaffRoles::register();
WhiteLabel\Journal::register();
