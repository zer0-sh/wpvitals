<?php
/**
 * Tabla de hallazgos del panel de WPVitals.
 *
 * Vista parcial reutilizada por el dashboard para cada categoría y para
 * los hallazgos ignorados; todas las tablas se renderizan igual.
 *
 * @package WPVitals
 *
 * @var array $wpvitals_findings Filas con el formato de DashboardData::finding().
 * @var bool  $wpvitals_restore   true si las filas ya están ignoradas (acción "Restaurar").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpvitals_restore = isset( $wpvitals_restore ) ? (bool) $wpvitals_restore : false;
?>
<table class="wpvitals-table">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Hallazgo', 'wpvitals' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Descripción', 'wpvitals' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Severidad', 'wpvitals' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Acción', 'wpvitals' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $wpvitals_findings as $wpvitals_finding ) : ?>
			<?php
			$wpvitals_points_tip = $wpvitals_finding['points'] > 0
				? sprintf(
					/* translators: %d: puntos descontados del Health Score. */
					__( '-%d puntos', 'wpvitals' ),
					$wpvitals_finding['points']
				)
				: '';
			$wpvitals_is_group = isset( $wpvitals_finding['vulnerabilities'] ) && is_array( $wpvitals_finding['vulnerabilities'] );
			?>
			<tr>
				<td class="wpvitals-finding-cell">
					<span class="wpvitals-finding-title"><?php echo esc_html( $wpvitals_finding['title'] ); ?></span>
					<span class="wpvitals-info-icon" title="<?php echo esc_attr( $wpvitals_finding['description'] ); ?>">i</span>
					<br />
					<span class="wpvitals-finding-id"><?php echo esc_html( $wpvitals_finding['id'] ); ?></span>
					<?php if ( '' !== $wpvitals_finding['value'] ) : ?>
						<br />
						<span class="wpvitals-finding-value"><?php echo esc_html( $wpvitals_finding['value'] ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $wpvitals_finding['recommendation'] ); ?></td>
				<td>
					<span class="wpvitals-severity wpvitals-severity-<?php echo esc_attr( $wpvitals_finding['severity'] ); ?>"
						<?php if ( '' !== $wpvitals_points_tip ) : ?>
							title="<?php echo esc_attr( $wpvitals_points_tip ); ?>"
						<?php endif; ?>
					><?php echo esc_html( $wpvitals_finding['severity_label'] ); ?></span>
				</td>
				<td class="wpvitals-action-col">
					<?php if ( $wpvitals_restore ) : ?>
						<a class="wpvitals-ignore-link" href="<?php echo esc_url( \WPVitals\Admin\AdminPage::toggle_ignored_url( \WPVitals\Admin\AdminPage::UNIGNORE_ACTION, $wpvitals_finding['id'] ) ); ?>">
							<?php esc_html_e( 'Restaurar', 'wpvitals' ); ?>
						</a>
					<?php else : ?>
						<?php if ( '' !== $wpvitals_finding['link'] ) : ?>
							<a class="wpvitals-action-link" href="<?php echo esc_url( $wpvitals_finding['link'] ); ?>" target="_blank" rel="noreferrer noopener">
								<?php esc_html_e( 'Ver fuente', 'wpvitals' ); ?>
							</a>
						<?php elseif ( null !== $wpvitals_finding['screen'] ) : ?>
							<a class="wpvitals-action-link" href="<?php echo esc_url( \WPVitals\Admin\AdminPage::screen_url( $wpvitals_finding['screen'] ) ); ?>">
								<?php echo esc_html( \WPVitals\Admin\AdminPage::screen_label( $wpvitals_finding['screen'] ) ); ?>
							</a>
						<?php elseif ( ! $wpvitals_finding['is_ok'] ) : ?>
							<span class="wpvitals-action-disabled"><?php esc_html_e( 'Sin acción disponible', 'wpvitals' ); ?></span>
						<?php endif; ?>
						<?php if ( ! $wpvitals_finding['is_ok'] && ! $wpvitals_is_group ) : ?>
							<br />
							<a class="wpvitals-ignore-link" href="<?php echo esc_url( \WPVitals\Admin\AdminPage::toggle_ignored_url( \WPVitals\Admin\AdminPage::IGNORE_ACTION, $wpvitals_finding['id'] ) ); ?>">
								<?php esc_html_e( 'Ignorar', 'wpvitals' ); ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $wpvitals_is_group ) : ?>
				<tr class="wpvitals-sub-row">
					<td colspan="4">
						<details class="wpvitals-sub-list">
							<summary><?php esc_html_e( 'Click para desglose', 'wpvitals' ); ?></summary>
							<ul class="wpvitals-sub-list-items">
								<?php foreach ( $wpvitals_finding['vulnerabilities'] as $wpvitals_child ) : ?>
									<?php
									$wpvitals_child_points_tip = $wpvitals_child['points'] > 0
										? sprintf(
											/* translators: %d: puntos descontados del Health Score. */
											__( '-%d puntos', 'wpvitals' ),
											$wpvitals_child['points']
										)
										: '';
									?>
									<li>
										<span class="wpvitals-sub-body">
											<span class="wpvitals-sub-severity wpvitals-severity wpvitals-severity-<?php echo esc_attr( $wpvitals_child['severity'] ); ?>"
												<?php if ( '' !== $wpvitals_child_points_tip ) : ?>
													title="<?php echo esc_attr( $wpvitals_child_points_tip ); ?>"
												<?php endif; ?>
											><?php echo esc_html( $wpvitals_child['severity_label'] ); ?></span>
											<span class="wpvitals-sub-title"><?php echo esc_html( $wpvitals_child['title'] ); ?></span>
											<span class="wpvitals-info-icon" title="<?php echo esc_attr( $wpvitals_child['description'] ); ?>">i</span>
											<br />
											<span class="wpvitals-sub-id"><?php echo esc_html( $wpvitals_child['id'] ); ?></span>
											<span class="wpvitals-sub-meta"><?php echo esc_html( $wpvitals_child['recommendation'] ); ?></span>
										</span>
										<span class="wpvitals-sub-actions">
											<?php if ( '' !== $wpvitals_child['link'] ) : ?>
												<a class="wpvitals-action-link" href="<?php echo esc_url( $wpvitals_child['link'] ); ?>" target="_blank" rel="noreferrer noopener">
													<?php esc_html_e( 'Ver fuente', 'wpvitals' ); ?>
												</a>
											<?php endif; ?>
											<?php if ( $wpvitals_restore ) : ?>
												<a class="wpvitals-ignore-link" href="<?php echo esc_url( \WPVitals\Admin\AdminPage::toggle_ignored_url( \WPVitals\Admin\AdminPage::UNIGNORE_ACTION, $wpvitals_child['id'] ) ); ?>">
													<?php esc_html_e( 'Restaurar', 'wpvitals' ); ?>
												</a>
											<?php elseif ( ! $wpvitals_child['is_ok'] ) : ?>
												<a class="wpvitals-ignore-link" href="<?php echo esc_url( \WPVitals\Admin\AdminPage::toggle_ignored_url( \WPVitals\Admin\AdminPage::IGNORE_ACTION, $wpvitals_child['id'] ) ); ?>">
													<?php esc_html_e( 'Ignorar', 'wpvitals' ); ?>
												</a>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					</td>
				</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</tbody>
</table>