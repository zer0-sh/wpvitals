<?php
/**
 * Vista de ajustes de WPVitals.
 *
 * @package WPVitals
 *
 * @var array $data Datos preparados por AdminPage::render_settings().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpvitals_settings = $data['settings'];
?>
<div class="wrap wpvitals-wrap">
	<h1><?php esc_html_e( 'WPVitals Settings', 'wpvitals' ); ?></h1>

	<?php if ( 'updated' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible inline">
			<p><?php esc_html_e( 'Settings saved successfully.', 'wpvitals' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpvitals-frequency"><?php esc_html_e( 'Scan frequency', 'wpvitals' ); ?></label>
				</th>
				<td>
					<select name="wpvitals[frequency]" id="wpvitals-frequency">
						<?php
						$wpvitals_frequencies = array(
							'disabled' => __( 'Disabled', 'wpvitals' ),
							'daily'    => __( 'Daily', 'wpvitals' ),
							'weekly'   => __( 'Weekly', 'wpvitals' ),
							'monthly'  => __( 'Monthly', 'wpvitals' ),
						);

						foreach ( $wpvitals_frequencies as $wpvitals_value => $wpvitals_label ) :
							?>
							<option value="<?php echo esc_attr( $wpvitals_value ); ?>" <?php selected( $wpvitals_settings->get_frequency(), $wpvitals_value ); ?>>
								<?php echo esc_html( $wpvitals_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'How often the site status is checked automatically.', 'wpvitals' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="wpvitals-mail-enabled"><?php esc_html_e( 'Email reports', 'wpvitals' ); ?></label>
				</th>
				<td>
					<label for="wpvitals-mail-enabled">
						<input
							type="checkbox"
							name="wpvitals[mail_enabled]"
							id="wpvitals-mail-enabled"
							value="1"
							<?php checked( $wpvitals_settings->is_mail_enabled(), true ); ?>
						/>
						<?php esc_html_e( 'Send results by email', 'wpvitals' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'After each manual scan and when the diagnosis changes in scheduled ones.', 'wpvitals' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="wpvitals-recipient"><?php esc_html_e( 'Recipient', 'wpvitals' ); ?></label>
				</th>
				<td>
					<input
						type="email"
						name="wpvitals[recipient]"
						id="wpvitals-recipient"
						class="regular-text"
						value="<?php echo esc_attr( $wpvitals_settings->get_recipient() ); ?>"
						placeholder="<?php echo esc_attr( (string) get_option( 'admin_email', '' ) ); ?>"
					/>
					<p class="description">
						<?php
						printf(
							/* translators: %s: default administrator email. */
							esc_html__( 'Leave empty to use the administrator email («%s»).', 'wpvitals' ),
							esc_html( (string) get_option( 'admin_email', '' ) )
						);
						?>
					</p>
				</td>
			</tr>
		<tr>
				<th scope="row">
					<label for="wpvitals-time-24h"><?php esc_html_e( 'Time format', 'wpvitals' ); ?></label>
				</th>
				<td>
					<label for="wpvitals-time-24h">
						<input
							type="checkbox"
							name="wpvitals[time_24h]"
							id="wpvitals-time-24h"
							value="1"
							<?php checked( $wpvitals_settings->is_time_24h(), true ); ?>
						/>
						<?php esc_html_e( 'Use 24-hour format', 'wpvitals' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Shows scan and scheduling times in 24-hour format.', 'wpvitals' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'wpvitals_settings' ); ?>
		<input type="hidden" name="action" value="wpvitals_settings" />
		<?php submit_button( __( 'Save settings', 'wpvitals' ), 'primary', 'submit', false ); ?>
	</form>

	<?php $wpvitals_time_format = $wpvitals_settings->is_time_24h() ? 'H:i' : (string) get_option( 'time_format' ); ?>

	<p>
		<strong>
			<?php
			printf(
				/* translators: 1: configured frequency, 2: date of the next run. */
				esc_html__( 'Scheduled scan: %1$s. Next run: %2$s.', 'wpvitals' ),
				esc_html( $wpvitals_frequencies[ $wpvitals_settings->get_frequency() ] ),
				null !== $data['next_run']
					? esc_html(
						date_i18n(
							get_option( 'date_format' ) . ' ' . $wpvitals_time_format,
							$data['next_run']
						)
					)
					: esc_html__( 'not scheduled', 'wpvitals' )
			);
			?>
		</strong>
	</p>
</div>