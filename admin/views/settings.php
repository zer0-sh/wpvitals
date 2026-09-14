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
	<h1><?php esc_html_e( 'Ajustes de WPVitals', 'wpvitals' ); ?></h1>

	<?php if ( 'updated' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible inline">
			<p><?php esc_html_e( 'Ajustes guardados correctamente.', 'wpvitals' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpvitals-frequency"><?php esc_html_e( 'Frecuencia del escaneo', 'wpvitals' ); ?></label>
				</th>
				<td>
					<select name="wpvitals[frequency]" id="wpvitals-frequency">
						<?php
						$wpvitals_frequencies = array(
							'disabled' => __( 'Desactivado', 'wpvitals' ),
							'daily'    => __( 'A diario', 'wpvitals' ),
							'weekly'   => __( 'Semanalmente', 'wpvitals' ),
							'monthly'  => __( 'Mensualmente', 'wpvitals' ),
						);

						foreach ( $wpvitals_frequencies as $wpvitals_value => $wpvitals_label ) :
							?>
							<option value="<?php echo esc_attr( $wpvitals_value ); ?>" <?php selected( $wpvitals_settings->get_frequency(), $wpvitals_value ); ?>>
								<?php echo esc_html( $wpvitals_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Frecuencia con la que se revisa el estado del sitio automáticamente.', 'wpvitals' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="wpvitals-mail-enabled"><?php esc_html_e( 'Informes por correo', 'wpvitals' ); ?></label>
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
						<?php esc_html_e( 'Enviar los resultados por correo', 'wpvitals' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Tras cada escaneo manual y cuando el diagnóstico cambia en los programados.', 'wpvitals' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="wpvitals-recipient"><?php esc_html_e( 'Destinatario', 'wpvitals' ); ?></label>
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
							/* translators: %s: correo del administrador por defecto. */
							esc_html__( 'Vacío para usar el correo del administrador («%s»).', 'wpvitals' ),
							esc_html( (string) get_option( 'admin_email', '' ) )
						);
						?>
					</p>
				</td>
			</tr>
		<tr>
				<th scope="row">
					<label for="wpvitals-time-24h"><?php esc_html_e( 'Formato de hora', 'wpvitals' ); ?></label>
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
						<?php esc_html_e( 'Usar formato de 24 horas', 'wpvitals' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Muestra la hora de los escaneos y las programaciones en formato de 24 horas.', 'wpvitals' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'wpvitals_settings' ); ?>
		<input type="hidden" name="action" value="wpvitals_settings" />
		<?php submit_button( __( 'Guardar ajustes', 'wpvitals' ), 'primary', 'submit', false ); ?>
	</form>

	<?php $wpvitals_time_format = $wpvitals_settings->is_time_24h() ? 'H:i' : (string) get_option( 'time_format' ); ?>

	<p>
		<strong>
			<?php
			printf(
				/* translators: 1: frecuencia configurada, 2: fecha de la próxima ejecución. */
				esc_html__( 'Escaneo programado: %1$s. Próxima ejecución: %2$s.', 'wpvitals' ),
				esc_html( $wpvitals_frequencies[ $wpvitals_settings->get_frequency() ] ),
				null !== $data['next_run']
					? esc_html(
						date_i18n(
							get_option( 'date_format' ) . ' ' . $wpvitals_time_format,
							$data['next_run']
						)
					)
					: esc_html__( 'no programado', 'wpvitals' )
			);
			?>
		</strong>
	</p>
</div>