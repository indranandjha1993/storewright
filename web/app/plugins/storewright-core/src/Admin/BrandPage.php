<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use InvalidArgumentException;
use Storewright\Core\Asset;
use Storewright\Platform\Brand\Fonts;
use Storewright\Platform\Brand\Palette;
use Storewright\Platform\Brand\Settings;
use Storewright\Platform\Brand\Update;
use Storewright\Platform\WhiteLabel\Brand;

final class BrandPage {

	public const SLUG = 'storewright-brand';

	private const DRAFT = 'storewright_brand_draft_';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		Action::on( 'brand_save', [ self::class, 'save' ] );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Brand', 'storewright' ), __( 'Brand', 'storewright' ), 'manage_woocommerce', self::SLUG, [ self::class, 'render' ] );
	}

	public static function assets( string $hook ): void {
		if ( str_ends_with( $hook, '_page_' . self::SLUG ) ) {
			Asset::script( 'storewright-admin-brand', 'assets/js/admin-brand.js' );
		}
	}

	public static function save(): void {
		$input = [
			'name'         => Action::text( 'name' ),
			'description'  => Action::text( 'description' ),
			'announcement' => Action::text( 'announcement' ),
			'headingFont'  => Action::text( 'headingFont' ),
			'bodyFont'     => Action::text( 'bodyFont' ),
			'radius'       => Action::text( 'radius' ),
		];
		foreach ( [ 'accent', 'background', 'text', 'sale' ] as $colour ) {
			$input[ $colour ] = Action::text( $colour );
		}
		try {
			foreach ( [ 'logo', 'heroImage' ] as $image ) {
				$input[ $image ] = self::image( $image );
			}
			Update::apply( $input );
			delete_transient( self::DRAFT . get_current_user_id() );
			Flash::success( __( 'Saved. The store is wearing the new brand.', 'storewright' ) );
		} catch ( InvalidArgumentException $e ) {
			set_transient( self::DRAFT . get_current_user_id(), $input, 10 * MINUTE_IN_SECONDS );
			Flash::error( $e->getMessage() );
		}
		Action::back( self::url() );
	}

	private static function image( string $field ): int {
		$current = (int) Action::text( $field );
		if ( Action::text( $field . '_remove' ) === '1' ) {
			return 0;
		}
		$upload = $_FILES[ $field . '_file' ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_array( $upload ) || empty( $upload['name'] ) ) {
			return $current;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$id = media_handle_upload( $field . '_file', 0, [ 'post_title' => $field === 'logo' ? 'logo' : 'home' ] );
		if ( is_wp_error( $id ) ) {
			throw new InvalidArgumentException( esc_html( $id->get_error_message() ) );
		}
		return (int) $id;
	}

	public static function render(): void {
		$settings = Settings::get();
		$draft    = get_transient( self::DRAFT . get_current_user_id() );
		delete_transient( self::DRAFT . get_current_user_id() );
		$values = [
			...$settings,
			'name'         => Brand::name(),
			'description'  => (string) get_option( 'blogdescription', '' ),
			'announcement' => (string) get_option( 'storewright_announcement', '' ),
			'logo'         => Brand::logoId(),
			'heroImage'    => (int) get_option( 'storewright_hero_image' ),
			...( is_array( $draft ) ? $draft : [] ),
		];
		$fonts  = Fonts::all();
		echo '<style>' . Fonts::fontFaceCss( array_merge( ...array_column( $fonts, 'faces' ) ) ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="wrap sw-brand">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Brand', 'storewright' ) . '</h1>';
		echo '<p class="sw-lead">' . esc_html__( 'One set of choices styles the store, the checkout, emails, the sign-in page and this admin.', 'storewright' ) . '</p>';
		echo '<div class="sw-brand__layout">';
		printf( '<form method="post" action="%s" enctype="multipart/form-data" class="sw-brand__form" aria-label="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr__( 'Brand', 'storewright' ) );
		Action::fields( 'brand_save' );
		self::identity( $values );
		self::colours( $values );
		self::type( $values, $fonts );
		printf( '<p class="sw-brand__save"><button type="submit" class="button button-primary">%s</button></p></form>', esc_html__( 'Save brand', 'storewright' ) );
		self::preview( $values );
		echo '</div></div>';
	}

	private static function identity( array $values ): void {
		echo '<fieldset class="sw-brand__card"><legend>' . esc_html__( 'Identity', 'storewright' ) . '</legend>';
		printf( '<div class="sw-field"><label for="sw-brand-name">%s</label><input type="text" id="sw-brand-name" name="name" value="%s" class="regular-text" data-preview-name></div>', esc_html__( 'Store name', 'storewright' ), esc_attr( $values['name'] ) );
		self::imageField( 'logo', __( 'Logo', 'storewright' ), (int) $values['logo'], __( 'Without a logo the store shows its name in the heading typeface.', 'storewright' ), __( 'PNG or SVG with a transparent background works best, about 400 pixels wide.', 'storewright' ) );
		printf( '<div class="sw-field"><label for="sw-brand-description">%s</label><textarea id="sw-brand-description" name="description" rows="2" maxlength="160" class="large-text">%s</textarea><p class="description">%s</p></div>', esc_html__( 'Store description', 'storewright' ), esc_textarea( $values['description'] ), esc_html__( 'One or two sentences on what the store sells. Search results and link previews show it under the store name.', 'storewright' ) );
		printf( '<div class="sw-field"><label for="sw-brand-announcement">%s</label><input type="text" id="sw-brand-announcement" name="announcement" value="%s" maxlength="140" class="large-text"><p class="description">%s</p></div>', esc_html__( 'Announcement', 'storewright' ), esc_attr( $values['announcement'] ), esc_html__( 'One line above the header, for an offer or a delivery promise. Leave empty to hide it.', 'storewright' ) );
		self::imageField( 'heroImage', __( 'Home page photo', 'storewright' ), (int) $values['heroImage'], __( 'Without a photo the opening section sits on the soft accent tint.', 'storewright' ), __( 'A wide photo, at least 1600 pixels across. The headline sits on its left side.', 'storewright' ) );
		echo '</fieldset>';
	}

	private static function imageField( string $field, string $label, int $id, string $none, string $hint ): void {
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'large' ) : '';
		printf( '<div class="sw-field sw-brand__image"><span class="sw-brand__label">%s</span>', esc_html( $label ) );
		if ( $url !== '' ) {
			/* translators: %s: field label */
			printf( '<img class="sw-brand__image-current" src="%s" alt="%s" data-preview-%s>', esc_url( $url ), esc_attr( sprintf( __( 'Current %s', 'storewright' ), strtolower( $label ) ) ), esc_attr( $field ) );
		} else {
			echo '<p class="description">' . esc_html( $none ) . '</p>';
		}
		$button = $url !== '' ? __( 'Replace', 'storewright' ) : __( 'Upload', 'storewright' );
		/* translators: %s: field label */
		$file = sprintf( __( '%s file', 'storewright' ), $label );
		printf( '<input type="hidden" name="%1$s" value="%2$d"><label class="sw-brand__file"><span>%3$s</span><input type="file" name="%1$s_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" aria-label="%4$s"></label>', esc_attr( $field ), $id, esc_html( $button ), esc_attr( $file ) );
		if ( $url !== '' ) {
			printf( '<label class="sw-areas__choice"><input type="checkbox" name="%s_remove" value="1"> %s</label>', esc_attr( $field ), esc_html__( 'Remove', 'storewright' ) );
		}
		echo '<p class="description">' . esc_html( $hint ) . '</p></div>';
	}

	private static function colours( array $values ): void {
		echo '<fieldset class="sw-brand__card"><legend>' . esc_html__( 'Colours', 'storewright' ) . '</legend>';
		foreach ( [
			'accent'     => [ __( 'Accent', 'storewright' ), __( 'Buttons, links and highlights.', 'storewright' ) ],
			'background' => [ __( 'Background', 'storewright' ), __( 'The page behind everything. Cards sit on white, or a lighter shade of a dark background.', 'storewright' ) ],
			'text'       => [ __( 'Text', 'storewright' ), '' ],
			'sale'       => [ __( 'Sale', 'storewright' ), __( 'Sale badges and warnings.', 'storewright' ) ],
		] as $key => [ $label, $help ] ) {
			printf(
				'<div class="sw-field sw-brand__colour"><input type="color" class="sw-brand__swatch" value="%2$s" aria-label="%3$s" data-swatch="%1$s"><label for="sw-brand-%1$s">%4$s</label><input type="text" id="sw-brand-%1$s" name="%1$s" value="%2$s" class="regular-text" data-colour="%1$s">%5$s</div>',
				esc_attr( $key ),
				esc_attr( $values[ $key ] ),
				/* translators: %s: colour name */
				esc_attr( sprintf( __( '%s colour picker', 'storewright' ), $label ) ),
				esc_html( $label ),
				wp_kses_post( $help === '' ? '' : '<p class="description">' . esc_html( $help ) . '</p>' )
			);
		}
		echo '</fieldset>';
	}

	private static function type( array $values, array $fonts ): void {
		echo '<fieldset class="sw-brand__card"><legend>' . esc_html__( 'Typography and shape', 'storewright' ) . '</legend>';
		foreach ( [
			'headingFont' => __( 'Headings', 'storewright' ),
			'bodyFont'    => __( 'Body text', 'storewright' ),
		] as $key => $label ) {
			printf( '<div class="sw-field sw-brand__font"><label for="sw-brand-%1$s">%2$s</label><select id="sw-brand-%1$s" name="%1$s" data-font="%1$s">', esc_attr( $key ), esc_html( $label ) );
			foreach ( $fonts as $font ) {
				printf( '<option value="%s" data-family="%s"%s>%s</option>', esc_attr( $font['slug'] ), esc_attr( $font['family'] ), $font['slug'] === $values[ $key ] ? ' selected' : '', esc_html( $font['name'] ) );
			}
			printf( '</select><p class="sw-brand__specimen" style="font-family:%s" aria-hidden="true" data-specimen="%s">%s</p></div>', esc_attr( Fonts::get( $values[ $key ] )['family'] ), esc_attr( $key ), esc_html__( 'Fresh stoneware, hand finished in small batches. 0123456789', 'storewright' ) );
		}
		echo '<div class="sw-field"><span class="sw-brand__label">' . esc_html__( 'Corners', 'storewright' ) . '</span>';
		foreach ( [
			'sharp' => __( 'Sharp', 'storewright' ),
			'soft'  => __( 'Soft', 'storewright' ),
			'round' => __( 'Round, with pill buttons', 'storewright' ),
		] as $radius => $label ) {
			printf( '<label class="sw-areas__choice"><input type="radio" name="radius" value="%s"%s data-radius> %s</label>', esc_attr( $radius ), $values['radius'] === $radius ? ' checked' : '', esc_html( $label ) );
		}
		echo '</div></fieldset>';
	}

	private static function preview( array $values ): void {
		$palette = Palette::from( $values );
		$radii   = Palette::radii( $values['radius'] );
		$family  = static fn( string $slug ): string => Fonts::get( $slug )['family'];
		$style   = sprintf(
			'--p-base:%s;--p-surface:%s;--p-contrast:%s;--p-muted:%s;--p-line:%s;--p-accent:%s;--p-accent-hover:%s;--p-accent-soft:%s;--p-on-accent:%s;--p-sale:%s;--p-on-sale:%s;--p-radius:%s;--p-radius-small:%s;--p-radius-button:%s;--p-font-body:%s;--p-font-heading:%s',
			$palette['base'],
			$palette['surface'],
			$palette['contrast'],
			$palette['muted'],
			$palette['line'],
			$palette['accent'],
			$palette['accentHover'],
			$palette['accentSoft'],
			$palette['onAccent'],
			$palette['sale'],
			$palette['onSale'],
			$radii['radius'],
			$radii['small'],
			$radii['button'],
			$family( $values['bodyFont'] ),
			$family( $values['headingFont'] )
		);
		$logo    = (int) $values['logo'] ? (string) wp_get_attachment_image_url( (int) $values['logo'], 'medium' ) : '';
		printf( '<div class="sw-brand__preview" style="%s" aria-label="%s" data-preview>', esc_attr( $style ), esc_attr__( 'Preview of the store', 'storewright' ) );
		echo '<div class="sw-brand__preview-header">';
		if ( $logo !== '' ) {
			printf( '<img src="%s" alt="">', esc_url( $logo ) );
		} else {
			printf( '<strong data-preview-title>%s</strong>', esc_html( $values['name'] ?: __( 'Your store', 'storewright' ) ) );
		}
		printf( '<span>%s</span><span>%s</span></div>', esc_html__( 'Shop', 'storewright' ), esc_html__( 'Account', 'storewright' ) );
		printf( '<div class="sw-brand__preview-hero"><h2>%s</h2><p>%s</p><span class="sw-brand__preview-button">%s</span></div>', esc_html__( 'Made well, delivered to your door', 'storewright' ), esc_html__( 'Everyday things for home and work, with tracked delivery and simple returns.', 'storewright' ), esc_html__( 'Shop everything', 'storewright' ) );
		echo '<div class="sw-brand__preview-cards">';
		foreach ( [ [ 'Stoneware Mug', '₹449.00', false ], [ 'Everyday Tee', '₹649.00', true ] ] as [ $product, $price, $sale ] ) {
			printf( '<div class="sw-brand__preview-card"><div class="sw-brand__preview-image">%s</div><a href="#preview" onclick="return false">%s</a><small>%s</small></div>', $sale ? '<em>' . esc_html__( 'Sale', 'storewright' ) . '</em>' : '', esc_html( $product ), esc_html( $price ) );
		}
		echo '</div>';
		echo '<p class="sw-brand__preview-note">' . esc_html__( 'Links, buttons, badges and the admin follow these choices. Emails use the colours and the closest safe typeface.', 'storewright' ) . '</p></div>';
	}
}
