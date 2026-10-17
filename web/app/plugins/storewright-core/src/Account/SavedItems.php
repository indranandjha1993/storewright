<?php

declare(strict_types=1);

namespace Storewright\Core\Account;

final class SavedItems {

	public const META   = 'storewright_saved';
	private const LIMIT = 100;

	public function __construct( private readonly int $userId ) {}

	public function ids(): array {
		$ids = get_user_meta( $this->userId, self::META, true );
		return is_array( $ids ) ? array_values( array_unique( array_map( 'intval', $ids ) ) ) : [];
	}

	public function has( int $productId ): bool {
		return in_array( $productId, $this->ids(), true );
	}

	public function toggle( int $productId ): bool {
		if ( $this->has( $productId ) ) {
			$this->remove( $productId );
			return false;
		}
		$ids = [ $productId, ...$this->ids() ];
		update_user_meta( $this->userId, self::META, array_slice( $ids, 0, self::LIMIT ) );
		return true;
	}

	public function remove( int $productId ): void {
		update_user_meta( $this->userId, self::META, array_values( array_diff( $this->ids(), [ $productId ] ) ) );
	}

	public function products(): array {
		$products = array_map( 'wc_get_product', $this->ids() );
		return array_values( array_filter( $products, static fn( $product ): bool => $product instanceof \WC_Product && $product->is_visible() ) );
	}
}
