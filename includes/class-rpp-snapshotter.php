<?php
/**
 * Monthly snapshot: on the first daily cron run of a new calendar month,
 * write each post's 3-month calendar-aligned view total for the month that
 * just ended into the permanent history table.
 *
 * Snapshot date key: 2026-06-01
 * Written:           first cron run on or after 2026-07-01
 * Window:            2026-04-01 – 2026-06-30  (3 full calendar months)
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
	 * Write a snapshot for the previous calendar month if one has not yet been
	 * recorded. Self-contained: uses its own calendar-aligned window query so
	 * the result matches the historical import convention exactly.
	 *
	 * Idempotent: PRIMARY KEY (post_id, snapshot_month) + INSERT IGNORE prevent
	 * duplicates even if called multiple times after month rollover.
	 *
	 * @return void
	 */
	public static function maybe_snapshot() {
		global $wpdb;

		$snapshot_table = $wpdb->prefix . RPP_SNAPSHOT_TABLE;
		$hits_table     = $wpdb->prefix . RPP_TABLE;
		$now            = current_time( 'timestamp' );

		// Target: the month that just ended.
		// e.g. if today is anywhere in July 2026, snapshot_month = 2026-06-01.
		$first_of_current_month = gmdate( 'Y-m-01', $now );
		$snapshot_month         = gmdate( 'Y-m-01', strtotime( $first_of_current_month . ' -1 month' ) );

		// Calendar window: 3 full months ending on the last day of snapshot_month.
		// e.g. snapshot_month = 2026-06-01 → window 2026-04-01 to 2026-06-30.
		$window_start = gmdate( 'Y-m-01', strtotime( $snapshot_month . ' -2 months' ) );
		$window_end   = gmdate( 'Y-m-d', strtotime( $first_of_current_month . ' -1 day' ) );

		// Idempotency check: already snapshotted this month?
		$already_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$snapshot_table} WHERE snapshot_month = %s LIMIT 1",
				$snapshot_month
			)
		);

		if ( $already_done > 0 ) {
			return;
		}

		// Query the hits table for the calendar-aligned 3-month window.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, SUM(hits) AS views
				 FROM {$hits_table}
				 WHERE day >= %s AND day <= %s
				 GROUP BY post_id",
				$window_start,
				$window_end
			),
			ARRAY_A
		);

		$sums = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$sums[ (int) $row['post_id'] ] = (int) $row['views'];
			}
		}

		// Zero-pad all tracked posts that had no hits in the window.
		$existing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				RPP_META_KEY
			)
		);
		foreach ( $existing as $pid ) {
			$pid = (int) $pid;
			if ( ! isset( $sums[ $pid ] ) ) {
				$sums[ $pid ] = 0;
			}
		}

		if ( empty( $sums ) ) {
			return;
		}

		// post_id = 0 holds the site-wide total for the month.
		$sums[0] = array_sum( $sums );

		// Bulk-insert via a single VALUES list.
		// INSERT IGNORE: PRIMARY KEY prevents any accidental overwrites.
		$placeholders = array();
		$values       = array();
		foreach ( $sums as $post_id => $views ) {
			$placeholders[] = '(%d, %s, %d)';
			$values[]       = $post_id;
			$values[]       = $snapshot_month;
			$values[]       = $views;
		}

		$sql = "INSERT IGNORE INTO {$snapshot_table} (post_id, snapshot_month, views) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built safely above.
		$wpdb->query( $wpdb->prepare( $sql, $values ) );
	}
}
