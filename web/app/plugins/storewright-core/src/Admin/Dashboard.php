<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use InvalidArgumentException;
use Storewright\Core\Jobs\FailedJobs;
use Storewright\Core\Payments\HealthEndpoint;

final class Dashboard {

	private const PERIODS = [ 'today', 'week', 'month' ];

	public static function register(): void {
		add_action( 'wp_dashboard_setup', [ self::class, 'widgets' ] );
		add_action( 'wp_dashboard_setup', static fn() => remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' ), 99 );
		add_filter( 'get_user_option_meta-box-order_dashboard', [ self::class, 'layout' ] );
		Action::on( 'setup_dismiss', [ self::class, 'dismissSetup' ] );
		Action::on( 'job_retry', [ self::class, 'retryJob' ] );
		Action::on( 'job_dismiss', [ self::class, 'dismissJob' ] );
	}

	public static function widgets(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$setup = SetupChecklist::describe();
		if ( ! $setup['dismissed'] && $setup['done'] < count( $setup['steps'] ) ) {
			wp_add_dashboard_widget( 'storewright_setup', __( 'Getting started', 'storewright' ), static fn() => self::setup( $setup ) );
		}
		wp_add_dashboard_widget( 'storewright_today', __( 'Today', 'storewright' ), [ self::class, 'today' ] );
		wp_add_dashboard_widget( 'storewright_insights', __( 'Insights', 'storewright' ), [ self::class, 'insights' ] );
		wp_add_dashboard_widget( 'storewright_payments', __( 'Payments', 'storewright' ), [ self::class, 'payments' ] );
		wp_add_dashboard_widget( 'storewright_jobs', __( 'Background work', 'storewright' ), [ self::class, 'jobs' ] );
	}

	public static function layout( mixed $order ): mixed {
		if ( is_array( $order ) && $order !== [] ) {
			return $order;
		}
		return [
			'normal' => 'storewright_setup,storewright_today,storewright_insights',
			'side'   => 'storewright_payments,storewright_jobs',
		];
	}

	public static function dismissSetup(): void {
		SetupChecklist::dismiss();
		Action::back( admin_url() );
	}

	public static function retryJob(): void {
		try {
			FailedJobs::retry( Action::query( 'id' ) );
			Flash::success( __( 'The job is queued to run again.', 'storewright' ) );
		} catch ( InvalidArgumentException $e ) {
			Flash::error( $e->getMessage() );
		}
		Action::back( admin_url() );
	}

	public static function dismissJob(): void {
		try {
			FailedJobs::dismiss( Action::query( 'id' ) );
			Flash::success( __( 'The job is dismissed.', 'storewright' ) );
		} catch ( InvalidArgumentException $e ) {
			Flash::error( $e->getMessage() );
		}
		Action::back( admin_url() );
	}

	private static function setup( array $setup ): void {
		/* translators: 1: steps done, 2: all steps */
		echo '<p class="sw-dash__lead">' . esc_html( sprintf( __( '%1$s of %2$s done. Each step opens the screen that finishes it.', 'storewright' ), $setup['done'], count( $setup['steps'] ) ) ) . '</p>';
		echo '<ol class="sw-dash__steps">';
		foreach ( $setup['steps'] as $step ) {
			printf(
				'<li class="%s"><span class="sw-dash__mark" aria-hidden="true"></span><span class="sw-dash__step"><strong>%s</strong><small>%s</small></span>%s</li>',
				$step['done'] ? 'is-done' : '',
				esc_html( $step['title'] ),
				esc_html( $step['detail'] ),
				$step['done'] ? '<span class="sw-dash__state">' . esc_html__( 'Done', 'storewright' ) . '</span>' : '<a href="' . esc_url( $step['url'] ) . '">' . esc_html__( 'Open', 'storewright' ) . '</a>'
			);
		}
		echo '</ol>';
		printf( '<p><a class="sw-dash__quiet" href="%s">%s</a></p>', esc_url( Action::url( 'setup_dismiss' ) ), esc_html__( 'Hide this list', 'storewright' ) );
	}

	public static function today(): void {
		$period = self::period();
		$today  = Today::describe( $period );
		echo '<p class="sw-dash__periods">';
		foreach ( self::PERIODS as $key ) {
			printf( '<a href="%s" class="%s">%s</a>', esc_url( add_query_arg( 'period', $key, admin_url() ) ), $key === $period ? 'is-active' : '', esc_html( self::periodLabel( $key ) ) );
		}
		echo '</p>';
		echo '<div class="sw-dash__kpis">';
		foreach ( $today['kpis'] as $kpi ) {
			printf(
				'<div class="sw-dash__kpi"><span class="sw-dash__kpi-label">%s</span><strong class="sw-dash__kpi-value">%s</strong><span class="sw-dash__kpi-note %s">%s</span></div>',
				esc_html( $kpi['label'] ),
				wp_kses_post( $kpi['value'] ),
				esc_attr( $kpi['tone'] ),
				esc_html( $kpi['note'] )
			);
		}
		echo '</div>';
		echo '<ul class="sw-dash__queues">';
		foreach ( $today['queues'] as $queue ) {
			printf( '<li><a href="%s"><strong>%s</strong> %s</a></li>', esc_url( $queue['url'] ), esc_html( number_format_i18n( $queue['count'] ) ), esc_html( $queue['label'] ) );
		}
		echo '</ul>';
		self::block( __( 'Stock', 'storewright' ), admin_url( 'edit.php?post_type=product&stock_status=outofstock' ), __( 'Out of stock', 'storewright' ) );
		/* translators: 1: low stock count, 2: out of stock count */
		echo '<p>' . esc_html( sprintf( __( '%1$s running low, %2$s out of stock', 'storewright' ), number_format_i18n( $today['stock']['low'] ), number_format_i18n( $today['stock']['out'] ) ) ) . '</p>';
		self::list(
			$today['stock']['items'],
			/* translators: %s: units left */
			static fn( array $item ): array => [ get_edit_post_link( $item['id'] ) ?: '', $item['name'], sprintf( __( '%s left', 'storewright' ), number_format_i18n( $item['quantity'] ) ), '' ],
			''
		);
		self::block( __( 'Best sellers', 'storewright' ) );
		self::list(
			$today['products'],
			/* translators: %s: units sold */
			static fn( array $item ): array => [ get_edit_post_link( $item['id'] ) ?: '', $item['name'], sprintf( __( '%s sold', 'storewright' ), number_format_i18n( $item['sold'] ) ), wc_price( $item['revenue'] ) ],
			__( 'Nothing sold in this period yet.', 'storewright' )
		);
	}

	public static function insights(): void {
		$period   = self::period();
		$range    = Today::range( $period );
		$after    = Insights::gmt( $range['after'] );
		$before   = Insights::gmt( $range['before'] );
		$payments = Insights::payments( $after, $before );
		self::block( __( 'Payment mix', 'storewright' ) );
		self::shares( $payments, static fn( array $row, int $share ): string => wp_strip_all_tags( wc_price( $row['total'] ) ) . ' · ' . $share . '%', __( 'No paid orders in this period yet.', 'storewright' ) );
		$customers = Insights::customers( $after, $before );
		$total     = $customers['new'] + $customers['returning'];
		self::block( __( 'Customers', 'storewright' ) );
		if ( $total === 0 ) {
			echo '<p class="sw-dash__empty">' . esc_html__( 'No paid orders in this period yet.', 'storewright' ) . '</p>';
		} else {
			/* translators: 1: returning customers, 2: share, 3: new customers */
			echo '<p>' . esc_html( sprintf( __( '%1$s returning (%2$s%%), %3$s new', 'storewright' ), number_format_i18n( $customers['returning'] ), (int) round( $customers['returning'] / $total * 100 ), number_format_i18n( $customers['new'] ) ) ) . '</p>';
		}
		self::block( __( 'Where orders go', 'storewright' ) );
		self::shares(
			array_map(
				static fn( array $row ): array => [
					...$row,
					'title' => implode( ', ', array_filter( [ $row['city'], $row['state'] ] ) ),
				],
				Insights::cities( $after, $before )
			),
			/* translators: %s: orders */
			static fn( array $row ): string => sprintf( __( '%s orders', 'storewright' ), number_format_i18n( $row['orders'] ) ),
			__( 'No deliveries in this period yet.', 'storewright' )
		);
		self::block( __( 'Delivery methods', 'storewright' ) );
		self::shares( Insights::delivery( $after, $before ), static fn( array $row, int $share ): string => number_format_i18n( $row['orders'] ) . ' · ' . $share . '%', __( 'No deliveries in this period yet.', 'storewright' ) );
		$returns = Insights::openReturns();
		self::block( __( 'Days of stock', 'storewright' ), $returns > 0 ? admin_url( 'admin.php?page=storewright-returns' ) : '', $returns > 0 ? sprintf( /* translators: %s: open returns */ __( '%s returns open', 'storewright' ), number_format_i18n( $returns ) ) : '' );
		self::list(
			Insights::stock(),
			static fn( array $item ): array => [
				get_edit_post_link( $item['id'] ) ?: '',
				$item['name'],
				/* translators: %s: days */
				sprintf( _n( '%s day left', '%s days left', $item['daysLeft'], 'storewright' ), number_format_i18n( $item['daysLeft'] ) ),
				/* translators: 1: units in stock, 2: units sold per day */
				sprintf( __( '%1$s in stock, about %2$s a day', 'storewright' ), number_format_i18n( $item['quantity'] ), $item['perDay'] ),
			],
			__( 'Nothing with tracked stock has sold in the last 30 days.', 'storewright' ),
			static fn( array $item ): bool => $item['daysLeft'] <= 7
		);
	}

	public static function payments(): void {
		$health = HealthEndpoint::describe();
		$names  = [
			'razorpay' => 'Razorpay',
			'stripe'   => 'Stripe',
		];
		$modes  = [
			'sandbox' => __( 'Test mode', 'storewright' ),
			'live'    => __( 'Live', 'storewright' ),
			'off'     => __( 'Not set up', 'storewright' ),
			'refused' => __( 'Live keys refused outside production', 'storewright' ),
		];
		echo '<ul class="sw-dash__list">';
		foreach ( $health['gateways'] as $gateway ) {
			$trouble = $gateway['failed'] > 0 || $gateway['stuck'] > 0 || $gateway['mode'] === 'refused';
			$detail  = '';
			if ( $gateway['mode'] !== 'off' ) {
				$detail = $gateway['last']
					/* translators: 1: number of events, 2: relative time */
					? sprintf( __( '%1$s events in 24 h, last %2$s ago', 'storewright' ), number_format_i18n( $gateway['day'] ), human_time_diff( (int) strtotime( $gateway['last'] ) ) )
					: __( 'No webhook received yet', 'storewright' );
				if ( $gateway['failed'] > 0 ) {
					/* translators: %s: number of events */
					$detail .= ' · ' . sprintf( __( '%s failed', 'storewright' ), number_format_i18n( $gateway['failed'] ) );
				}
				if ( $gateway['stuck'] > 0 ) {
					/* translators: %s: number of events */
					$detail .= ' · ' . sprintf( __( '%s waiting', 'storewright' ), number_format_i18n( $gateway['stuck'] ) );
				}
			}
			printf(
				'<li class="%s"><strong>%s</strong><span>%s</span><small>%s</small></li>',
				$trouble ? 'is-trouble' : '',
				esc_html( $names[ $gateway['gateway'] ] ?? $gateway['gateway'] ),
				esc_html( $modes[ $gateway['mode'] ] ?? $gateway['mode'] ),
				esc_html( $detail )
			);
		}
		printf( '<li><strong>%s</strong><span>%s</span></li>', esc_html__( 'Cash on delivery', 'storewright' ), $health['cashOnDelivery'] ? esc_html__( 'On', 'storewright' ) : esc_html__( 'Off', 'storewright' ) );
		echo '</ul>';
		printf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'admin.php?page=storewright-payments' ) ), esc_html__( 'Webhooks and unpaid orders', 'storewright' ) );
	}

	public static function jobs(): void {
		$jobs = FailedJobs::summary();
		if ( $jobs['last'] === null ) {
			$state = [ __( 'Has not run yet', 'storewright' ), 'is-quiet', __( 'The runner has not reported in. Check that the cron container is up.', 'storewright' ) ];
		} elseif ( $jobs['stale'] ) {
			/* translators: %s: how long ago */
			$state = [ __( 'Not running', 'storewright' ), 'is-bad', sprintf( __( 'Last run %s ago. Emails, webhooks and stock updates wait until it is back.', 'storewright' ), human_time_diff( $jobs['last'] ) ) ];
		} else {
			/* translators: %s: how long ago */
			$state = [ __( 'Running', 'storewright' ), 'is-good', sprintf( __( 'Last run %s ago.', 'storewright' ), human_time_diff( $jobs['last'] ) ) ];
		}
		printf( '<p><span class="sw-pill %s">%s</span> %s</p>', esc_attr( $state[1] ), esc_html( $state[0] ), esc_html( $state[2] ) );
		/* translators: 1: jobs waiting, 2: jobs overdue */
		echo '<p>' . esc_html( sprintf( __( '%1$s waiting to run, %2$s overdue.', 'storewright' ), number_format_i18n( $jobs['pending'] ), number_format_i18n( $jobs['overdue'] ) ) ) . '</p>';
		self::block( __( 'Failed', 'storewright' ) );
		if ( $jobs['failed'] === [] ) {
			echo '<p class="sw-dash__empty">' . esc_html__( 'Nothing has failed.', 'storewright' ) . '</p>';
			return;
		}
		echo '<ul class="sw-dash__list sw-dash__jobs">';
		foreach ( $jobs['failed'] as $job ) {
			printf(
				'<li><code>%s</code>%s<small>%s</small><span class="sw-dash__actions"><a href="%s">%s</a> <a class="sw-dash__quiet" href="%s">%s</a></span></li>',
				esc_html( $job['hook'] ),
				$job['args'] === [] ? '' : '<small>' . esc_html( (string) wp_json_encode( $job['args'] ) ) . '</small>',
				esc_html( $job['log'] ),
				esc_url( Action::url( 'job_retry', [ 'id' => $job['id'] ] ) ),
				esc_html__( 'Run again', 'storewright' ),
				esc_url( Action::url( 'job_dismiss', [ 'id' => $job['id'] ] ) ),
				esc_html__( 'Dismiss', 'storewright' )
			);
		}
		echo '</ul>';
	}

	private static function period(): string {
		$period = Action::query( 'period' );
		return in_array( $period, self::PERIODS, true ) ? $period : 'today';
	}

	private static function periodLabel( string $period ): string {
		return match ( $period ) {
			'week'  => __( '7 days', 'storewright' ),
			'month' => __( '30 days', 'storewright' ),
			default => __( 'Today', 'storewright' ),
		};
	}

	private static function block( string $title, string $url = '', string $link = '' ): void {
		echo '<h3 class="sw-dash__block">' . esc_html( $title );
		if ( $url !== '' ) {
			printf( ' <a href="%s">%s</a>', esc_url( $url ), esc_html( $link ) );
		}
		echo '</h3>';
	}

	private static function list( array $items, callable $row, string $none, ?callable $trouble = null ): void {
		if ( $items === [] ) {
			if ( $none !== '' ) {
				echo '<p class="sw-dash__empty">' . esc_html( $none ) . '</p>';
			}
			return;
		}
		echo '<ul class="sw-dash__list">';
		foreach ( $items as $item ) {
			[ $url, $name, $note, $extra ] = $row( $item );
			printf(
				'<li class="%s"><a href="%s">%s</a><span>%s</span>%s</li>',
				$trouble && $trouble( $item ) ? 'is-trouble' : '',
				esc_url( $url ),
				esc_html( $name ),
				esc_html( $note ),
				$extra === '' ? '' : '<small>' . wp_kses_post( $extra ) . '</small>'
			);
		}
		echo '</ul>';
	}

	private static function shares( array $rows, callable $label, string $none ): void {
		if ( $rows === [] ) {
			echo '<p class="sw-dash__empty">' . esc_html( $none ) . '</p>';
			return;
		}
		$sum = array_sum( array_column( $rows, 'orders' ) );
		echo '<ul class="sw-dash__shares">';
		foreach ( $rows as $row ) {
			$share = $sum ? (int) round( $row['orders'] / $sum * 100 ) : 0;
			printf(
				'<li><span class="sw-dash__share-name">%s</span><span class="sw-dash__share-value">%s</span><span class="sw-dash__share-bar" aria-hidden="true"><span style="width:%d%%"></span></span></li>',
				esc_html( $row['title'] ),
				esc_html( $label( $row, $share ) ),
				(int) $share
			);
		}
		echo '</ul>';
	}
}
