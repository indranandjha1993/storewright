<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use InvalidArgumentException;
use OutOfBoundsException;
use Storewright\Core\Asset;
use Storewright\Core\Delivery\AreaInput;
use Storewright\Core\Delivery\Areas;
use Storewright\Core\Delivery\Places;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\ShopperCheck;

final class AreasPage {

	public const SLUG = 'storewright-areas';

	private const COLOURS = [ '#0f5c4d', '#b4541a', '#3858e9', '#8a2c8a', '#5b6b00', '#a0352c' ];

	private const DRAFT = 'storewright_area_draft_';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		Action::on( 'area_save', [ self::class, 'save' ] );
		Action::on( 'area_delete', [ self::class, 'delete' ] );
		Action::on( 'area_move', [ self::class, 'move' ] );
	}

	public static function url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Delivery areas', 'storewright' ), __( 'Delivery areas', 'storewright' ), 'manage_woocommerce', self::SLUG, [ self::class, 'render' ] );
	}

	public static function assets( string $hook ): void {
		if ( ! str_ends_with( $hook, '_page_' . self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'leaflet', Asset::url( 'assets/vendor/leaflet/leaflet.css' ), [], '1.9.4' );
		wp_enqueue_script( 'leaflet', Asset::url( 'assets/vendor/leaflet/leaflet.js' ), [], '1.9.4', true );
		Asset::script( 'storewright-admin-areas', 'assets/js/admin-areas.js', [ 'leaflet', 'wp-api-fetch' ] );
	}

	public static function render(): void {
		$area = Action::query( 'area' );
		if ( $area === '' ) {
			self::list();
			return;
		}
		$areas = self::areas()->all();
		$draft = self::draft();
		$saved = $area === 'new' ? null : ( array_values( array_filter( $areas, static fn( array $item ): bool => (string) $item['id'] === $area ) )[0] ?? null );
		if ( $area !== 'new' && $saved === null ) {
			wp_die( esc_html__( 'That delivery area does not exist.', 'storewright' ) );
		}
		self::editor( $saved, $draft, $areas );
	}

	public static function save(): void {
		$id      = Action::text( 'id' );
		$editing = $id === '' ? 'new' : $id;
		try {
			$input = AreaInput::from( self::input() );
			if ( $id === '' ) {
				self::areas()->create( $input );
			} else {
				self::areas()->update( (int) $id, $input );
			}
			delete_transient( self::DRAFT . get_current_user_id() );
			/* translators: %s: area name */
			Flash::success( sprintf( __( '%s saved.', 'storewright' ), $id === '0' ? __( 'Everywhere else', 'storewright' ) : $input->name ) );
			Action::back( self::url() );
		} catch ( InvalidArgumentException | OutOfBoundsException $e ) {
			set_transient( self::DRAFT . get_current_user_id(), self::input(), 10 * MINUTE_IN_SECONDS );
			Flash::error( $e->getMessage() );
			Action::back( self::url( [ 'area' => $editing ] ) );
		}
	}

	public static function delete(): void {
		try {
			self::areas()->delete( (int) Action::text( 'id' ) );
			Flash::success( __( 'The area is deleted. Addresses it covered move on to the next area.', 'storewright' ) );
		} catch ( InvalidArgumentException | OutOfBoundsException $e ) {
			Flash::error( $e->getMessage() );
		}
		Action::back( self::url() );
	}

	public static function move(): void {
		$id   = (int) Action::query( 'id' );
		$step = (int) Action::query( 'step' );
		$ids  = array_values( array_filter( array_map( static fn( array $area ): int => (int) $area['id'], self::areas()->all() ) ) );
		$from = array_search( $id, $ids, true );
		if ( $from !== false && isset( $ids[ $from + $step ] ) ) {
			[ $ids[ $from ], $ids[ $from + $step ] ] = [ $ids[ $from + $step ], $ids[ $from ] ];
			try {
				self::areas()->reorder( $ids );
			} catch ( InvalidArgumentException $e ) {
				Flash::error( $e->getMessage() );
			}
		}
		Action::back( self::url() );
	}

	private static function list(): void {
		$areas   = self::areas()->all();
		$answer  = self::answer();
		$colours = [];
		foreach ( $areas as $index => $area ) {
			$colours[ $area['id'] ] = self::COLOURS[ $index % count( self::COLOURS ) ];
		}
		self::settings(
			[
				'mode'   => 'list',
				'shapes' => array_values(
					array_filter(
						array_map(
							static fn( array $area ): ?array => $area['shape'] ? [
								'shape'  => $area['shape'],
								'colour' => $colours[ $area['id'] ],
							] : null,
							$areas
						)
					)
				),
				'marker' => $answer['point'] ?? null,
			]
		);
		echo '<div class="wrap sw-areas">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Delivery areas', 'storewright' ) . '</h1>';
		printf( ' <a href="%s" class="page-title-action">%s</a>', esc_url( self::url( [ 'area' => 'new' ] ) ), esc_html__( 'Add an area', 'storewright' ) );
		echo '<p class="sw-lead">' . esc_html__( 'Addresses are checked against the areas from the top. The first area that covers an address and offers a delivery method decides what the shopper can choose.', 'storewright' ) . '</p>';
		echo '<div class="sw-areas__layout"><div class="sw-areas__main">';
		self::tester( $answer );
		echo '<ol class="sw-areas__items">';
		$movable = array_values( array_filter( $areas, static fn( array $area ): bool => $area['id'] !== 0 ) );
		foreach ( $areas as $area ) {
			self::item( $area, $colours[ $area['id'] ], $area['id'] === ( $movable[0]['id'] ?? null ), $area['id'] === ( end( $movable )['id'] ?? null ), $area['id'] === ( $answer['areaId'] ?? null ) );
		}
		echo '</ol></div>';
		self::map();
		echo '</div></div>';
	}

	private static function tester( ?array $answer ): void {
		printf( '<form method="get" action="%s" class="sw-areas__test" aria-labelledby="sw-areas-test-title">', esc_url( admin_url( 'admin.php' ) ) );
		printf( '<input type="hidden" name="page" value="%s">', esc_attr( self::SLUG ) );
		echo '<h2 id="sw-areas-test-title">' . esc_html__( 'Test an address', 'storewright' ) . '</h2>';
		echo '<p class="sw-areas__hint">' . esc_html__( 'Enter a PIN code, or click the map to test a spot. The answer is what a shopper would see.', 'storewright' ) . '</p>';
		printf(
			'<div class="sw-areas__row"><label for="sw-test-pin">%s</label><input type="text" id="sw-test-pin" name="test_pin" inputmode="numeric" maxlength="6" value="%s"> <button type="submit" class="button">%s</button></div>',
			esc_html__( 'PIN code to test', 'storewright' ),
			esc_attr( Action::query( 'test_pin' ) ),
			esc_html__( 'Check', 'storewright' )
		);
		echo '<div role="status">';
		if ( $answer ) {
			printf( '<div class="sw-areas__answer is-%s"><p class="sw-areas__answer-message">%s</p>', esc_attr( $answer['status'] ), esc_html( $answer['message'] ) );
			if ( $answer['area'] ) {
				/* translators: %s: delivery area name */
				echo '<p class="sw-areas__hint">' . esc_html( sprintf( __( 'Decided by %s.', 'storewright' ), $answer['area'] ) ) . '</p>';
			}
			if ( $answer['options'] !== [] ) {
				echo '<ul class="sw-areas__options">';
				foreach ( $answer['options'] as $option ) {
					echo '<li>' . esc_html( $option['title'] . ': ' . $option['charge'] ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		echo '</div></form>';
	}

	private static function answer(): ?array {
		$pin = Action::query( 'test_pin' );
		$lat = Action::query( 'test_lat' );
		$lng = Action::query( 'test_lng' );
		if ( $lat !== '' && $lng !== '' ) {
			return ShopperCheck::byPoint( new Point( (float) $lat, (float) $lng ), false );
		}
		return $pin === '' ? null : ShopperCheck::byPin( $pin, false );
	}

	private static function item( array $area, string $colour, bool $first, bool $last, bool $highlighted ): void {
		$fixed = $area['id'] === 0;
		printf( '<li class="sw-areas__item%s" aria-label="%s"><span class="sw-areas__position" style="background:%s">%d</span><div class="sw-areas__body">', $highlighted ? ' is-highlighted' : '', esc_attr( $area['name'] ), esc_attr( $colour ), (int) $area['position'] );
		echo '<h2 class="sw-areas__name">' . esc_html( $area['name'] ) . '</h2><dl class="sw-areas__facts">';
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Where', 'storewright' ), esc_html( self::whereText( $area ) ) );
		if ( ! $fixed ) {
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'PIN codes', 'storewright' ), esc_html( $area['pins'] ? implode( ', ', $area['pins'] ) : __( 'Any PIN code', 'storewright' ) ) );
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Map', 'storewright' ), esc_html( self::shapeText( $area['shape'] ) ) );
		}
		printf( '<dt>%s</dt><dd>%s</dd></dl>', esc_html__( 'Delivery', 'storewright' ), esc_html( self::methodsText( $area ) ) );
		echo '<div class="sw-areas__actions">';
		printf( '<a href="%s">%s</a>', esc_url( self::url( [ 'area' => $area['id'] ] ) ), esc_html__( 'Edit', 'storewright' ) );
		if ( ! $fixed ) {
			if ( ! $first ) {
				printf(
					'<a class="sw-quiet" href="%s">%s</a>',
					esc_url(
						Action::url(
							'area_move',
							[
								'id'   => $area['id'],
								'step' => -1,
							]
						)
					),
					esc_html__( 'Move up', 'storewright' )
				);
			}
			if ( ! $last ) {
				printf(
					'<a class="sw-quiet" href="%s">%s</a>',
					esc_url(
						Action::url(
							'area_move',
							[
								'id'   => $area['id'],
								'step' => 1,
							]
						)
					),
					esc_html__( 'Move down', 'storewright' )
				);
			}
		}
		echo '</div></div></li>';
	}

	private static function editor( ?array $saved, ?array $draft, array $areas ): void {
		$fixed  = $saved !== null && $saved['id'] === 0;
		$values = $draft ?? self::values( $saved );
		$title  = $saved === null ? __( 'New delivery area', 'storewright' ) : $saved['name'];
		$others = [];
		foreach ( $areas as $index => $area ) {
			if ( $area['shape'] && $area['id'] !== ( $saved['id'] ?? null ) ) {
				$others[] = [
					'shape'  => $area['shape'],
					'colour' => self::COLOURS[ $index % count( self::COLOURS ) ],
					'faint'  => true,
				];
			}
		}
		self::settings(
			[
				'mode'   => 'edit',
				'shapes' => $others,
				'draft'  => [
					'kind'    => $values['shape_kind'],
					'centre'  => $values['shape_lat'] !== '' ? [ (float) $values['shape_lat'], (float) $values['shape_lng'] ] : null,
					'corners' => json_decode( $values['shape_corners'] ?: '[]', true ) ?: [],
				],
			]
		);
		echo '<div class="wrap sw-areas">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		echo '<div class="sw-areas__layout"><div class="sw-areas__main">';
		printf( '<form method="post" action="%s" class="sw-areas__editor" aria-label="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $title ) );
		Action::fields( 'area_save' );
		printf( '<input type="hidden" name="id" value="%s">', esc_attr( $saved === null ? '' : (string) $saved['id'] ) );
		if ( $fixed ) {
			echo '<p class="sw-areas__hint">' . esc_html__( 'Everywhere else covers every address no other area covers. Only its delivery methods can change.', 'storewright' ) . '</p>';
		} else {
			self::placeFields( $values );
			self::shapeFields( $values );
		}
		self::methodFields( $values['methods'] );
		printf( '<p class="sw-areas__buttons"><button type="submit" class="button button-primary">%s</button> <a class="button" href="%s">%s</a></p>', esc_html__( 'Save', 'storewright' ), esc_url( self::url() ), esc_html__( 'Cancel', 'storewright' ) );
		echo '</form>';
		if ( $saved !== null && ! $fixed ) {
			printf( '<details class="sw-areas__delete"><summary>%s</summary><form method="post" action="%s">', esc_html__( 'Delete area', 'storewright' ), esc_url( admin_url( 'admin-post.php' ) ) );
			Action::fields( 'area_delete' );
			/* translators: %s: delivery area name */
			printf( '<input type="hidden" name="id" value="%d"><p>%s</p><button type="submit" class="button button-link-delete">%s</button></form></details>', (int) $saved['id'], esc_html( sprintf( __( 'Delete %s? Addresses it covers move on to the next area.', 'storewright' ), $saved['name'] ) ), esc_html__( 'Delete', 'storewright' ) );
		}
		echo '</div>';
		self::map();
		echo '</div></div>';
	}

	private static function placeFields( array $values ): void {
		echo '<div class="sw-field"><label for="sw-area-name">' . esc_html__( 'Name', 'storewright' ) . '</label>';
		printf( '<input type="text" id="sw-area-name" name="name" value="%s" class="regular-text"><p class="description">%s</p></div>', esc_attr( $values['name'] ), esc_html__( 'Only staff see this name.', 'storewright' ) );
		echo '<div class="sw-field"><label for="sw-area-regions">' . esc_html__( 'Regions', 'storewright' ) . '</label><select id="sw-area-regions" name="regions[]" multiple size="6">';
		foreach ( Places::options() as $place ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $place['code'] ), in_array( $place['code'], $values['regions'], true ) ? ' selected' : '', esc_html( $place['label'] ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Hold Ctrl or Cmd to pick more than one.', 'storewright' ) . '</p></div>';
		echo '<div class="sw-field"><label for="sw-area-pins">' . esc_html__( 'PIN codes', 'storewright' ) . '</label>';
		printf( '<textarea id="sw-area-pins" name="pins" rows="4" class="large-text">%s</textarea><p class="description">%s</p></div>', esc_textarea( $values['pins'] ), esc_html__( 'One per line: 560001 for one PIN code, 560* for every PIN code starting with 560, 560001-560099 for a range. Leave empty for every PIN code in the regions.', 'storewright' ) );
	}

	private static function shapeFields( array $values ): void {
		echo '<fieldset class="sw-areas__card"><legend>' . esc_html__( 'Map shape', 'storewright' ) . '</legend>';
		foreach ( [
			'none'    => __( 'No shape, the regions and PIN codes decide', 'storewright' ),
			'radius'  => __( 'A radius around a point', 'storewright' ),
			'polygon' => __( 'An area drawn on the map', 'storewright' ),
		] as $kind => $label ) {
			printf( '<label class="sw-areas__choice"><input type="radio" name="shape_kind" value="%s"%s> %s</label>', esc_attr( $kind ), $values['shape_kind'] === $kind ? ' checked' : '', esc_html( $label ) );
		}
		echo '<p class="sw-areas__hint" data-shape-note>' . esc_html__( 'With a shape, the shopper has to be inside it: their shared location if they gave one, otherwise the centre of their PIN code.', 'storewright' ) . '</p>';
		printf( '<input type="hidden" name="shape_lat" value="%s"><input type="hidden" name="shape_lng" value="%s"><input type="hidden" name="shape_corners" value="%s">', esc_attr( $values['shape_lat'] ), esc_attr( $values['shape_lng'] ), esc_attr( $values['shape_corners'] ) );
		printf( '<div class="sw-field" data-shape-only="radius"><label for="sw-area-radius">%s</label><input type="number" id="sw-area-radius" name="radius_km" min="0.1" step="0.1" value="%s" class="small-text"></div>', esc_html__( 'Radius in km', 'storewright' ), esc_attr( $values['radius_km'] ) );
		echo '<p class="sw-areas__hint" data-shape-hint></p>';
		printf( '<p class="sw-areas__buttons" data-shape-only="polygon"><button type="button" class="button" data-undo-corner>%s</button> <button type="button" class="button-link" data-clear-corners>%s</button></p>', esc_html__( 'Undo last corner', 'storewright' ), esc_html__( 'Start again', 'storewright' ) );
		printf( '<div class="sw-areas__row" data-shape-only="any"><label for="sw-area-find">%s</label><input type="text" id="sw-area-find" inputmode="numeric" maxlength="6"> <button type="button" class="button" data-find-pin>%s</button><p class="sw-areas__hint" data-find-note></p></div>', esc_html__( 'Show a PIN code on the map', 'storewright' ), esc_html__( 'Show', 'storewright' ) );
		echo '</fieldset>';
	}

	private static function methodFields( array $methods ): void {
		echo '<fieldset class="sw-areas__card" data-methods><legend>' . esc_html__( 'Delivery methods', 'storewright' ) . '</legend>';
		echo '<p class="sw-areas__hint" data-no-methods' . ( $methods === [] ? '' : ' hidden' ) . '>' . esc_html__( 'Without a method, addresses in this area are refused at checkout.', 'storewright' ) . '</p>';
		echo '<div data-method-rows>';
		foreach ( array_values( $methods ) as $index => $method ) {
			self::methodRow( $method, (string) $index );
		}
		echo '</div>';
		printf( '<p class="sw-areas__buttons"><button type="button" class="button" data-add-method="flat_rate">%s</button> <button type="button" class="button" data-add-method="free_shipping">%s</button></p>', esc_html__( 'Add a delivery charge', 'storewright' ), esc_html__( 'Add free delivery', 'storewright' ) );
		foreach ( [
			'flat_rate'     => __( 'Delivery', 'storewright' ),
			'free_shipping' => __( 'Free delivery', 'storewright' ),
		] as $type => $title ) {
			printf( '<template data-method-template="%s">', esc_attr( $type ) );
			self::methodRow(
				[
					'id'        => 0,
					'type'      => $type,
					'title'     => $title,
					'cost'      => '',
					'minAmount' => '',
					'enabled'   => true,
					'editable'  => true,
					'charge'    => '',
				],
				'__index__'
			);
			echo '</template>';
		}
		echo '</fieldset>';
	}

	private static function methodRow( array $method, string $index ): void {
		$name = 'methods[' . $index . ']';
		/* translators: %s: method number or placeholder */
		printf( '<div class="sw-areas__method" role="group" data-method-row><input type="hidden" name="%1$s[id]" value="%2$d"><input type="hidden" name="%1$s[type]" value="%3$s">', esc_attr( $name ), (int) $method['id'], esc_attr( $method['type'] ) );
		printf( '<label class="sw-areas__choice"><input type="checkbox" name="%s[enabled]" value="1"%s> %s</label>', esc_attr( $name ), $method['enabled'] ? ' checked' : '', esc_html__( 'Offered', 'storewright' ) );
		if ( $method['editable'] ) {
			printf( '<div class="sw-field"><label>%s<input type="text" name="%s[title]" value="%s" class="regular-text"></label></div>', esc_html__( 'Name shoppers see', 'storewright' ), esc_attr( $name ), esc_attr( $method['title'] ) );
			if ( $method['type'] === 'flat_rate' ) {
				printf( '<div class="sw-field"><label>%s<input type="number" name="%s[cost]" min="0" step="any" value="%s" class="small-text"></label><p class="description">%s</p></div>', esc_html__( 'Charge', 'storewright' ), esc_attr( $name ), esc_attr( (string) $method['cost'] ), esc_html__( 'Leave empty or 0 to make it free.', 'storewright' ) );
			} else {
				printf( '<div class="sw-field"><label>%s<input type="number" name="%s[minAmount]" min="0" step="any" value="%s" class="small-text"></label><p class="description">%s</p></div>', esc_html__( 'Free on orders of at least', 'storewright' ), esc_attr( $name ), esc_attr( (string) $method['minAmount'] ), esc_html__( 'Leave empty to make it free on every order.', 'storewright' ) );
			}
		} else {
			/* translators: 1: method name, 2: what it costs */
			echo '<p class="sw-areas__hint">' . esc_html( sprintf( __( '%1$s, %2$s. Its other settings are changed under Store settings.', 'storewright' ), $method['title'], $method['charge'] ) ) . '</p>';
		}
		printf( '<button type="button" class="button-link sw-quiet" data-remove-method>%s</button></div>', esc_html__( 'Remove', 'storewright' ) );
	}

	private static function values( ?array $saved ): array {
		$shape = $saved['shape'] ?? null;
		return [
			'name'          => $saved['name'] ?? '',
			'regions'       => $saved ? array_column( $saved['regions'], 'code' ) : [ Places::options()[0]['code'] ],
			'pins'          => $saved ? implode( "\n", $saved['pins'] ) : '',
			'shape_kind'    => $shape['kind'] ?? 'none',
			'shape_lat'     => isset( $shape['lat'] ) ? (string) $shape['lat'] : '',
			'shape_lng'     => isset( $shape['lng'] ) ? (string) $shape['lng'] : '',
			'shape_corners' => isset( $shape['polygon'] ) ? (string) wp_json_encode( $shape['polygon'] ) : '',
			'radius_km'     => isset( $shape['radius_m'] ) ? (string) ( $shape['radius_m'] / 1000 ) : '5',
			'methods'       => $saved['methods'] ?? [
				[
					'id'        => 0,
					'type'      => 'flat_rate',
					'title'     => __( 'Delivery', 'storewright' ),
					'cost'      => '',
					'minAmount' => '',
					'enabled'   => true,
					'editable'  => true,
					'charge'    => '',
				],
			],
		];
	}

	private static function draft(): ?array {
		$draft = get_transient( self::DRAFT . get_current_user_id() );
		if ( ! is_array( $draft ) ) {
			return null;
		}
		delete_transient( self::DRAFT . get_current_user_id() );
		$byCode  = array_column( Places::options(), 'code', 'code' );
		$methods = [];
		foreach ( $draft['methods'] as $method ) {
			$methods[] = [
				...$method,
				'editable' => true,
				'charge'   => '',
			];
		}
		return [
			'name'          => $draft['name'],
			'regions'       => array_values( array_intersect( array_column( $draft['regions'], 1 ), $byCode ) ),
			'pins'          => $draft['pins'],
			'shape_kind'    => $draft['shape_kind'],
			'shape_lat'     => $draft['shape_lat'],
			'shape_lng'     => $draft['shape_lng'],
			'shape_corners' => $draft['shape_corners'],
			'radius_km'     => $draft['radius_km'],
			'methods'       => $methods,
		];
	}

	private static function input(): array {
		$places  = array_column( Places::options(), null, 'code' );
		$regions = [];
		foreach ( (array) ( $_POST['regions'] ?? [] ) as $code ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$code = sanitize_text_field( wp_unslash( (string) $code ) );
			if ( isset( $places[ $code ] ) ) {
				$regions[] = [
					'type' => $places[ $code ]['type'],
					'code' => $code,
				];
			}
		}
		$methods = [];
		foreach ( (array) ( $_POST['methods'] ?? [] ) as $method ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$method    = array_map( static fn( mixed $value ): string => sanitize_text_field( wp_unslash( (string) $value ) ), (array) $method );
			$methods[] = [
				'id'        => (int) ( $method['id'] ?? 0 ),
				'type'      => $method['type'] ?? '',
				'title'     => $method['title'] ?? '',
				'cost'      => $method['cost'] ?? '',
				'minAmount' => $method['minAmount'] ?? '',
				'enabled'   => ! empty( $method['enabled'] ),
			];
		}
		$kind    = Action::text( 'shape_kind' );
		$lat     = Action::text( 'shape_lat' );
		$lng     = Action::text( 'shape_lng' );
		$corners = json_decode( Action::text( 'shape_corners' ) ?: '[]', true );
		$shape   = null;
		if ( $kind === 'radius' ) {
			if ( $lat === '' || $lng === '' ) {
				throw new InvalidArgumentException( esc_html__( 'Click the map to place the centre of the radius.', 'storewright' ) );
			}
			if ( (float) Action::text( 'radius_km' ) <= 0 ) {
				throw new InvalidArgumentException( esc_html__( 'The radius must be more than 0 km.', 'storewright' ) );
			}
			$shape = [
				'kind'     => 'radius',
				'lat'      => (float) $lat,
				'lng'      => (float) $lng,
				'radius_m' => (float) Action::text( 'radius_km' ) * 1000,
			];
		} elseif ( $kind === 'polygon' ) {
			if ( ! is_array( $corners ) || count( $corners ) < 3 ) {
				throw new InvalidArgumentException( esc_html__( 'Click the map at least three times to draw the area.', 'storewright' ) );
			}
			$shape = [
				'kind'    => 'polygon',
				'polygon' => $corners,
			];
		}
		return [
			'name'          => Action::text( 'name' ),
			'regions'       => $regions,
			'pins'          => Action::lines( 'pins' ),
			'shape'         => $shape,
			'methods'       => $methods,
			'shape_kind'    => $kind,
			'shape_lat'     => $lat,
			'shape_lng'     => $lng,
			'shape_corners' => Action::text( 'shape_corners' ),
			'radius_km'     => Action::text( 'radius_km' ),
		];
	}

	private static function map(): void {
		printf( '<div class="sw-areas__map" id="sw-areas-map" aria-label="%s"></div>', esc_attr__( 'Delivery area map', 'storewright' ) );
	}

	private static function settings( array $settings ): void {
		$tiles = apply_filters(
			'storewright_map_tiles',
			[
				'url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
				'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			]
		);
		$text  = [
			'placeCentre' => __( 'Click the map to place the centre.', 'storewright' ),
			/* translators: 1: latitude, 2: longitude */
			'centre'      => __( 'Centre at %1, %2. Click the map to move it.', 'storewright' ),
			/* translators: %1: number of corners */
			'corners'     => __( '%1 corners so far. Click the map to add a corner, drag a corner to move it.', 'storewright' ),
			/* translators: %1: corner number */
			'corner'      => __( 'Corner %1', 'storewright' ),
			/* translators: 1: PIN code, 2: district */
			'showing'     => __( 'Showing %1, %2.', 'storewright' ),
		];
		wp_add_inline_script(
			'storewright-admin-areas',
			'window.storewrightAreas = ' . wp_json_encode(
				[
					...$settings,
					'tiles'   => $tiles,
					'listUrl' => self::url(),
				]
			) . '; window.storewrightAreasText = ' . wp_json_encode( $text ) . ';',
			'before'
		);
	}

	private static function whereText( array $area ): string {
		if ( $area['id'] === 0 ) {
			return __( 'Every address no other area covers', 'storewright' );
		}
		return $area['regions'] ? implode( ', ', array_column( $area['regions'], 'label' ) ) : __( 'Anywhere', 'storewright' );
	}

	private static function shapeText( ?array $shape ): string {
		if ( ( $shape['kind'] ?? '' ) === 'radius' ) {
			/* translators: 1: distance in km, 2: latitude, 3: longitude */
			return sprintf( __( 'Within %1$s km of %2$s, %3$s', 'storewright' ), $shape['radius_m'] / 1000, number_format( (float) $shape['lat'], 4 ), number_format( (float) $shape['lng'], 4 ) );
		}
		if ( ( $shape['kind'] ?? '' ) === 'polygon' ) {
			/* translators: %d: number of corners */
			return sprintf( __( 'Inside a drawn area with %d corners', 'storewright' ), count( $shape['polygon'] ) );
		}
		return __( 'No map shape', 'storewright' );
	}

	private static function methodsText( array $area ): string {
		$offered = array_filter( $area['methods'], static fn( array $method ): bool => (bool) $method['enabled'] );
		if ( $offered === [] ) {
			return __( 'None, so these addresses are refused at checkout', 'storewright' );
		}
		return implode( '; ', array_map( static fn( array $method ): string => $method['title'] . ': ' . $method['charge'], $offered ) );
	}

	private static function areas(): Areas {
		global $wpdb;
		return new Areas( $wpdb );
	}
}
