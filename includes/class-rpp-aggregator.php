<?php
/**
 * Daily aggregation: prune old rows, recompute the rolling window, and sync
 * the result into post meta.
 *
 * @package RecentPostPopularity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron callback that maintains the 'views' meta.
 */
class RPP_Aggregator {

	/**
	 * Run the full daily aggregation.
	 *
	 * @return void
	 */
	public static function run() {
		global $wpdb;

		$table = $wpdb->prefix . RPP_TABLE;
		$now   = current_time( 'timestamp' );

		// 1. Prune rows older than the retention buffer. Cutoff computed in PHP
		//    (site timezone), not via MySQL CURDATE(), to avoid tz off-by-one.
		$prune_before = gmdate( 'Y-m-d', $now - RPP_RETAIN_DAYS * DAY_IN_SECONDS );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE day < %s",
				$prune_before
			)
		);

		// 2. Compute the rolling window sum per post.
		$window_start = gmdate( 'Y-m-d', $now - RPP_WINDOW_DAYS * DAY_IN_SECONDS );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, SUM(hits) AS recent
				 FROM {$table}
				 WHERE day >= %s
				 GROUP BY post_id",
				$window_start
			),
			ARRAY_A
		);

		$sums = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$sums[ (int) $row['post_id'] ] = (int) $row['recent'];
			}
		}

		// 3a. Sync each post that has recent hits.
		foreach ( $sums as $post_id => $recent ) {
			update_post_meta( $post_id, RPP_META_KEY, $recent );
		}

		// 3b. Zero out posts that fell out of the window, so stale values don't
		//     linger and they remain rankable as least-popular.
		$existing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				RPP_META_KEY
			)
		);

		foreach ( $existing as $pid ) {
			$pid = (int) $pid;
			if ( ! isset( $sums[ $pid ] ) ) {
				update_post_meta( $pid, RPP_META_KEY, 0 );
			}
		}

		// 4. Monthly snapshot — write once per calendar month if not yet recorded.
		RPP_Snapshotter::maybe_snapshot();
	}
}
