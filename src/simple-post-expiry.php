<?php
/**
 * Plugin Name:       Simple Post Expiry
 * Description:       指定した公開期限を過ぎた投稿を自動的に下書きへ戻します。
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      7.4
 * Text Domain:       simple-post-expiry
 * License:           MIT
 * License URI:       https://opensource.org/license/mit/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles expiry settings and scheduled status changes for posts.
 */
final class Simple_Post_Expiry {
	const VERSION         = '1.0.0';
	const META_ENABLED    = '_simple_post_expiry_enabled';
	const META_EXPIRES_AT = '_simple_post_expiry_at';
	const CRON_HOOK       = 'simple_post_expiry_expire_post';
	const NONCE_ACTION    = 'simple_post_expiry_save';
	const NONCE_NAME      = 'simple_post_expiry_nonce';

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save_meta_box' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'sync_on_status_change' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'clear_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'expire_post' ) );
	}

	/**
	 * Register private post meta used by the plugin.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			'post',
			self::META_ENABLED,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			'post',
			self::META_EXPIRES_AT,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => false,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * Check whether the current user can edit registered meta.
	 *
	 * @param bool   $allowed Whether access is already allowed.
	 * @param string $meta_key Meta key being accessed.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Add the expiry settings meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'simple-post-expiry',
			__( '公開期限', 'simple-post-expiry' ),
			array( __CLASS__, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render expiry settings.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$enabled    = (bool) get_post_meta( $post->ID, self::META_ENABLED, true );
		$expires_at = absint( get_post_meta( $post->ID, self::META_EXPIRES_AT, true ) );
		$date_value = $expires_at ? wp_date( 'Y-m-d\TH:i', $expires_at, wp_timezone() ) : '';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="simple-post-expiry-enabled">
				<input
					type="checkbox"
					id="simple-post-expiry-enabled"
					name="simple_post_expiry_enabled"
					value="1"
					<?php checked( $enabled ); ?>
				>
				<?php esc_html_e( '公開期限を設定する', 'simple-post-expiry' ); ?>
			</label>
		</p>
		<p>
			<label for="simple-post-expiry-at">
				<?php esc_html_e( '終了日時', 'simple-post-expiry' ); ?>
			</label>
			<input
				type="datetime-local"
				id="simple-post-expiry-at"
				name="simple_post_expiry_at"
				value="<?php echo esc_attr( $date_value ); ?>"
				class="widefat"
				step="60"
			>
		</p>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: WordPress site timezone. */
					__( 'タイムゾーン: %s。期限後、投稿は下書きへ戻ります。', 'simple-post-expiry' ),
					wp_timezone_string()
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Load the small admin-side form helper only on post edit screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'simple-post-expiry-admin',
			plugins_url( 'assets/admin.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);
	}

	/**
	 * Save expiry settings from the post editor.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an update.
	 * @return void
	 */
	public static function save_meta_box( $post_id, $post, $update ) {
		unset( $update );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || 'post' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['simple_post_expiry_enabled'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['simple_post_expiry_enabled'] ) );

		if ( ! $enabled ) {
			delete_post_meta( $post_id, self::META_ENABLED );
			delete_post_meta( $post_id, self::META_EXPIRES_AT );
			self::clear_schedule( $post_id );
			return;
		}

		$date_value = isset( $_POST['simple_post_expiry_at'] )
			? sanitize_text_field( wp_unslash( $_POST['simple_post_expiry_at'] ) )
			: '';
		$expires_at = self::parse_local_datetime( $date_value );

		if ( ! $expires_at ) {
			delete_post_meta( $post_id, self::META_ENABLED );
			delete_post_meta( $post_id, self::META_EXPIRES_AT );
			self::clear_schedule( $post_id );
			return;
		}

		update_post_meta( $post_id, self::META_ENABLED, 1 );
		update_post_meta( $post_id, self::META_EXPIRES_AT, $expires_at );
		self::sync_schedule( $post_id );
	}

	/**
	 * Keep the schedule in sync when a post's status changes.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function sync_on_status_change( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );

		if ( 'post' === $post->post_type ) {
			self::sync_schedule( $post->ID );
		}
	}

	/**
	 * Move an expired published post back to draft.
	 *
	 * @param int $post_id Post ID supplied by WP-Cron.
	 * @return void
	 */
	public static function expire_post( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! get_post_meta( $post_id, self::META_ENABLED, true ) ) {
			return;
		}

		$expires_at = absint( get_post_meta( $post_id, self::META_EXPIRES_AT, true ) );

		if ( ! $expires_at ) {
			delete_post_meta( $post_id, self::META_ENABLED );
			return;
		}

		if ( $expires_at > time() ) {
			self::schedule_event( $post_id, $expires_at );
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			delete_post_meta( $post_id, self::META_ENABLED );
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			delete_post_meta( $post_id, self::META_ENABLED );
			return;
		}

		// Mark the one-time expiry as consumed before changing status to avoid re-scheduling.
		delete_post_meta( $post_id, self::META_ENABLED );

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, self::META_ENABLED, 1 );
			self::schedule_event( $post_id, time() + ( 5 * MINUTE_IN_SECONDS ) );
			return;
		}

		/**
		 * Fires after a post is successfully returned to draft.
		 *
		 * @param int $post_id Post ID.
		 * @param int $expires_at Expiry time as a UTC Unix timestamp.
		 */
		do_action( 'simple_post_expiry_post_expired', $post_id, $expires_at );
	}

	/**
	 * Re-create schedules when the plugin is activated.
	 *
	 * @return void
	 */
	public static function activate() {
		$post_ids = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => self::META_ENABLED,
				'meta_value'             => '1',
			)
		);

		foreach ( $post_ids as $post_id ) {
			self::sync_schedule( $post_id );
		}
	}

	/**
	 * Remove all pending plugin events when deactivated.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_unschedule_hook( self::CRON_HOOK );
	}

	/**
	 * Synchronize a post's single event with its current settings and status.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function sync_schedule( $post_id ) {
		$post_id = absint( $post_id );
		self::clear_schedule( $post_id );

		if ( ! $post_id || 'post' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		if ( ! get_post_meta( $post_id, self::META_ENABLED, true ) ) {
			return;
		}

		$expires_at = absint( get_post_meta( $post_id, self::META_EXPIRES_AT, true ) );

		if ( $expires_at ) {
			self::schedule_event( $post_id, $expires_at );
		}
	}

	/**
	 * Schedule one expiry event.
	 *
	 * @param int $post_id Post ID.
	 * @param int $timestamp UTC Unix timestamp.
	 * @return void
	 */
	private static function schedule_event( $post_id, $timestamp ) {
		self::clear_schedule( $post_id );
		wp_schedule_single_event( max( time() + 1, absint( $timestamp ) ), self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Remove all expiry events for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function clear_schedule( $post_id ) {
		$post_id = absint( $post_id );

		if ( $post_id ) {
			wp_clear_scheduled_hook( self::CRON_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Convert a datetime-local value in the site timezone to a Unix timestamp.
	 *
	 * @param string $value Value in Y-m-d\TH:i format.
	 * @return int
	 */
	private static function parse_local_datetime( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}

		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();

		if ( false === $date ) {
			return 0;
		}

		if ( false !== $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) {
			return 0;
		}

		if ( $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			return 0;
		}

		return $date->getTimestamp();
	}
}

Simple_Post_Expiry::init();

register_activation_hook( __FILE__, array( 'Simple_Post_Expiry', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Simple_Post_Expiry', 'deactivate' ) );
