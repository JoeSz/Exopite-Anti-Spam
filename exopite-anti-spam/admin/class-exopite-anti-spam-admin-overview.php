<?php
/**
 * Admin page "Anti Spam": quarantine and statistics.
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/admin
 */

/**
 * The page "Anti Spam" in the Contact Form 7 menu with the tabs "Quarantine" and "Statistics".
 * The settings stay on the "Blacklist" page and in the "Anti Spam" tab of the forms.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/admin
 */
class Exopite_Anti_Spam_Admin_Overview {

	/**
	 * Menu slug of the page.
	 */
	const PAGE = 'exopite-anti-spam-overview';

	/**
	 * Reason codes of spam (bot) submissions.
	 */
	const SPAM_CODES = array( 'honeypot', 'timestamp', 'captcha', 'acceptance', 'fields_missing', 'score', 'pow', 'spam_other' );

	/**
	 * Reason codes of validation errors.
	 */
	const INVALID_CODES = array( 'too_fast', 'expired', 'token_used', 'captcha_wrong', 'badwords', 'blacklist', 'ratelimit', 'email_disposable', 'email_domain', 'invalid_other' );

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin main class.
	 *
	 * @var Exopite_Anti_Spam
	 */
	public $main;

	/**
	 * Constructor.
	 *
	 * @param string            $plugin_name The ID of this plugin.
	 * @param Exopite_Anti_Spam $plugin_main Plugin main class.
	 */
	public function __construct( $plugin_name, $plugin_main ) {

		$this->plugin_name = $plugin_name;
		$this->main        = $plugin_main;
	}

	/**
	 * Submenu page in the Contact Form 7 menu. Hook: admin_menu.
	 */
	public function add_menu() {
		global $admin_page_hooks;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Contact Form 7 6.2+ has the top-level menu "wpcf7-dashboard", older versions "wpcf7".
		$parent = isset( $admin_page_hooks['wpcf7-dashboard'] ) ? 'wpcf7-dashboard' : 'wpcf7';

		add_submenu_page( $parent, __( 'Anti Spam', 'exopite-anti-spam' ), __( 'Anti Spam', 'exopite-anti-spam' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * URL of the page.
	 *
	 * @param array $args Query arguments (tab, entry ...).
	 * @return string
	 */
	public function get_url( $args = array() ) {

		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Output the page.
	 */
	public function render() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects the tab.
		$tab  = isset( $_GET['tab'] ) && 'statistics' === $_GET['tab'] ? 'statistics' : 'quarantine';
		$tabs = array(
			'quarantine' => __( 'Quarantine', 'exopite-anti-spam' ),
			'statistics' => __( 'Statistics', 'exopite-anti-spam' ),
		);

		echo '<div class="wrap eas-overview">';
		echo '<h1>' . esc_html__( 'Anti Spam', 'exopite-anti-spam' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $key => $title ) {
			echo '<a href="' . esc_url( $this->get_url( array( 'tab' => $key ) ) ) . '" class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html( $title ) . '</a>';
		}

		echo '</nav>';

		if ( 'statistics' === $tab ) {
			include __DIR__ . '/partials/exopite-anti-spam-admin-statistics.php';
		} else {
			$this->render_quarantine();
		}

		echo '</div>';
	}

	/**
	 * Tab "Quarantine".
	 */
	public function render_quarantine() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only selects what is shown.
		$eas_form_filter = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
		$eas_paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$eas_entry_id    = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$eas_quarantine = $this->main->quarantine;
		$eas_quarantine->maybe_clean_up();

		$this->render_notice();

		$eas_entry = $eas_entry_id ? $eas_quarantine->get_entry( $eas_entry_id ) : null;

		if ( $eas_entry_id && ! $eas_entry ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The entry does not exist anymore.', 'exopite-anti-spam' ) . '</p></div>';
		}

		include __DIR__ . '/partials/exopite-anti-spam-admin-quarantine.php';
	}

	/**
	 * Is the quarantine activated in any form?
	 *
	 * @return bool
	 */
	public function is_quarantine_used() {

		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $forms as $form_id ) {
			if ( $this->main->quarantine->is_active( get_post_meta( $form_id, 'exopite-anti-spam', true ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Small POST form for an action (release, delete, delete all), with nonce and confirmation (admin JS).
	 *
	 * @param string $action      'release', 'delete' or 'delete_all'.
	 * @param int    $id          Entry ID, for 'delete_all' the form ID (0: all forms).
	 * @param int    $form_filter Form filter of the list (for the redirect).
	 * @param string $label       Button text.
	 * @param string $button_class Additional button class.
	 * @param string $confirm     Confirmation question.
	 */
	public function render_action_button( $action, $id, $form_filter, $label, $button_class, $confirm ) {

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eas-quarantine-action" data-eas-confirm="' . esc_attr( $confirm ) . '">';
		echo '<input type="hidden" name="action" value="eas_quarantine">';
		echo '<input type="hidden" name="do" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="id" value="' . esc_attr( (int) $id ) . '">';
		echo '<input type="hidden" name="form_filter" value="' . esc_attr( (int) $form_filter ) . '">';
		wp_nonce_field( 'eas_quarantine_' . $action . '_' . (int) $id );
		echo '<button type="submit" class="button ' . esc_attr( $button_class ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * Release, delete or delete all. Hook: admin_post_eas_quarantine.
	 */
	public function handle_action() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'exopite-anti-spam' ), '', array( 'response' => 403 ) );
		}

		$action      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$form_filter = isset( $_POST['form_filter'] ) ? absint( $_POST['form_filter'] ) : 0;

		if ( ! in_array( $action, array( 'release', 'delete', 'delete_all' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'exopite-anti-spam' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( 'eas_quarantine_' . $action . '_' . $id );

		$quarantine = $this->main->quarantine;
		$type       = 'success';

		if ( 'release' === $action ) {

			$result  = $quarantine->release( $id );
			$type    = $result['ok'] ? 'success' : 'error';
			$message = $result['message'];

		} elseif ( 'delete' === $action ) {

			$message = $quarantine->delete( $id ) ? __( 'The entry was deleted.', 'exopite-anti-spam' ) : __( 'The entry does not exist anymore.', 'exopite-anti-spam' );

		} else {

			$count = $quarantine->delete_all( $id );
			/* translators: %d: number of deleted entries */
			$message = sprintf( _n( '%d entry was deleted.', '%d entries were deleted.', $count, 'exopite-anti-spam' ), $count );
		}

		set_transient(
			'eas_quarantine_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( $this->get_url( array( 'form' => $form_filter ? $form_filter : null ) ) );
		exit;
	}

	/**
	 * Result of the last action (handle_action()), shown once.
	 */
	public function render_notice() {

		$key    = 'eas_quarantine_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$notice_class = 'error' === $notice['type'] ? 'notice-error' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
	}

	/**
	 * Readable label of a reason code.
	 *
	 * @param string $code Reason code, e.g. 'honeypot' or 'released:score'.
	 * @return string
	 */
	public function get_reason_label( $code ) {

		if ( 0 === strpos( $code, 'released:' ) ) {
			/* translators: %s: reason, why the submission was in the quarantine */
			return sprintf( __( 'Released (%s)', 'exopite-anti-spam' ), $this->get_reason_label( substr( $code, 9 ) ) );
		}

		$labels = array(
			'sent'             => __( 'Sent', 'exopite-anti-spam' ),
			'failed'           => __( 'Mail failed', 'exopite-anti-spam' ),
			'aborted'          => __( 'Aborted', 'exopite-anti-spam' ),
			'honeypot'         => __( 'Honeypot filled', 'exopite-anti-spam' ),
			'timestamp'        => __( 'Timestamp missing or manipulated', 'exopite-anti-spam' ),
			'captcha'          => __( 'Image captcha manipulated', 'exopite-anti-spam' ),
			'acceptance'       => __( 'Acceptance token missing or manipulated', 'exopite-anti-spam' ),
			'fields_missing'   => __( 'Sent without the anti spam fields', 'exopite-anti-spam' ),
			'score'            => __( 'Spam score reached', 'exopite-anti-spam' ),
			'pow'              => __( 'Proof-of-work missing or invalid', 'exopite-anti-spam' ),
			'spam_other'       => __( 'Spam (other plugin)', 'exopite-anti-spam' ),
			'too_fast'         => __( 'Sent too fast', 'exopite-anti-spam' ),
			'expired'          => __( 'Session expired', 'exopite-anti-spam' ),
			'token_used'       => __( 'Token error (already used or invalid)', 'exopite-anti-spam' ),
			'captcha_wrong'    => __( 'Wrong image captcha', 'exopite-anti-spam' ),
			'badwords'         => __( 'Bad/spam words', 'exopite-anti-spam' ),
			'blacklist'        => __( 'E-mail or domain blacklisted', 'exopite-anti-spam' ),
			'ratelimit'        => __( 'Too many failed attempts', 'exopite-anti-spam' ),
			'email_disposable' => __( 'Disposable e-mail address', 'exopite-anti-spam' ),
			'email_domain'     => __( 'E-mail domain does not exist', 'exopite-anti-spam' ),
			'invalid_other'    => __( 'Other validation error', 'exopite-anti-spam' ),
			'released'         => __( 'Released from quarantine', 'exopite-anti-spam' ),
		);

		return isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
	}

	/**
	 * Form title with a link to the form editor, "#ID (deleted)" if the form does not exist anymore.
	 *
	 * @param int $form_id Form ID.
	 * @return string HTML.
	 */
	public function get_form_link( $form_id ) {

		$form_id = (int) $form_id;
		$post    = get_post( $form_id );

		if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) {
			/* translators: %d: form ID */
			return esc_html( sprintf( __( '#%d (deleted)', 'exopite-anti-spam' ), $form_id ) );
		}

		$title = '' !== $post->post_title ? $post->post_title : '#' . $form_id;
		$url   = add_query_arg(
			array(
				'page'   => 'wpcf7',
				'post'   => $form_id,
				'action' => 'edit',
			),
			admin_url( 'admin.php' )
		);

		return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
	}
}
