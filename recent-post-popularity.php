<?php
/**
 * Plugin Name:       Recent Post Popularity
 * Plugin URI:        https://example.com/recent-post-popularity
 * Description:       Measures recent (rolling ~90-day) post popularity without Jetpack/WordPress.com Stats. Writes each post's rolling view total to post meta ('views'), refreshed daily by cron.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Recent Post Popularity
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       recent-post-popularity
 *
 * @package RecentPostPopularity
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Configuration constants
 * ---------------------------------------------------------------------------
 */
define( 'RPP_VERSION', '1.0.0' );
define( 'RPP_TABLE', 'rpp_post_hits' );      // Actual name: $wpdb->prefix . RPP_TABLE.
define( 'RPP_WINDOW_DAYS', 90 );             // Rolling window summed into meta (tunable 60-90).
define( 'RPP_RETAIN_DAYS', 100 );            // Keep a small buffer beyond the window before pruning.
define( 'RPP_META_KEY', 'views' );           // Required by spec; see collision note in readme.
define( 'RPP_POST_TYPES', array( 'post' ) ); // Which post types are counted/ranked.
define( 'RPP_REST_NS', 'rpp/v1' );
define( 'RPP_CRON_HOOK', 'rpp_daily_event' );
define( 'RPP_VERSION_OPTION', 'rpp_version' );
define( 'RPP_SNAPSHOT_TABLE', 'rpp_monthly_snapshots' );

define( 'RPP_PLUGIN_FILE', __FILE__ );
define( 'RPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * ---------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------------
 */
require_once RPP_PLUGIN_DIR . 'includes/class-rpp-activator.php';
require_once RPP_PLUGIN_DIR . 'includes/class-rpp-deactivator.php';
require_once RPP_PLUGIN_DIR . 'includes/class-rpp-counter.php';
require_once RPP_PLUGIN_DIR . 'includes/class-rpp-aggregator.php';
require_once RPP_PLUGIN_DIR . 'includes/class-rpp-snapshotter.php';

/*
 * ---------------------------------------------------------------------------
 * Lifecycle hooks
 * ---------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'RPP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RPP_Deactivator', 'deactivate' ) );

/*
 * ---------------------------------------------------------------------------
 * Hook wiring
 * ---------------------------------------------------------------------------
 */

// View counting: enqueue beacon + register REST endpoint.
add_action( 'wp_enqueue_scripts', array( 'RPP_Counter', 'enqueue_beacon' ) );
add_action( 'rest_api_init', array( 'RPP_Counter', 'register_routes' ) );

// Daily aggregation cron callback.
add_action( RPP_CRON_HOOK, array( 'RPP_Aggregator', 'run' ) );
