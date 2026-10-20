<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

final class Action {

	public static function on( string $name, callable $handler, string $capability = 'manage_woocommerce' ): void {
		add_action(
			'admin_post_storewright_' . $name,
			static function () use ( $name, $handler, $capability ): void {
				check_admin_referer( 'storewright_' . $name );
				if ( ! current_user_can( $capability ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'storewright' ), 403 );
				}
				$handler();
			}
		);
	}

	public static function url( string $name, array $args = [] ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action' => 'storewright_' . $name,
					...$args,
				],
				admin_url( 'admin-post.php' )
			),
			'storewright_' . $name
		);
	}

	public static function fields( string $name ): void {
		printf( '<input type="hidden" name="action" value="storewright_%s">', esc_attr( $name ) );
		wp_nonce_field( 'storewright_' . $name );
	}

	public static function back( string $url ): never {
		wp_safe_redirect( $url );
		exit;
	}

	public static function text( string $key ): string {
		return sanitize_text_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function lines( string $key ): string {
		return sanitize_textarea_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function query( string $key ): string {
		return sanitize_text_field( wp_unslash( (string) ( $_GET[ $key ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
