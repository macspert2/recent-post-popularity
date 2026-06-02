<?php
/**
 * Activation routine: create table, seed meta, schedule cron.
 *
 * @package RecentPostPopularity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class RPP_Activator {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_table();
		self::schedule_cron();
		self::seed_meta();

		update_option( RPP_VERSION_OPTION, RPP_VERSION );
	}

	/**
	 * Create the per-day hits table with dbDelta().
	 *
	 * @return void
	 */
	private static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . RPP_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta is picky about formatting: two spaces after PRIMARY KEY, etc.
		$sql = "CREATE TABLE {$table} (
			post_id BIGINT(20) UNSIGNED NOT NULL,
			day DATE NOT NULL,
			hits INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (post_id, day),
			KEY day (day)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Schedule the daily aggregation cron if not already scheduled.
	 *
	 * @return void
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( RPP_CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RPP_CRON_HOOK );
		}
	}

	/**
	 * Seed the 'views' meta to 0 for all published posts of the tracked
	 * post types, so the whole catalogue is rankable from day one.
	 *
	 * Only adds the meta when absent, to avoid clobbering on reactivation.
	 *
	 * @return void
	 */
	private static function seed_meta() {
		$ids = get_posts(
			array(
				'post_type'        => RPP_POST_TYPES,
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'posts_per_page'   => -1,
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);

		foreach ( $ids as $id ) {
			// add_post_meta with $unique = true is a no-op if the meta already exists,
			// so existing counts are preserved across reactivation.
			add_post_meta( $id, RPP_META_KEY, 0, true );
		}
	}
}
