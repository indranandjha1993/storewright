<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

final class Photos {

	private const PEXELS = [
		'SW-TEE'   => [ 12025472, 'Mr Mockup' ],
		'SW-MUG'   => [ 29783602, 'Brunxs' ],
		'SW-NOTE'  => [ 8059127, 'Eva Bronzini' ],
		'SW-EBOOK' => [ 31145642, 'Leticia Alvares' ],
		'SW-ICONS' => [ 6614748, 'Tima Miroshnichenko' ],
	];

	public static function url( string $sku ): string {
		$id = self::PEXELS[ $sku ][0] ?? 0;
		return $id ? sprintf( 'https://images.pexels.com/photos/%1$d/pexels-photo-%1$d.jpeg?auto=compress&cs=tinysrgb&w=1600', $id ) : '';
	}

	public static function credit( string $sku ): string {
		[ $id, $photographer ] = self::PEXELS[ $sku ] ?? [ 0, '' ];
		/* translators: 1: photographer, 2: photo URL */
		return $id ? sprintf( __( 'Photo by %1$s on Pexels, %2$s', 'storewright' ), $photographer, "https://www.pexels.com/photo/{$id}/" ) : '';
	}
}
