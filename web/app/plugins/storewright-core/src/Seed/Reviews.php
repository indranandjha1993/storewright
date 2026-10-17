<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

use WC_Comments;

final class Reviews {

	private const REVIEWS = [
		'SW-MUG'   => [
			[ 'Asha Menon', 'asha@example.com', 5, 'Heavier than it looks and keeps tea warm for a while. The glaze has small variations, which I like.' ],
			[ 'Rohan Deshpande', 'rohan@example.com', 4, 'Good size for coffee. Arrived well packed. Would buy a second one in another colour if there was one.' ],
			[ 'Meera Iyer', 'meera@example.com', 5, 'Sits nicely on the desk and goes in the dishwasher without fading.' ],
		],
		'SW-NOTE'  => [
			[ 'Kabir Shah', 'kabir@example.com', 5, 'Fountain pen ink does not bleed through. The dots are light enough to ignore when I want plain pages.' ],
			[ 'Asha Menon', 'asha@example.com', 4, 'Lies flat from the first page. I wish it came with a ribbon marker.' ],
		],
		'SW-TEE'   => [
			[ 'Rohan Deshpande', 'rohan@example.com', 5, 'Thick cotton, true to size after two washes. Ordered M at 5 ft 10 in and it fits well.' ],
			[ 'Nisha Rao', 'nisha@example.com', 4, 'Soft and does not cling. The sand colour is a little lighter than the photo.' ],
		],
		'SW-EBOOK' => [
			[ 'Kabir Shah', 'kabir@example.com', 5, 'Short and practical. The returns chapter alone saved me an evening of reading policies.' ],
		],
	];

	public function run(): int {
		$count = 0;
		foreach ( self::REVIEWS as $sku => $reviews ) {
			$productId = wc_get_product_id_by_sku( $sku );
			if ( ! $productId || get_comments(
				[
					'post_id' => $productId,
					'type'    => 'review',
					'count'   => true,
				]
			) ) {
				continue;
			}
			foreach ( $reviews as $index => [$name, $email, $rating, $text] ) {
				$user      = get_user_by( 'email', $email );
				$commentId = wp_insert_comment(
					[
						'comment_post_ID'      => $productId,
						'comment_author'       => $name,
						'comment_author_email' => $email,
						'comment_content'      => $text,
						'comment_type'         => 'review',
						'comment_approved'     => 1,
						'user_id'              => $user ? $user->ID : 0,
						'comment_date'         => gmdate( 'Y-m-d H:i:s', time() - ( $index + 1 ) * 9 * DAY_IN_SECONDS ),
					]
				);
				if ( $commentId ) {
					update_comment_meta( $commentId, 'rating', $rating );
					update_comment_meta( $commentId, 'verified', 1 );
					++$count;
				}
			}
			WC_Comments::clear_transients( $productId );
		}
		return $count;
	}
}
