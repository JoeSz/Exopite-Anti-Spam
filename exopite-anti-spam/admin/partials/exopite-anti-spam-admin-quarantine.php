<?php
/**
 * Tab "Quarantine" of the "Anti Spam" page (included by Exopite_Anti_Spam_Admin_Overview::render_quarantine()).
 * Variables: $eas_quarantine (Exopite_Anti_Spam_Quarantine), $eas_form_filter (int), $eas_entry (array|null), $eas_paged (int).
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/admin/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$eas_date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

if ( $eas_entry ) {

	echo '<p><a href="' . esc_url( $this->get_url( array( 'form' => $eas_form_filter ? $eas_form_filter : null ) ) ) . '">&larr; ' . esc_html__( 'Back to the list', 'exopite-anti-spam' ) . '</a></p>';

	echo '<h2>' . esc_html(
		sprintf(
			/* translators: 1: entry ID, 2: date */
			__( 'Entry #%1$d from %2$s', 'exopite-anti-spam' ),
			$eas_entry['id'],
			mysql2date( $eas_date_format, $eas_entry['created'] )
		)
	) . '</h2>';

	echo '<table class="widefat striped eas-quarantine-entry"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Form', 'exopite-anti-spam' ) . '</th><td>' . $this->get_form_link( $eas_entry['form_id'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_form_link().
	echo '<tr><th scope="row">' . esc_html__( 'Reasons', 'exopite-anti-spam' ) . '</th><td>' . esc_html( implode( ', ', array_map( array( $this, 'get_reason_label' ), $eas_entry['reasons']['codes'] ) ) ) . '</td></tr>';

	if ( $eas_entry['score'] ) {
		echo '<tr><th scope="row">' . esc_html__( 'Spam score', 'exopite-anti-spam' ) . '</th><td>' . esc_html( $eas_entry['score'] ) . '</td></tr>';
	}

	echo '<tr><th scope="row">' . esc_html__( 'Details', 'exopite-anti-spam' ) . '</th><td>' . nl2br( esc_html( implode( "\n", $eas_entry['reasons']['log'] ) ) ) . '</td></tr>';

	if ( '' !== (string) $eas_entry['release_error'] ) {
		echo '<tr><th scope="row">' . esc_html__( 'Release error', 'exopite-anti-spam' ) . '</th><td>' . esc_html( $eas_entry['release_error'] ) . '</td></tr>';
	}

	echo '</tbody></table>';

	echo '<h3>' . esc_html__( 'Submitted data', 'exopite-anti-spam' ) . '</h3>';
	echo '<table class="widefat striped eas-quarantine-data"><tbody>';

	foreach ( $eas_entry['posted_data'] as $eas_name => $eas_value ) {
		$eas_value = is_array( $eas_value ) ? implode( ', ', array_map( 'strval', array_filter( $eas_value, 'is_scalar' ) ) ) : (string) $eas_value;
		echo '<tr><th scope="row">' . esc_html( $eas_name ) . '</th><td>' . nl2br( esc_html( $eas_value ) ) . '</td></tr>';
	}

	$eas_meta_labels = array(
		'url'        => __( 'Page', 'exopite-anti-spam' ),
		'referer'    => __( 'Referer', 'exopite-anti-spam' ),
		'remote_ip'  => __( 'IP address', 'exopite-anti-spam' ),
		'user_agent' => __( 'Browser (user agent)', 'exopite-anti-spam' ),
	);

	foreach ( $eas_meta_labels as $eas_key => $eas_label ) {
		if ( ! empty( $eas_entry['meta'][ $eas_key ] ) ) {
			echo '<tr><th scope="row">' . esc_html( $eas_label ) . '</th><td>' . esc_html( $eas_entry['meta'][ $eas_key ] ) . '</td></tr>';
		}
	}

	echo '</tbody></table>';

	$eas_releasable = $eas_quarantine->is_releasable( $eas_entry );

	echo '<p class="eas-quarantine-actions">';

	if ( true === $eas_releasable ) {

		if ( $eas_entry['has_files'] ) {
			echo '<span class="description">' . esc_html__( 'The form has a file field: attachments are not stored in the quarantine and are not sent on release.', 'exopite-anti-spam' ) . '</span><br>';
		}

		$this->render_action_button( 'release', $eas_entry['id'], $eas_form_filter, __( 'Release', 'exopite-anti-spam' ), 'button-primary', __( 'Release this submission? It is sent as if nothing had happened.', 'exopite-anti-spam' ) );
	} else {
		echo '<span class="description">' . esc_html( $eas_releasable ) . '</span><br>';
	}

	$this->render_action_button( 'delete', $eas_entry['id'], $eas_form_filter, __( 'Delete', 'exopite-anti-spam' ), '', __( 'Delete this entry?', 'exopite-anti-spam' ) );

	echo '</p>';

	return;
}

$eas_per_page = 50;
$eas_entries  = $eas_quarantine->get_entries( $eas_form_filter, $eas_paged, $eas_per_page );
$eas_form_ids = $eas_quarantine->get_form_ids();

echo '<p>' . esc_html__( 'Submissions marked as spam because of the spam score, the honeypot or the proof-of-work, of forms with the quarantine activated ("Anti Spam" tab of the form). A released submission is sent as if nothing had happened.', 'exopite-anti-spam' ) . '</p>';

if ( ! $eas_entries['total'] && ! $eas_form_filter ) {

	if ( ! $this->is_quarantine_used() ) {
		echo '<p><strong>' . esc_html__( 'The quarantine is not activated in any form. It can be activated in the "Anti Spam" tab of a form.', 'exopite-anti-spam' ) . '</strong></p>';
	} else {
		echo '<p>' . esc_html__( 'The quarantine is empty.', 'exopite-anti-spam' ) . '</p>';
	}

	return;
}

// Filter by form.
echo '<form method="get" class="eas-quarantine-filter">';
echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '">';
echo '<label for="eas-quarantine-form">' . esc_html__( 'Form:', 'exopite-anti-spam' ) . '</label> ';
echo '<select id="eas-quarantine-form" name="form"><option value="0">' . esc_html__( 'All forms', 'exopite-anti-spam' ) . '</option>';

foreach ( $eas_form_ids as $eas_form_id ) {
	$eas_title = get_the_title( $eas_form_id );
	echo '<option value="' . esc_attr( $eas_form_id ) . '"' . selected( $eas_form_filter, $eas_form_id, false ) . '>' . esc_html( '' !== $eas_title ? $eas_title : '#' . $eas_form_id ) . '</option>';
}

echo '</select> ';
submit_button( __( 'Filter', 'exopite-anti-spam' ), 'secondary', '', false );
echo '</form>';

echo '<table class="widefat striped eas-quarantine-list"><thead><tr>';
echo '<th scope="col">' . esc_html__( 'Date', 'exopite-anti-spam' ) . '</th>';
echo '<th scope="col">' . esc_html__( 'Form', 'exopite-anti-spam' ) . '</th>';
echo '<th scope="col">' . esc_html__( 'Reasons', 'exopite-anti-spam' ) . '</th>';
echo '<th scope="col">' . esc_html__( 'Spam score', 'exopite-anti-spam' ) . '</th>';
echo '<th scope="col">' . esc_html__( 'Release error', 'exopite-anti-spam' ) . '</th>';
echo '<th scope="col">' . esc_html__( 'Actions', 'exopite-anti-spam' ) . '</th>';
echo '</tr></thead><tbody>';

if ( ! $eas_entries['items'] ) {
	echo '<tr><td colspan="6">' . esc_html__( 'No entries.', 'exopite-anti-spam' ) . '</td></tr>';
}

foreach ( $eas_entries['items'] as $eas_item ) {

	$eas_view_url = $this->get_url(
		array(
			'entry' => $eas_item['id'],
			'form'  => $eas_form_filter ? $eas_form_filter : null,
		)
	);

	echo '<tr>';
	echo '<td><a href="' . esc_url( $eas_view_url ) . '">' . esc_html( mysql2date( $eas_date_format, $eas_item['created'] ) ) . '</a></td>';
	echo '<td>' . $this->get_form_link( $eas_item['form_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_form_link().
	echo '<td>' . esc_html( implode( ', ', array_map( array( $this, 'get_reason_label' ), $eas_item['reasons']['codes'] ) ) ) . '</td>';
	echo '<td>' . ( $eas_item['score'] ? esc_html( $eas_item['score'] ) : '&ndash;' ) . '</td>';
	echo '<td>' . esc_html( (string) $eas_item['release_error'] ) . '</td>';
	echo '<td><a class="button button-small" href="' . esc_url( $eas_view_url ) . '">' . esc_html__( 'View', 'exopite-anti-spam' ) . '</a></td>';
	echo '</tr>';
}

echo '</tbody></table>';

$eas_pages = (int) ceil( $eas_entries['total'] / $eas_per_page );

if ( $eas_pages > 1 ) {
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%', $this->get_url( array( 'form' => $eas_form_filter ? $eas_form_filter : null ) ) ),
				'format'  => '',
				'current' => $eas_paged,
				'total'   => $eas_pages,
			)
		)
	);
	echo '</div></div>';
}

echo '<p class="eas-quarantine-actions">';
$this->render_action_button(
	'delete_all',
	$eas_form_filter,
	$eas_form_filter,
	$eas_form_filter ? __( 'Delete all entries of this form', 'exopite-anti-spam' ) : __( 'Delete all entries', 'exopite-anti-spam' ),
	'',
	__( 'Delete all these entries? This can not be undone.', 'exopite-anti-spam' )
);
echo '</p>';
