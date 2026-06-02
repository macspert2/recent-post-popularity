=== Recent Post Popularity ===
Contributors: recentpostpopularity
Tags: popularity, views, statistics, post views, ranking
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Measures recent (rolling ~90-day) post popularity without Jetpack/WordPress.com Stats. Exposes each post's rolling view total as standard post meta.

== Description ==

Recent Post Popularity measures how much each post has been read over a rolling
~90-day window. Absolute accuracy does not matter; only the **relative** standing
between posts matters. The window is rolling, so the measure naturally decays:
old activity drops out and the plugin never accumulates a lifetime total.

The plugin's only output is a single piece of post meta:

* Each post's rolling 90-day view total is written to `wp_postmeta` under
  `meta_key = 'views'` (integer), refreshed once per day by a cron job — **not**
  on every view.

= How it works =

* **Per-view counting that survives full-page caching.** Instead of incrementing
  a counter in PHP (which never runs for a cached HTML hit), the plugin enqueues a
  tiny script that fires a `navigator.sendBeacon()` POST to an uncached REST
  endpoint on every real page view. As a side benefit this ignores most bots,
  since they don't run JavaScript.
* **Daily buckets, not a running total.** One row per post per day is stored in a
  custom table. Recent popularity is the sum of the last N days; rolling behaviour
  is automatic because old days are pruned.
* **Meta synced by cron.** `wp_postmeta.views` is updated only during the daily
  aggregation, keeping the hot path (the increment) a single atomic write.

= Privacy =

No personal data, no IP storage, no cookies. GDPR-light by design.

== Consuming the data ==

The plugin ships no template tags, shortcodes, widgets, or blocks. Recent
popularity is available as standard post meta under the key `views`. For example,
to list the ten most-read posts of the recent window:

`
$q = new WP_Query( array(
    'post_type'      => 'post',
    'meta_key'       => 'views',
    'orderby'        => 'meta_value_num', // numeric sort
    'order'          => 'DESC',
    'posts_per_page' => 10,
) );
`

Note: only posts that have the `views` meta appear when ordering by it. On
activation the plugin seeds every existing published post to `0`, so none are
missing from the ranking.

== Notes and caveats ==

* **`meta_key = 'views'` collision.** The key `views` is generic and may clash
  with a theme or another plugin that uses the same key. It is required by this
  plugin's specification, so it is used as-is — but be aware of the possibility.
  If a clash appears, the key is a one-line change to the `RPP_META_KEY` constant
  in the main plugin file.
* **Cache compatibility.** Counting happens via a beacon to an uncached REST
  route, so page caching does not suppress counts. Make sure your page cache / CDN
  does **not** cache the REST endpoint (`/wp-json/rpp/v1/hit`).
* **Counts forward only.** There is no historical backfill. Rankings become
  meaningful once a few weeks of data have accrued.
* **Editors excluded.** Logged-in users who can `edit_posts` are not counted, so
  your own visits don't pollute the rankings.
* **Per language / per post_id.** Counting is strictly per `post_id`. On a
  multilingual (e.g. Polylang) site each translation is its own post and is ranked
  independently.

== Frequently Asked Questions ==

= Where is the data stored? =

Daily hit buckets live in a custom table `{prefix}rpp_post_hits`. The rolling
window total is written to standard post meta under the key `views`.

= What happens when I deactivate? =

The scheduled cron event is cleared, but the table and the `views` meta are
preserved, so no data is lost on deactivate/reactivate.

= What happens when I uninstall (delete) the plugin? =

The custom table is dropped, all `views` post meta is deleted, and the version
option is removed.

== Changelog ==

= 1.0.0 =
* Initial release: beacon-based view counting, daily rolling-window aggregation
  into the `views` post meta, daily pruning of old data.
