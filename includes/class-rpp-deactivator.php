<?php
/**
 * Deactivation routine: unschedule cron. Data is preserved.
 *
 * @package RecentPostPopularity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
class RPP_Deactivator {

	/**
	 * Run on deactivation.
	 *
	 * Clears the scheduled cron only. The hits table and 'views' meta are
	 * intentionally left intact so data survives a deactivate/reactivate.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( RPP_CRON_HOOK );
	}
}
