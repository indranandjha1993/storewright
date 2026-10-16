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
		'apparel'  => [ 12025472, 'Mr Mockup' ],
		'desk'     => [ 7319184, 'Angela Roma' ],
		'books'    => [ 6963777, 'Cup of Couple' ],
		'software' => [ 6614748, 'Tima Miroshnichenko' ],
		'hero'     => [ 8092417, 'Karola G.' ],
	];

	public static function url( string $key ): string {
		$id = self::PEXELS[ $key ][0] ?? 0;
		return $id ? sprintf( 'https://images.pexels.com/photos/%1$d/pexels-photo-%1$d.jpeg?auto=compress&cs=tinysrgb&w=1600', $id ) : '';
	}

	public static function credit( string $key ): string {
		[ $id, $photographer ] = self::PEXELS[ $key ] ?? [ 0, '' ];
		/* translators: 1: photographer, 2: photo URL */
		return $id ? sprintf( __( 'Photo by %1$s on Pexels, %2$s', 'storewright' ), $photographer, "https://www.pexels.com/photo/{$id}/" ) : '';
	}
}
