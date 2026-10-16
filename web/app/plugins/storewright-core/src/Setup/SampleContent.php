<?php

declare(strict_types=1);

namespace Storewright\Core\Setup;

final class SampleContent {

	private const POSTS = [
		'hello-world' => 'post',
		'sample-page' => 'page',
	];

	public function remove(): int {
		$removed = 0;
		foreach ( self::POSTS as $slug => $type ) {
			$post = get_page_by_path( $slug, OBJECT, $type );
			if ( $post && $post->post_modified_gmt === $post->post_date_gmt && wp_delete_post( $post->ID, true ) ) {
				++$removed;
			}
		}
		foreach ( get_comments( [ 'author_email' => 'wapuu@wordpress.example' ] ) as $comment ) {
			if ( $comment instanceof \WP_Comment && wp_delete_comment( (int) $comment->comment_ID, true ) ) {
				++$removed;
			}
		}
		return $removed;
	}
}
