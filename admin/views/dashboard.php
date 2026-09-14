<?php
/**
 * Vista principal del panel de WPVitals.
 *
 * @package WPVitals
 *
 * @var array $data Datos preparados por DashboardData::build().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpvitals_has    = $data['has_result'];
$wpvitals_state  = $wpvitals_has ? $data['score_state'] : 'unknown';
$wpvitals_gauge  = null !== $data['score_total'] ? (int) round( $data['score_total'] * 1.8 ) : 0;
$wpvitals_format = $data['settings']->is_time_24h() ? 'H:i' : (string) get_option( 'time_format' );
$wpvitals_values = array_merge(
	$data['site'],
	array(
		'vuln'    => $data['vulnerabilities_count'],
		'updates' => $data['pending_updates_count'],
	)
);
?>
<div class="wrap wpvitals-wrap">
	<?php if ( 'ok' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible inline">
			<p><?php esc_html_e( 'Escaneo completado correctamente.', 'wpvitals' ); ?></p>
		</div>
	<?php elseif ( 'error' === $data['notice'] ) : ?>
		<div class="notice notice-error inline">
			<p><?php esc_html_e( 'El escaneo falló. Reinténtalo en unos segundos.', 'wpvitals' ); ?></p>
		</div>
	<?php elseif ( 'busy' === $data['notice'] ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Ya hay un escaneo en curso.', 'wpvitals' ); ?></p>
		</div>
	<?php elseif ( 'ignored' === $data['notice'] ) : ?>
		<div class="notice notice-info is-dismissible inline">
			<p><?php esc_html_e( 'El hallazgo se ha movido a la sección de ignorados.', 'wpvitals' ); ?></p>
		</div>
	<?php elseif ( 'restored' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible inline">
			<p><?php esc_html_e( 'El hallazgo se ha restaurado y vuelve a contar en el diagnóstico.', 'wpvitals' ); ?></p>
		</div>
	<?php elseif ( 'restored_all' === $data['notice'] ) : ?>
		<div class="notice notice-success is-dismissible inline">
			<p><?php esc_html_e( 'Todos los hallazgos ignorados se han restaurado.', 'wpvitals' ); ?></p>
		</div>
	<?php endif; ?>

	<header class="wpvitals-header">
		<div class="wpvitals-brand">
			<span class="wpvitals-brand-mark" aria-hidden="true">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
					<path d="M8 12l3 3 5-6" />
				</svg>
			</span>
			<div class="wpvitals-brand-text">
				<h1><?php esc_html_e( 'WPVitals', 'wpvitals' ); ?></h1>
				<span class="wpvitals-brand-tag"><?php esc_html_e( 'Diagnóstico de salud, seguridad y rendimiento', 'wpvitals' ); ?></span>
			</div>
		</div>

		<form class="wpvitals-scan-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpvitals_scan" />
			<?php wp_nonce_field( 'wpvitals_scan' ); ?>
			<button type="submit" class="button button-primary wpvitals-scan-button">
				<span><?php esc_html_e( 'Escanear ahora', 'wpvitals' ); ?></span>
			</button>
		</form>
	</header>

	<?php if ( ! $wpvitals_has ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Todavía no hay ningún escaneo. Pulsa "Escanear ahora" para generar el primer diagnóstico.', 'wpvitals' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wpvitals-grid">
		<section class="wpvitals-grid-col wpvitals-col-main" aria-label="<?php esc_attr_e( 'Resumen de sitio', 'wpvitals' ); ?>">
			<div class="wpvitals-card">
				<h2 class="wpvitals-card-title"><?php esc_html_e( 'Health Score', 'wpvitals' ); ?></h2>
				<div class="wpvitals-gauge wpvitals-gauge-<?php echo esc_attr( $wpvitals_state ); ?>" style="<?php echo esc_attr( '--p:' . $wpvitals_gauge . 'deg' ); ?>">
					<div class="wpvitals-gauge-fill"></div>
					<div class="wpvitals-gauge-value">
						<?php
						if ( $wpvitals_has ) {
							echo esc_html( (string) $data['score_total'] );
						} else {
							echo '&mdash;';
						}
						?>
					</div>
				</div>
				<div class="wpvitals-card-meta">
					<?php echo esc_html( $wpvitals_has ? $data['score_label'] : __( 'Sin escaneo', 'wpvitals' ) ); ?>
				</div>
			</div>

			<div class="wpvitals-card">
				<h2 class="wpvitals-card-title"><?php esc_html_e( 'Último escaneo', 'wpvitals' ); ?></h2>
				<div class="wpvitals-card-value">
					<?php
					if ( $wpvitals_has ) {
						echo esc_html(
							date_i18n(
								get_option( 'date_format' ) . ' ' . $wpvitals_format,
								$data['completed_at']
							)
						);
					} else {
						echo '&mdash;';
					}
					?>
				</div>
				<div class="wpvitals-card-meta">
					<?php echo esc_html( $wpvitals_has ? $data['origin_label'] : __( 'Sin escaneos', 'wpvitals' ) ); ?>
				</div>
			</div>
		</section>

		<section class="wpvitals-grid-col wpvitals-col-site" aria-label="<?php esc_attr_e( 'Detalles técnicos', 'wpvitals' ); ?>">
			<h2 class="wpvitals-grid-heading"><?php esc_html_e( 'Detalles técnicos', 'wpvitals' ); ?></h2>

			<div class="wpvitals-card wpvitals-tech">
				<span class="wpvitals-tech-icon wpvitals-tech-icon-globe" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="9" />
						<path d="M3 12h18" />
						<path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z" />
					</svg>
				</span>
				<div class="wpvitals-tech-text">
					<div class="wpvitals-tech-label"><?php esc_html_e( 'URL del proyecto', 'wpvitals' ); ?></div>
					<div class="wpvitals-tech-value wpvitals-site-url">
						<a href="<?php echo esc_url( $wpvitals_values['url'] ); ?>" target="_blank" rel="noreferrer noopener">
							<?php echo esc_html( $wpvitals_values['url'] ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="wpvitals-tech-pair">
				<div class="wpvitals-card wpvitals-tech">
					<span class="wpvitals-tech-icon wpvitals-tech-icon-wp" aria-hidden="true">W</span>
					<div class="wpvitals-tech-text">
						<div class="wpvitals-tech-label"><?php esc_html_e( 'WordPress', 'wpvitals' ); ?></div>
						<div class="wpvitals-tech-value"><?php echo esc_html( $wpvitals_values['core'] ); ?></div>
					</div>
				</div>

				<div class="wpvitals-card wpvitals-tech">
					<span class="wpvitals-tech-icon wpvitals-tech-icon-php" aria-hidden="true">php</span>
					<div class="wpvitals-tech-text">
						<div class="wpvitals-tech-label"><?php esc_html_e( 'PHP', 'wpvitals' ); ?></div>
						<div class="wpvitals-tech-value"><?php echo esc_html( $wpvitals_values['php'] ); ?></div>
					</div>
				</div>
			</div>

			<div class="wpvitals-card wpvitals-tech">
				<span class="wpvitals-tech-icon wpvitals-tech-icon-server" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="4" width="18" height="7" rx="2" />
						<rect x="3" y="13" width="18" height="7" rx="2" />
						<path d="M7 7.5h.01M7 16.5h.01" />
					</svg>
				</span>
				<div class="wpvitals-tech-text">
					<div class="wpvitals-tech-label"><?php esc_html_e( 'Servidor', 'wpvitals' ); ?></div>
					<div class="wpvitals-tech-value"><?php echo esc_html( '' !== $wpvitals_values['server'] ? $wpvitals_values['server'] : __( 'No detectable', 'wpvitals' ) ); ?></div>
				</div>
			</div>
		</section>

		<section class="wpvitals-grid-col wpvitals-col-key" aria-label="<?php esc_attr_e( 'Hallazgos clave', 'wpvitals' ); ?>">
			<h2 class="wpvitals-grid-heading"><?php esc_html_e( 'Hallazgos clave', 'wpvitals' ); ?></h2>

			<div class="wpvitals-card wpvitals-stat">
				<div class="wpvitals-stat-value wpvitals-stat-value-<?php echo esc_attr( $wpvitals_values['vuln'] > 0 ? 'bad' : 'good' ); ?>">
					<?php echo esc_html( (string) $wpvitals_values['vuln'] ); ?>
				</div>
				<div class="wpvitals-stat-label"><?php esc_html_e( 'Vulnerabilidades activas', 'wpvitals' ); ?></div>
				<span class="wpvitals-badge wpvitals-badge-<?php echo esc_attr( $wpvitals_values['vuln'] > 0 ? 'bad' : 'good' ); ?>">
					<?php echo esc_html( $wpvitals_values['vuln'] > 0 ? __( 'En riesgo', 'wpvitals' ) : __( 'Seguro', 'wpvitals' ) ); ?>
				</span>
			</div>

			<div class="wpvitals-card wpvitals-stat">
				<div class="wpvitals-stat-value wpvitals-stat-value-<?php echo esc_attr( $wpvitals_values['updates'] > 0 ? 'bad' : 'good' ); ?>">
					<?php echo esc_html( (string) $wpvitals_values['updates'] ); ?>
				</div>
				<div class="wpvitals-stat-label"><?php esc_html_e( 'Actualizaciones pendientes', 'wpvitals' ); ?></div>
				<span class="wpvitals-badge wpvitals-badge-<?php echo esc_attr( $wpvitals_values['updates'] > 0 ? 'warn' : 'good' ); ?>">
					<?php echo esc_html( $wpvitals_values['updates'] > 0 ? __( 'Pendiente', 'wpvitals' ) : __( 'Al día', 'wpvitals' ) ); ?>
				</span>
			</div>
		</section>
	</div>

	<?php if ( $wpvitals_has && array() !== $data['categories'] ) : ?>
		<?php foreach ( $data['categories'] as $wpvitals_group ) : ?>
			<section class="wpvitals-section">
				<h2 class="wpvitals-section-title"><?php echo esc_html( $wpvitals_group['label'] ); ?></h2>
				<?php
				$wpvitals_findings = $wpvitals_group['findings'];
				$wpvitals_restore  = false;
				require __DIR__ . '/partials/findings-table.php';
				?>
			</section>
		<?php endforeach; ?>
	<?php elseif ( $wpvitals_has ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'No se han detectado hallazgos relevantes.', 'wpvitals' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $wpvitals_has && $data['ignored_count'] > 0 ) : ?>
		<div class="wpvitals-section">
			<details class="wpvitals-ignored">
				<summary>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: número de hallazgos ignorados. */
							__( 'Ignorados (%d)', 'wpvitals' ),
							$data['ignored_count']
						)
					);
					?>
					<span class="wpvitals-restore-all">
						<a href="<?php echo esc_url( \WPVitals\Admin\AdminPage::restore_all_url() ); ?>">
							<?php esc_html_e( 'Restaurar todos', 'wpvitals' ); ?>
						</a>
					</span>
				</summary>
				<?php
				$wpvitals_findings = $data['ignored'];
				$wpvitals_restore  = true;
				require __DIR__ . '/partials/findings-table.php';
				?>
			</details>
		</div>
	<?php endif; ?>
</div>