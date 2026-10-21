<?php
/**
 * Lightweight behavior checks that run without loading WordPress.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

$spe_posts    = array();
$spe_meta     = array();
$spe_events   = array();
$spe_actions  = array();
$spe_failures = array();

function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_timezone() {
	return new DateTimeZone( 'Asia/Tokyo' );
}

function get_post_meta( $post_id, $key, $single ) {
	global $spe_meta;
	unset( $single );

	return isset( $spe_meta[ $post_id ][ $key ] ) ? $spe_meta[ $post_id ][ $key ] : '';
}

function update_post_meta( $post_id, $key, $value ) {
	global $spe_meta;
	$spe_meta[ $post_id ][ $key ] = $value;

	return true;
}

function delete_post_meta( $post_id, $key ) {
	global $spe_meta;
	unset( $spe_meta[ $post_id ][ $key ] );

	return true;
}

function get_post( $post_id ) {
	global $spe_posts;

	return isset( $spe_posts[ $post_id ] ) ? $spe_posts[ $post_id ] : null;
}

function get_post_type( $post_id ) {
	$post = get_post( $post_id );

	return $post ? $post->post_type : false;
}

function get_post_status( $post_id ) {
	$post = get_post( $post_id );

	return $post ? $post->post_status : false;
}

function wp_clear_scheduled_hook( $hook, $args ) {
	global $spe_events;

	$spe_events = array_values(
		array_filter(
			$spe_events,
			function ( $event ) use ( $hook, $args ) {
				return $event['hook'] !== $hook || $event['args'] !== $args;
			}
		)
	);

	return true;
}

function wp_schedule_single_event( $timestamp, $hook, $args ) {
	global $spe_events;
	$spe_events[] = array(
		'timestamp' => $timestamp,
		'hook'      => $hook,
		'args'      => $args,
	);

	return true;
}

function wp_update_post( $data, $wp_error ) {
	global $spe_posts;
	unset( $wp_error );

	$spe_posts[ $data['ID'] ]->post_status = $data['post_status'];

	return $data['ID'];
}

function is_wp_error() {
	return false;
}

function do_action( $hook, ...$args ) {
	global $spe_actions;
	$spe_actions[] = array(
		'hook' => $hook,
		'args' => $args,
	);
}

function assert_same( $expected, $actual, $message ) {
	global $spe_failures;

	if ( $expected !== $actual ) {
		$spe_failures[] = sprintf(
			'%s (expected %s, got %s)',
			$message,
			var_export( $expected, true ),
			var_export( $actual, true )
		);
	}
}

require dirname( __DIR__ ) . '/simple-post-expiry.php';

$parse_method = new ReflectionMethod( 'Simple_Post_Expiry', 'parse_local_datetime' );
$parse_method->setAccessible( true );

$valid_value = '2026-10-21T12:34';
$expected    = ( new DateTimeImmutable( $valid_value, new DateTimeZone( 'Asia/Tokyo' ) ) )->getTimestamp();

assert_same( $expected, $parse_method->invoke( null, $valid_value ), 'Valid local datetime is converted to UTC timestamp' );
assert_same( 0, $parse_method->invoke( null, '2026-02-30T12:34' ), 'Invalid calendar date is rejected' );
assert_same( 0, $parse_method->invoke( null, '' ), 'Empty datetime is rejected' );

$post_id                = 42;
$future                 = time() + 3600;
$spe_posts[ $post_id ]  = (object) array(
	'ID'          => $post_id,
	'post_type'   => 'post',
	'post_status' => 'publish',
);
$spe_meta[ $post_id ]   = array(
	Simple_Post_Expiry::META_ENABLED    => 1,
	Simple_Post_Expiry::META_EXPIRES_AT => $future,
);
$sync_method            = new ReflectionMethod( 'Simple_Post_Expiry', 'sync_schedule' );
$sync_method->setAccessible( true );

$sync_method->invoke( null, $post_id );
$sync_method->invoke( null, $post_id );

assert_same( 1, count( $spe_events ), 'Schedule synchronization does not create duplicate events' );
assert_same( $future, $spe_events[0]['timestamp'], 'Future expiry uses the saved timestamp' );

$past = time() - 60;
$spe_meta[ $post_id ][ Simple_Post_Expiry::META_EXPIRES_AT ] = $past;
Simple_Post_Expiry::expire_post( $post_id );

assert_same( 'draft', $spe_posts[ $post_id ]->post_status, 'Expired published post is returned to draft' );
assert_same( '', get_post_meta( $post_id, Simple_Post_Expiry::META_ENABLED, true ), 'One-time expiry is disabled after execution' );
assert_same( $past, get_post_meta( $post_id, Simple_Post_Expiry::META_EXPIRES_AT, true ), 'Expiry timestamp is retained after execution' );
assert_same( 'simple_post_expiry_post_expired', $spe_actions[0]['hook'], 'Successful expiry action is fired' );

if ( $spe_failures ) {
	fwrite( STDERR, implode( PHP_EOL, $spe_failures ) . PHP_EOL );
	exit( 1 );
}

echo "All Simple Post Expiry behavior checks passed." . PHP_EOL;
