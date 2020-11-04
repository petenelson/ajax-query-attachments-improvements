<?php
/**
 * Plugin Name: AJAX Query Attachments Performance
 * Description: Updates the AJAX attachment queries with performance improvements.
 * Version:     1.0.0
 * Author:      Pete Nelson
 * Author URI:  https://petenelson.io
 * License:     GPLv2 or later
 *
 * @package PeteNelson\AJAXQueryAttachmentsPerformance
 */

namespace PeteNelson\AJAXQueryAttachmentsPerformance;

/**
 * Quickly provide a namespaced way to get functions.
 *
 * @param string $function Name of function in namespace.
 *
 * @return string
 */
function n( $function ) {
	return __NAMESPACE__ . "\\$function";
}

/**
 * Setup WordPress hooks and filters.
 *
 * @return void
 */
function setup() {
	add_filter( 'ajax_query_attachments_args', n( 'update_ajax_args' ) );
	add_action( 'clean_post_cache', n( 'update_attachments_last_changed' ), 10, 2 );
	add_filter( 'posts_pre_query', n( 'maybe_use_cached_posts' ), 10, 2 );
}
add_action( 'init', n( 'setup' ) );

/**
 * Updates the AJAX attachment query args for better performance.
 *
 * @param  array $query_args List of query args.
 * @return array
 */
function update_ajax_args( $query_args ) {

	$query_args['fields']                 = 'ids';
	$query_args['update_post_meta_cache'] = false;
	$query_args['update_term_meta_cache'] = false;
	$query_args['no_found_rows']          = true;

	// Flag this for caching the results.
	$query_args['cache_ajax_query']       = true;

	return $query_args;
}

/**
 * Updates the last changed cache time for attachments.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function update_attachments_last_changed( $post_id, $post ) {
	if ( 'attachment' === $post->post_type ) {
		get_attachments_last_changed( true );
	}
}

/**
 * Gets the time any attachment was last changed, used for invalidating
 * the cache.
 *
 * @param bool $update Should we force an update to the time?
 * @return string
 */
function get_attachments_last_changed( $update = false ) {
	$last_changed = wp_cache_get( get_last_changed_key(), get_cache_group() );
	if ( false === $last_changed || $update ) {
		$last_changed = time();
		wp_cache_set( get_last_changed_key(), $last_changed, get_cache_group(), HOUR_IN_SECONDS * 12 );
	}

	return (string) $last_changed;
}

/**
 * Gets the cache key for caching the AJAX results.
 *
 * @param  WP_Query $query The query object.
 * @return string
 */
function get_cache_key( $query ) {
	return 'cached_query_' . md5( wp_json_encode( $query->query_vars ) . get_attachments_last_changed() );
}

/**
 * Gets the cache group.
 *
 * @return string
 */
function get_cache_group() {
	return 'wp_ajax_query_attachments';
}

/**
 * Gets the cache key used for tracking attachments last changed.
 *
 * @return string
 */
function get_last_changed_key() {
	return 'attachments_last_changed';
}

/**
 * Uses the object cache to cache AJAX attachment query results.
 *
 * @param  array    $posts List of posts.
 * @param  WP_Query $query The query object.
 * @return array
 */
function maybe_use_cached_posts( $posts, $query ) {

	$cache_ajax_query = $query->get( 'cache_ajax_query' );
	if ( $cache_ajax_query ) {

		remove_filter( 'posts_pre_query', n( 'maybe_use_cached_posts' ) );

		$cache_key  = get_cache_key( $query );
		$cached_ids = wp_cache_get( $cache_key, get_cache_group() );

		if ( false !== $cached_ids ) {
			return $cached_ids;
		} else {

			// Run the query and cache the post IDs.
			$posts = $query->get_posts();

			wp_cache_set( $cache_key, $posts, get_cache_group(), HOUR_IN_SECONDS * 12 );
		}
	}

	return $posts;
}
