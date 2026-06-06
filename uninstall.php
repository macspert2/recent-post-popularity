<?php
/**
 * Uninstall routine: drop the hits table, delete all 'views' meta, and remove
 * the version option.
 *
 * @package RecentPostPopularity
 */

// Bail if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Mirror the constants from the main file (not loaded during uninstall).
$rpp_table          = $wpdb->prefix . 'rpp_post_hits';
$rpp_snapshot_table = $wpdb->prefix . 'rpp_monthly_snapshots';
$rpp_meta_key       = 'views';

// Drop the per-day hits table and the monthly snapshots table.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be parameterized.
$wpdb->query( "DROP TABLE IF EXISTS {$rpp_table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$rpp_snapshot_table}" );

// Delete all 'views' meta.
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $rpp_meta_key ) );

// Remove the stored version option.
delete_option( 'rpp_version' );
