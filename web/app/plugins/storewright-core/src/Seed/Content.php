<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

final class Content {

	private Media $media;

	public function __construct() {
		$this->media = new Media();
	}

	public function run(): array {
		$pages = [
			'about'          => $this->page( 'About', 'about', $this->about() ),
			'contact'        => $this->page( 'Contact', 'contact', $this->contact() ),
			'returns'        => $this->page( 'Shipping and returns', 'returns', $this->returns() ),
			'terms'          => $this->page( 'Terms of sale', 'terms', $this->terms() ),
			'privacy-policy' => $this->page( 'Privacy policy', 'privacy-policy', $this->privacy() ),
			'journal'        => $this->page( 'Journal', 'journal', '' ),
			'home'           => $this->page( 'Home', 'home', '' ),
		];
		update_option( 'wp_page_for_privacy_policy', $pages['privacy-policy'] );
		update_option( 'woocommerce_terms_page_id', $pages['terms'] );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $pages['home'] );
		update_option( 'page_for_posts', $pages['journal'] );
		$this->retireDraft( 'refund_returns' );
		$this->renameDefaultCategory();
		$posts = [
			$this->post( 'How we choose what to sell', 'how-we-choose', $this->postChoosing(), 'post-choosing', 'Behind the shop' ),
			$this->post( 'Caring for stoneware', 'caring-for-stoneware', $this->postCare(), 'post-care', 'Care guides' ),
		];
		return [
			'pages' => count( $pages ),
			'posts' => count( $posts ),
		];
	}

	private function page( string $title, string $slug, string $content ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			if ( $existing->post_status !== 'publish' || ( $existing->post_content === '' && $content !== '' ) ) {
				wp_update_post(
					[
						'ID'           => $existing->ID,
						'post_status'  => 'publish',
						'post_content' => $existing->post_content === '' ? $content : $existing->post_content,
					]
				);
			}
			return $existing->ID;
		}
		return (int) wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			]
		);
	}

	private function post( string $title, string $slug, string $content, string $photo, string $category ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'post' );
		$excerpt  = wp_trim_words( wp_strip_all_tags( $content ), 28 );
		$id       = $existing ? $existing->ID : (int) wp_insert_post(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			]
		);
		if ( $existing && $existing->post_excerpt !== $excerpt ) {
			wp_update_post(
				[
					'ID'           => $id,
					'post_excerpt' => $excerpt,
				]
			);
		}
		wp_set_post_categories( $id, [ $this->category( $category ) ] );
		if ( ! has_post_thumbnail( $id ) ) {
			set_post_thumbnail( $id, $this->media->image( $title, '#dae4e0', Photos::url( $photo ), Photos::credit( $photo ) ) );
		}
		return $id;
	}

	private function category( string $name ): int {
		$term = get_term_by( 'name', $name, 'category' );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, 'category' );
		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	private function renameDefaultCategory(): void {
		$default = get_term( (int) get_option( 'default_category' ), 'category' );
		if ( $default instanceof \WP_Term && $default->slug === 'uncategorized' ) {
			wp_update_term(
				$default->term_id,
				'category',
				[
					'name' => __( 'Notes', 'storewright' ),
					'slug' => 'notes',
				]
			);
		}
	}

	private function retireDraft( string $slug ): void {
		$draft = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $draft && $draft->post_status === 'draft' ) {
			wp_delete_post( $draft->ID, true );
		}
	}

	private function paragraphs( array $blocks ): string {
		$out = [];
		foreach ( $blocks as $block ) {
			if ( str_starts_with( $block, '## ' ) ) {
				$out[] = '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( substr( $block, 3 ) ) . '</h2><!-- /wp:heading -->';
			} elseif ( str_starts_with( $block, '- ' ) ) {
				$items = array_map( static fn( string $line ): string => '<li>' . esc_html( ltrim( $line, '- ' ) ) . '</li>', explode( "\n", $block ) );
				$out[] = '<!-- wp:list --><ul class="wp-block-list">' . implode( "\n", $items ) . '</ul><!-- /wp:list -->';
			} else {
				$out[] = '<!-- wp:paragraph --><p>' . esc_html( $block ) . '</p><!-- /wp:paragraph -->';
			}
		}
		return implode( "\n\n", $out );
	}

	private function about(): string {
		return $this->paragraphs(
			[
				'We started with a simple complaint: the things we used every day were either beautiful or built to last, rarely both. So we went looking for makers who refused that trade.',
				'Everything in the shop is something we use ourselves. Mugs that survive the dishwasher for years, notebooks whose paper takes ink without bleeding, a tee that keeps its shape after fifty washes. If an item stops meeting that bar, it leaves the shop.',
				'## How we work',
				'We buy in small runs from workshops we have visited, pay them on time and keep a short list. That means some things sell out and come back a few weeks later. We would rather tell you that than hold stock that sat in a warehouse for a year.',
				'## Where we are',
				'Orders are packed in Bengaluru and sent across India. In the city we deliver the same day for orders placed before noon. Everything is tracked from the moment it leaves us.',
			]
		);
	}

	private function contact(): string {
		return $this->paragraphs(
			[
				'Write to us and a person answers, usually within a working day.',
				'## Orders and deliveries',
				'Sign in to your account to see where an order is. If something arrived damaged or not at all, reply to your order email with a photo and we will sort it out.',
				'## Wholesale and gifting',
				'We supply offices and events with custom runs of mugs, notebooks and tees from 50 pieces. Tell us the quantity and the date and we will send a quote.',
				'## Visiting',
				'We work from a small studio and do not have a shop floor. Collection is available for Bengaluru orders by arrangement.',
			]
		);
	}

	private function returns(): string {
		return $this->paragraphs(
			[
				'## Delivery',
				"- Across India: dispatched within one working day, delivered in 2 to 6 days depending on the PIN code\n- Bengaluru: same-day delivery for orders placed before noon on working days\n- Digital products: a download link arrives by email as soon as payment clears",
				'Delivery charges are shown at checkout before you pay. Orders over the free delivery threshold ship free.',
				'## Returns',
				'If something is not right, tell us within 7 days of delivery and we will collect it from your door. Items need to be unused and in their original packaging. Refunds go back to the original payment method within 5 working days of the item reaching us; cash on delivery orders are refunded by bank transfer.',
				'## Damaged or wrong items',
				'Send a photo within 48 hours of delivery and we replace or refund straight away, including delivery.',
				'## What cannot be returned',
				"- Digital downloads once the file has been downloaded\n- Items made to order or personalised",
			]
		);
	}

	private function terms(): string {
		return $this->paragraphs(
			[
				'These terms apply to every order placed on this store. Placing an order means you accept them.',
				'## Prices and payment',
				'Prices include GST and are shown in Indian rupees. Payment is taken when you place the order, by UPI, card, netbanking or wallet through our payment partners, or in cash on delivery where offered. We do not store card details.',
				'## Orders',
				'An order is accepted when we send the confirmation email. We may cancel an order before dispatch if an item is unavailable or a price was listed in error; you receive a full refund.',
				'## Delivery and risk',
				'Delivery estimates are given in good faith. Risk in the goods passes to you on delivery.',
				'## Returns and refunds',
				'Our returns policy forms part of these terms. Statutory rights are not affected.',
				'## Governing law',
				'These terms are governed by the laws of India. Disputes are subject to the courts of the city where the store is registered.',
			]
		);
	}

	private function privacy(): string {
		return $this->paragraphs(
			[
				'We collect only what is needed to take your order, deliver it and support you afterwards.',
				'## What we collect',
				"- Your name, email address, phone number and delivery address when you order or create an account\n- Your PIN code or shared location when you check whether we deliver to you\n- Order history and messages you send us",
				'## How we use it',
				'To process and deliver orders, to answer your questions, to send order updates and, if you opt in, occasional news about new products. We never sell personal data.',
				'## Who else sees it',
				'Payment partners process your payment on their own secure pages; we never see card numbers. Delivery partners receive your name, address and phone number to deliver the parcel.',
				'## Your choices',
				'You can see and change your details in your account, ask us to delete your account, and unsubscribe from news at any time. Cookies are used to keep your bag and sign-in working and to remember your delivery area.',
			]
		);
	}

	private function postChoosing(): string {
		return $this->paragraphs(
			[
				'Every few weeks a parcel of samples arrives at the studio and most of it goes back. Here is what the few things that stay have in common.',
				'## It has to earn its place',
				'We ask one question first: would we miss it if it broke? A mug that pours cleanly, a notebook that opens flat, a tee with a collar that does not curl. If the answer is no, the price does not matter.',
				'## We meet the maker',
				'Before anything goes on sale we have visited the workshop or had a long call with the person who runs it. We want to know how it is made, how workers are paid and what happens to seconds.',
				'## Small runs, honest stock',
				'We order in small batches and say so. Sold out means sold out, not a marketing trick, and the restock date on the page is the real one.',
			]
		);
	}

	private function postCare(): string {
		return $this->paragraphs(
			[
				'Stoneware is tougher than it looks, and a little care keeps it looking new for years.',
				'## Everyday use',
				'Dishwasher is fine on a normal cycle. Avoid sudden changes of temperature: do not pour boiling water into a mug straight from the fridge, and let it cool before washing.',
				'## Stains',
				'Tea and coffee marks lift with a paste of baking soda and a little water, left for ten minutes and rinsed. Skip abrasive pads, which dull the glaze.',
				'## Chips',
				'A chipped rim is a safety issue more than a cosmetic one. If a mug chips within the first year through normal use, tell us and we replace it.',
			]
		);
	}
}
