<?php
/**
 * View counting: enqueue the beacon, register the REST endpoint, and perform
 * the atomic per-day increment.
 *
 * @package RecentPostPopularity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles client-side beacon enqueue and the REST hit endpoint.
 */
class RPP_Counter {

	/**
	 * Enqueue the beacon script on singular views of tracked post types,
	 * for non-editor visitors only.
	 *
	 * @return void
	 */
	public static function enqueue_beacon() {
		if ( ! is_singular( RPP_POST_TYPES ) ) {
			return;
		}

		// Don't count editors/admins, so own visits don't pollute rankings.
		if ( current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		wp_enqueue_script(
			'rpp-beacon',
			RPP_PLUGIN_URL . 'assets/js/beacon.js',
			array(),
			RPP_VERSION,
			true // In footer.
		);

		// Build the post-specific beacon URL server-side. It's identical for
		// every visitor of a given cached page, so it stays cache-safe.
		$url = rest_url( RPP_REST_NS . '/hit?post_id=' . $post_id );

		wp_localize_script(
			'rpp-beacon',
			'rppBeacon',
			array( 'url' => $url )
		);
	}

	/**
	 * Register the REST route used by the beacon.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			RPP_REST_NS,
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'record_hit' ),
				'permission_callback' => '__return_true', // Public; this is a view counter.
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Record a single hit for a post on today's date.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function record_hit( $request ) {
		global $wpdb;

		$post_id = absint( $request->get_param( 'post_id' ) );

		// Validate target: must exist, be published, and be a tracked type.
		$post = $post_id ? get_post( $post_id ) : null;
		if (
			! $post
			|| 'publish' !== $post->post_status
			|| ! in_array( $post->post_type, RPP_POST_TYPES, true )
		) {
			// Never reveal detail; the beacon ignores the response anyway.
			return new WP_REST_Response( null, 204 );
		}

		$table = $wpdb->prefix . RPP_TABLE;
		$today = current_time( 'Y-m-d' );

		// Atomic increment: relies on PRIMARY KEY (post_id, day).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, day, hits) VALUES (%d, %s, 1)
				 ON DUPLICATE KEY UPDATE hits = hits + 1",
				$post_id,
				$today
			)
		);

		return new WP_REST_Response( null, 204 );
	}
}
