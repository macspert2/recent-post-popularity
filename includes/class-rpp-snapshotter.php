<?php
/**
 * Monthly snapshot: once per calendar month, write each post's current rolling
 * view total into a permanent history table.
 *
 * @package RecentPostPopularity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes the monthly view snapshot, called at the end of the daily aggregation.
 */
class RPP_Snapshotter {

	/**
	 * Write a monthly snapshot if one has not yet been recorded for the current
	 * calendar month.
	 *
	 * Idempotent: the PRIMARY KEY (post_id, snapshot_month) prevents duplicates
	 * even if called multiple times in the same month.
	 *
	 * @param array $sums     Map of post_id => rolling view count (posts with hits > 0).
	 * @param array $existing Flat list of post_ids that carry the 'views' meta (includes zeros).
	 * @return void
	 */
	public static function maybe_snapshot( array $sums, array $existing ) {
		global $wpdb;

		$snapshot_table = $wpdb->prefix . RPP_SNAPSHOT_TABLE;
		$month          = gmdate( 'Y-m-01', current_time( 'timestamp' ) );

		// Cheap check: has any snapshot been written for this month already?
		$already_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$snapshot_table} WHERE snapshot_month = %s LIMIT 1",
				$month
			)
		);

		if ( $already_done > 0 ) {
			return;
		}

		// Build the full post_id => views map, including zeros for posts that
		// have the meta but no hits in the current window.
		$all_views = $sums; // already int => int.
		foreach ( $existing as $pid ) {
			$pid = (int) $pid;
			if ( ! isset( $all_views[ $pid ] ) ) {
				$all_views[ $pid ] = 0;
			}
		}

		if ( empty( $all_views ) ) {
			return;
		}

		// Bulk-insert via a single VALUES list for efficiency.
		// INSERT IGNORE so a re-run never overwrites an existing month's data.
		$placeholders = array();
		$values       = array();
		foreach ( $all_views as $post_id => $views ) {
			$placeholders[] = '(%d, %s, %d)';
			$values[]       = $post_id;
			$values[]       = $month;
			$values[]       = $views;
		}

		$sql = "INSERT IGNORE INTO {$snapshot_table} (post_id, snapshot_month, views) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built safely above.
		$wpdb->query( $wpdb->prepare( $sql, $values ) );
	}
}
