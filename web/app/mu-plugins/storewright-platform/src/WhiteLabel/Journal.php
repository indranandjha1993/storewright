<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class Journal {

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'relabel' ], 998 );
		add_filter( 'post_type_labels_post', [ self::class, 'labels' ] );
	}

	public static function relabel(): void {
		global $menu, $submenu;
		foreach ( (array) $menu as $position => $item ) {
			if ( ( $item[2] ?? '' ) === 'edit.php' ) {
				$menu[ $position ][0] = __( 'Journal', 'storewright' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$menu[ $position ][6] = 'dashicons-edit-page'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
		if ( isset( $submenu['edit.php'] ) ) {
			foreach ( $submenu['edit.php'] as $index => $item ) {
				$submenu['edit.php'][ $index ][0] = match ( $item[2] ?? '' ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					'edit.php'          => __( 'All entries', 'storewright' ),
					'post-new.php'      => __( 'New entry', 'storewright' ),
					default             => $item[0],
				};
			}
		}
	}

	public static function labels( object $labels ): object {
		$labels->name               = __( 'Journal', 'storewright' );
		$labels->singular_name      = __( 'Entry', 'storewright' );
		$labels->menu_name          = __( 'Journal', 'storewright' );
		$labels->add_new            = __( 'New entry', 'storewright' );
		$labels->add_new_item       = __( 'New journal entry', 'storewright' );
		$labels->edit_item          = __( 'Edit entry', 'storewright' );
		$labels->new_item           = __( 'New entry', 'storewright' );
		$labels->view_item          = __( 'View entry', 'storewright' );
		$labels->all_items          = __( 'All entries', 'storewright' );
		$labels->search_items       = __( 'Search entries', 'storewright' );
		$labels->not_found          = __( 'No entries yet.', 'storewright' );
		$labels->not_found_in_trash = __( 'No entries in the bin.', 'storewright' );
		return $labels;
	}
}
