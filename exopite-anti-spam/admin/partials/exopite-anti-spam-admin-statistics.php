<?php
/**
 * Tab "Statistics" of the "Anti Spam" page (included by Exopite_Anti_Spam_Admin_Overview::render()).
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

$eas_periods = array(
	7  => __( 'Last 7 days', 'exopite-anti-spam' ),
	30 => __( 'Last 30 days', 'exopite-anti-spam' ),
);

echo '<p>' . esc_html__( 'Every submission of a Contact Form 7 form is counted once: sent, or the reasons why it was rejected (a submission may have several reasons). Only counters are stored, no personal data. Data older than 90 days is deleted.', 'exopite-anti-spam' ) . '</p>';

foreach ( $eas_periods as $eas_days => $eas_title ) {

	$eas_rows = $this->main->stats->get_summary( $eas_days );

	echo '<h2>' . esc_html( $eas_title ) . '</h2>';

	if ( empty( $eas_rows ) ) {
		echo '<p>' . esc_html__( 'No submissions counted yet.', 'exopite-anti-spam' ) . '</p>';
		continue;
	}

	$eas_totals = array(
		'sent'     => 0,
		'spam'     => 0,
		'invalid'  => 0,
		'released' => 0,
	);

	foreach ( $eas_rows as $eas_row ) {

		$eas_total = (int) $eas_row['total'];

		if ( 'sent' === $eas_row['reason'] || 'released' === $eas_row['reason'] ) {
			$eas_totals[ $eas_row['reason'] ] += $eas_total;
		} elseif ( in_array( $eas_row['reason'], self::SPAM_CODES, true ) ) {
			$eas_totals['spam'] += $eas_total;
		} elseif ( in_array( $eas_row['reason'], self::INVALID_CODES, true ) ) {
			$eas_totals['invalid'] += $eas_total;
		}
	}

	echo '<p class="eas-stats-totals">';
	echo '<strong>' . esc_html__( 'Sent', 'exopite-anti-spam' ) . ':</strong> ' . esc_html( number_format_i18n( $eas_totals['sent'] ) ) . ' &nbsp; ';
	echo '<strong>' . esc_html__( 'Spam', 'exopite-anti-spam' ) . ':</strong> ' . esc_html( number_format_i18n( $eas_totals['spam'] ) ) . ' &nbsp; ';
	echo '<strong>' . esc_html__( 'Validation errors', 'exopite-anti-spam' ) . ':</strong> ' . esc_html( number_format_i18n( $eas_totals['invalid'] ) ) . ' &nbsp; ';
	echo '<strong>' . esc_html__( 'Released from quarantine', 'exopite-anti-spam' ) . ':</strong> ' . esc_html( number_format_i18n( $eas_totals['released'] ) );
	echo '</p>';

	echo '<table class="widefat striped eas-stats-table"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'Form', 'exopite-anti-spam' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Reason', 'exopite-anti-spam' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Count', 'exopite-anti-spam' ) . '</th>';
	echo '</tr></thead><tbody>';

	$eas_last_form = null;

	foreach ( $eas_rows as $eas_row ) {

		// The form name only in the first row of its group.
		$eas_form_cell = ( $eas_last_form !== $eas_row['form_id'] ) ? $this->get_form_link( $eas_row['form_id'] ) : '';
		$eas_last_form = $eas_row['form_id'];

		echo '<tr>';
		echo '<td>' . $eas_form_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_form_link().
		echo '<td>' . esc_html( $this->get_reason_label( $eas_row['reason'] ) ) . '</td>';
		echo '<td>' . esc_html( number_format_i18n( (int) $eas_row['total'] ) ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}
