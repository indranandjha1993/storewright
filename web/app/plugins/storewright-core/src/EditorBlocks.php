<?php

declare(strict_types=1);

namespace Storewright\Core;

use WP_Block_Type_Registry;

final class EditorBlocks {

	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		$names = [];
		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( str_starts_with( $name, 'storewright/' ) && $type->editor_script_handles === [] ) {
				$names[] = $name;
			}
		}
		Asset::script( 'storewright-editor-blocks', 'assets/js/editor-blocks.js', [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render', 'wp-data', 'wp-preferences' ] );
		wp_add_inline_script( 'storewright-editor-blocks', 'window.storewrightEditorBlocks = ' . wp_json_encode( $names ) . ';', 'before' );
	}
}
