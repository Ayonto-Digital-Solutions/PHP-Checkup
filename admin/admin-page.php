<?php
/**
 * Admin Page Handler
 *
 * @package AS_PHP_Checkup
 * @since 1.0.0
 * @version 1.3.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup_Admin class
 *
 * @since 1.0.0
 */
class AS_PHP_Checkup_Admin {

	/**
	 * Instance of this class
	 *
	 * @since 1.0.0
	 * @var AS_PHP_Checkup_Admin|null
	 */
	private static $instance = null;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_as_php_checkup_refresh', array( $this, 'ajax_refresh_check' ) );
		add_action( 'wp_ajax_as_php_checkup_export', array( $this, 'ajax_export_report' ) );
		add_action( 'wp_ajax_as_php_checkup_analyze_plugins', array( $this, 'ajax_analyze_plugins' ) );
		add_action( 'wp_ajax_as_php_checkup_load_profile', array( $this, 'ajax_load_profile' ) );
		add_action( 'wp_ajax_as_php_checkup_save_config', array( $this, 'ajax_save_config' ) );
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AS_PHP_Checkup_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add admin menu
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'PHP Checkup', 'as-php-checkup' ),
			__( 'PHP Checkup', 'as-php-checkup' ),
			'manage_options',
			'as-php-checkup',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'tools_page_as-php-checkup' !== $hook ) {
			return;
		}

		// Check if scripts are registered before enqueueing
		if ( ! wp_script_is( 'as-php-checkup-admin', 'registered' ) ) {
			wp_register_script(
				'as-php-checkup-admin',
				AS_PHP_CHECKUP_PLUGIN_URL . 'assets/js/admin-script.js',
				array( 'jquery' ),
				AS_PHP_CHECKUP_VERSION,
				true
			);
		}
		
		wp_enqueue_script( 'as-php-checkup-admin' );
		
		wp_localize_script( 'as-php-checkup-admin', 'asPhpCheckup', array(
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'as_php_checkup_nonce' ),
			'strings'       => array(
				'refreshing'       => __( 'Refreshing...', 'as-php-checkup' ),
				'refresh_complete' => __( 'Check complete!', 'as-php-checkup' ),
				'refresh_error'    => __( 'Error refreshing data', 'as-php-checkup' ),
				'analyzing'        => __( 'Analyzing plugins...', 'as-php-checkup' ),
				'analysis_complete'=> __( 'Analysis complete!', 'as-php-checkup' ),
				'analysis_error'   => __( 'Error analyzing plugins', 'as-php-checkup' ),
				'export_error'     => __( 'Error exporting report', 'as-php-checkup' ),
				'apply_solution'   => __( 'Applying solution...', 'as-php-checkup' ),
				'solution_applied' => __( 'Solution applied successfully!', 'as-php-checkup' ),
				'solution_error'   => __( 'Error applying solution', 'as-php-checkup' ),
				'confirm_apply'    => __( 'Are you sure you want to apply this solution? A backup will be created.', 'as-php-checkup' ),
			),
		) );

		if ( ! wp_style_is( 'as-php-checkup-admin', 'registered' ) ) {
			wp_register_style(
				'as-php-checkup-admin',
				AS_PHP_CHECKUP_PLUGIN_URL . 'assets/css/admin-style.css',
				array(),
				AS_PHP_CHECKUP_VERSION
			);
		}
		
		wp_enqueue_style( 'as-php-checkup-admin' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Render admin page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'as-php-checkup' ) );
		}
		
		$checkup = AS_PHP_Checkup::get_instance();
		$results = $checkup->get_check_results();
		$system_info = $checkup->get_system_info();
		
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$plugin_requirements = $analyzer->get_aggregated_requirements();
		$analysis_report = $analyzer->get_analysis_report();
		
		// Get solutions - New in 1.2.0
		$solution_provider = AS_PHP_Checkup_Solution_Provider::get_instance();
		$available_solutions = $solution_provider->get_available_solutions();
		
		// Calculate overall status
		$total_checks = 0;
		$passed_checks = 0;
		$warning_checks = 0;
		$failed_checks = 0;
		
		foreach ( $results as $category ) {
			$total_checks += count( $category['items'] );
			$passed_checks += $category['passed'];
			$warning_checks += $category['warnings'];
			$failed_checks += $category['failed'];
		}
		
		$health_score = $checkup->get_health_score();
		$last_check = get_option( 'as_php_checkup_last_check' );
		$last_analysis = get_option( 'as_php_checkup_last_analysis' );
		
		?>
		<div class="wrap as-php-checkup-wrap">
			<h1><?php esc_html_e( 'AS PHP Checkup', 'as-php-checkup' ); ?> 
				<span class="version">v<?php echo esc_html( AS_PHP_CHECKUP_VERSION ); ?></span>
			</h1>
			
			<?php $this->render_tabs(); ?>
			
			<div class="tab-content" id="tab-overview">
				<!-- Summary Cards -->
				<div class="summary-cards">
					<div class="card health-score">
						<h3><?php esc_html_e( 'Health Score', 'as-php-checkup' ); ?></h3>
						<div class="score-circle <?php echo $this->get_score_class( $health_score ); ?>">
							<span class="score"><?php echo esc_html( $health_score ); ?>%</span>
						</div>
						<p><?php echo esc_html( $this->get_score_message( $health_score ) ); ?></p>
					</div>
					
					<div class="card php-version">
						<h3><?php esc_html_e( 'PHP Version', 'as-php-checkup' ); ?></h3>
						<div class="version-number"><?php echo esc_html( $system_info['php_version'] ); ?></div>
						<p><?php echo esc_html( $this->get_php_version_status( $system_info['php_version'] ) ); ?></p>
					</div>
					
					<div class="card check-status">
						<h3><?php esc_html_e( 'Check Status', 'as-php-checkup' ); ?></h3>
						<div class="status-counts">
							<span class="passed" title="<?php esc_attr_e( 'Passed', 'as-php-checkup' ); ?>">
								✓ <?php echo esc_html( $passed_checks ); ?>
							</span>
							<span class="warnings" title="<?php esc_attr_e( 'Warnings', 'as-php-checkup' ); ?>">
								⚠ <?php echo esc_html( $warning_checks ); ?>
							</span>
							<span class="failed" title="<?php esc_attr_e( 'Failed', 'as-php-checkup' ); ?>">
								✗ <?php echo esc_html( $failed_checks ); ?>
							</span>
						</div>
						<p>
							<?php 
							printf(
								/* translators: %d: total number of checks */
								esc_html__( 'Total: %d checks', 'as-php-checkup' ),
								$total_checks
							);
							?>
						</p>
					</div>
					
					<div class="card last-check">
						<h3><?php esc_html_e( 'Last Check', 'as-php-checkup' ); ?></h3>
						<div class="time">
							<?php 
							if ( $last_check ) {
								echo esc_html( human_time_diff( $last_check, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'as-php-checkup' ) );
							} else {
								esc_html_e( 'Never', 'as-php-checkup' );
							}
							?>
						</div>
						<button class="button button-primary refresh-check" data-action="refresh">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Refresh Now', 'as-php-checkup' ); ?>
						</button>
					</div>
				</div>
				
				<!-- Check Results -->
				<div class="check-results">
					<h2>
						<?php esc_html_e( 'Configuration Check Results', 'as-php-checkup' ); ?>
						<?php if ( ! empty( $available_solutions ) ) : ?>
							<span class="solutions-available">
								<?php esc_html_e( '(Solutions Available)', 'as-php-checkup' ); ?>
							</span>
						<?php endif; ?>
					</h2>
					
					<?php foreach ( $results as $category_key => $category ) : ?>
						<div class="category-section <?php echo esc_attr( $category_key ); ?>">
							<h3 class="category-title">
								<?php echo esc_html( $category['label'] ); ?>
								<span class="category-stats">
									<?php if ( $category['passed'] > 0 ) : ?>
										<span class="passed">✓ <?php echo esc_html( $category['passed'] ); ?></span>
									<?php endif; ?>
									<?php if ( $category['warnings'] > 0 ) : ?>
										<span class="warnings">⚠ <?php echo esc_html( $category['warnings'] ); ?></span>
									<?php endif; ?>
									<?php if ( $category['failed'] > 0 ) : ?>
										<span class="failed">✗ <?php echo esc_html( $category['failed'] ); ?></span>
									<?php endif; ?>
								</span>
							</h3>
							
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th class="setting-name"><?php esc_html_e( 'Setting', 'as-php-checkup' ); ?></th>
										<th class="current-value"><?php esc_html_e( 'Current', 'as-php-checkup' ); ?></th>
										<th class="recommended-value"><?php esc_html_e( 'Recommended', 'as-php-checkup' ); ?></th>
										<th class="status"><?php esc_html_e( 'Status', 'as-php-checkup' ); ?></th>
										<?php if ( ! empty( $available_solutions ) ) : ?>
											<th class="actions"><?php esc_html_e( 'Actions', 'as-php-checkup' ); ?></th>
										<?php endif; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $category['items'] as $item ) : ?>
										<tr class="status-<?php echo esc_attr( $item['status'] ); ?>">
											<td class="setting-name">
												<strong><?php echo esc_html( $item['label'] ); ?></strong>
												<br>
												<small><?php echo esc_html( $item['description'] ); ?></small>
											</td>
											<td class="current-value">
												<code><?php echo esc_html( $item['current'] ); ?></code>
											</td>
											<td class="recommended-value">
												<code><?php echo esc_html( $item['recommended'] ); ?></code>
											</td>
											<td class="status">
												<span class="status-badge status-<?php echo esc_attr( $item['status'] ); ?>">
													<?php echo esc_html( $this->get_status_label( $item['status'] ) ); ?>
												</span>
												<?php if ( ! empty( $item['message'] ) ) : ?>
													<br><small><?php echo esc_html( $item['message'] ); ?></small>
												<?php endif; ?>
											</td>
											<?php if ( ! empty( $available_solutions ) && 'warning' === $item['status'] ) : ?>
												<td class="actions">
													<button class="button button-small show-solution" 
														data-setting="<?php echo esc_attr( $item['setting'] ); ?>"
														data-recommended="<?php echo esc_attr( $item['recommended'] ); ?>"
														data-current="<?php echo esc_attr( $item['current'] ); ?>">
														<?php esc_html_e( 'Fix', 'as-php-checkup' ); ?>
													</button>
												</td>
											<?php elseif ( ! empty( $available_solutions ) ) : ?>
												<td class="actions">-</td>
											<?php endif; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
				</div>
				
				<!-- Solutions Modal - New in 1.2.0 -->
				<?php if ( ! empty( $available_solutions ) ) : ?>
					<div id="solution-modal" class="solution-modal" style="display:none;">
						<div class="modal-content">
							<span class="close">&times;</span>
							<h2><?php esc_html_e( 'Available Solutions', 'as-php-checkup' ); ?></h2>
							<div class="solution-details">
								<!-- Will be populated by JavaScript -->
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="tab-content" id="tab-system" style="display:none;">
				<h2><?php esc_html_e( 'System Information', 'as-php-checkup' ); ?></h2>
				
				<div class="system-info-grid">
					<div class="info-card">
						<h3><?php esc_html_e( 'PHP Configuration', 'as-php-checkup' ); ?></h3>
						<table class="info-table">
							<tr>
								<td><?php esc_html_e( 'PHP Version', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['php_version'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'PHP SAPI', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['php_sapi'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'PHP User', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['php_user'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'PHP.ini Path', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['php_ini_path'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Operating System', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['os'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Architecture', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['architecture'] ); ?></code></td>
							</tr>
						</table>
					</div>
					
					<div class="info-card">
						<h3><?php esc_html_e( 'Server Information', 'as-php-checkup' ); ?></h3>
						<table class="info-table">
							<tr>
								<td><?php esc_html_e( 'Server Software', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['server_software'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Database Version', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['database_version'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'WordPress Version', 'as-php-checkup' ); ?>:</td>
								<td><code><?php echo esc_html( $system_info['wordpress_version'] ); ?></code></td>
							</tr>
						</table>
					</div>
					
					<div class="info-card full-width">
						<h3><?php esc_html_e( 'Loaded PHP Extensions', 'as-php-checkup' ); ?></h3>
						<div class="extensions-list">
							<?php foreach ( $system_info['loaded_extensions'] as $extension ) : ?>
								<span class="extension-badge"><?php echo esc_html( $extension ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
					
					<?php if ( ! empty( $system_info['disabled_functions'] ) && 'None' !== $system_info['disabled_functions'] ) : ?>
						<div class="info-card full-width">
							<h3><?php esc_html_e( 'Disabled Functions', 'as-php-checkup' ); ?></h3>
							<div class="disabled-functions">
								<code><?php echo esc_html( $system_info['disabled_functions'] ); ?></code>
							</div>
						</div>
					<?php endif; ?>
				</div>
				
				<div class="action-buttons">
					<button class="button button-primary export-report" data-format="txt">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export System Info', 'as-php-checkup' ); ?>
					</button>
					<button class="button copy-system-info">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy to Clipboard', 'as-php-checkup' ); ?>
					</button>
				</div>
			</div>
			
			<div class="tab-content" id="tab-plugins" style="display:none;">
				<h2>
					<?php esc_html_e( 'Plugin Requirements Analysis', 'as-php-checkup' ); ?>
					<?php if ( $last_analysis ) : ?>
						<small>
							<?php 
							printf(
								/* translators: %s: time since last analysis */
								esc_html__( 'Last analyzed: %s ago', 'as-php-checkup' ),
								human_time_diff( $last_analysis, current_time( 'timestamp' ) )
							);
							?>
						</small>
					<?php endif; ?>
				</h2>
				
				<div class="plugins-summary">
					<div class="summary-card">
						<h3><?php esc_html_e( 'Analysis Summary', 'as-php-checkup' ); ?></h3>
						<ul>
							<li>
								<?php 
								printf(
									/* translators: %d: number of plugins */
									esc_html__( 'Total Plugins Analyzed: %d', 'as-php-checkup' ),
									$analysis_report['total_plugins']
								);
								?>
							</li>
							<li>
								<?php 
								printf(
									/* translators: %s: memory limit */
									esc_html__( 'Highest Memory Requirement: %s', 'as-php-checkup' ),
									$plugin_requirements['memory_limit']
								);
								?>
							</li>
							<li>
								<?php 
								printf(
									/* translators: %d: execution time */
									esc_html__( 'Longest Execution Time: %d seconds', 'as-php-checkup' ),
									$plugin_requirements['max_execution_time']
								);
								?>
							</li>
							<li>
								<?php 
								printf(
									/* translators: %d: input vars */
									esc_html__( 'Max Input Vars Needed: %d', 'as-php-checkup' ),
									$plugin_requirements['max_input_vars']
								);
								?>
							</li>
						</ul>
					</div>
					
					<?php if ( ! empty( $plugin_requirements['extensions'] ) ) : ?>
						<div class="summary-card">
							<h3><?php esc_html_e( 'Required PHP Extensions', 'as-php-checkup' ); ?></h3>
							<div class="extensions-required">
								<?php foreach ( $plugin_requirements['extensions'] as $extension => $required ) : ?>
									<?php if ( $required ) : ?>
										<span class="extension-badge <?php echo extension_loaded( $extension ) ? 'loaded' : 'missing'; ?>">
											<?php echo esc_html( $extension ); ?>
											<?php if ( ! extension_loaded( $extension ) ) : ?>
												<span class="status">✗</span>
											<?php else : ?>
												<span class="status">✓</span>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
				
				<?php if ( ! empty( $analysis_report['plugin_details'] ) ) : ?>
					<h3><?php esc_html_e( 'Plugin Details', 'as-php-checkup' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Plugin', 'as-php-checkup' ); ?></th>
								<th><?php esc_html_e( 'Version', 'as-php-checkup' ); ?></th>
								<th><?php esc_html_e( 'PHP Required', 'as-php-checkup' ); ?></th>
								<th><?php esc_html_e( 'Requirements', 'as-php-checkup' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $analysis_report['plugin_details'] as $plugin ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $plugin['name'] ); ?></strong></td>
									<td><?php echo esc_html( $plugin['version'] ); ?></td>
									<td>
										<?php 
										echo $plugin['requires_php'] ? 
											esc_html( $plugin['requires_php'] ) : 
											'<em>' . esc_html__( 'Not specified', 'as-php-checkup' ) . '</em>';
										?>
									</td>
									<td>
										<?php if ( $plugin['has_requirements'] && ! empty( $plugin['requirements'] ) ) : ?>
											<div class="plugin-requirements">
												<?php foreach ( $plugin['requirements'] as $req_key => $req_value ) : ?>
													<?php if ( 'extensions' !== $req_key ) : ?>
														<span class="requirement">
															<?php echo esc_html( $req_key . ': ' . $req_value ); ?>
														</span>
													<?php else : ?>
														<span class="requirement">
															<?php 
															$ext_list = array_keys( array_filter( $req_value ) );
															echo esc_html( 'Extensions: ' . implode( ', ', $ext_list ) );
															?>
														</span>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										<?php else : ?>
											<em><?php esc_html_e( 'No specific requirements detected', 'as-php-checkup' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				
				<div class="action-buttons">
					<button class="button button-primary analyze-plugins">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Re-analyze Plugins', 'as-php-checkup' ); ?>
					</button>
					<button class="button export-plugin-report">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export Plugin Report', 'as-php-checkup' ); ?>
					</button>
				</div>
			</div>
			
			<div class="tab-content" id="tab-solutions" style="display:none;">
				<h2><?php esc_html_e( 'Configuration Solutions', 'as-php-checkup' ); ?></h2>
				
				<?php if ( ! empty( $available_solutions ) ) : ?>
					<div class="solutions-overview">
						<p><?php esc_html_e( 'Choose your preferred method to apply PHP configuration changes:', 'as-php-checkup' ); ?></p>
						
						<div class="solution-methods">
							<?php foreach ( $available_solutions as $type => $solution ) : ?>
								<div class="solution-method <?php echo esc_attr( $type ); ?>">
									<h3><?php echo esc_html( $solution['label'] ); ?></h3>
									<p><?php echo esc_html( $solution['description'] ); ?></p>
									
									<?php if ( isset( $solution['instructions'] ) ) : ?>
										<div class="instructions">
											<h4><?php esc_html_e( 'Instructions:', 'as-php-checkup' ); ?></h4>
											<pre><?php echo esc_html( $solution['instructions'] ); ?></pre>
										</div>
									<?php endif; ?>
									
									<div class="solution-actions">
										<button class="button download-config" data-type="<?php echo esc_attr( $type ); ?>">
											<span class="dashicons dashicons-download"></span>
											<?php esc_html_e( 'Download Configuration', 'as-php-checkup' ); ?>
										</button>
										
										<?php if ( in_array( $type, array( 'user_ini', 'htaccess' ), true ) ) : ?>
											<button class="button button-primary auto-apply" data-type="<?php echo esc_attr( $type ); ?>">
												<span class="dashicons dashicons-admin-generic"></span>
												<?php esc_html_e( 'Auto-Apply', 'as-php-checkup' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else : ?>
					<div class="no-solutions">
						<p><?php esc_html_e( 'No configuration changes are needed. Your PHP configuration meets all requirements!', 'as-php-checkup' ); ?></p>
					</div>
				<?php endif; ?>
				
				<!-- Config Generator - New in 1.2.0 -->
				<div class="config-generator-section">
					<h3><?php esc_html_e( 'Configuration Generator', 'as-php-checkup' ); ?></h3>
					<p><?php esc_html_e( 'Generate optimized configuration files for your server environment.', 'as-php-checkup' ); ?></p>
					
					<div class="generator-controls">
						<label for="config-type">
							<?php esc_html_e( 'Configuration Type:', 'as-php-checkup' ); ?>
						</label>
						<select id="config-type" class="config-type-selector">
							<option value="php_ini"><?php esc_html_e( 'PHP.ini', 'as-php-checkup' ); ?></option>
							<option value="user_ini"><?php esc_html_e( '.user.ini', 'as-php-checkup' ); ?></option>
							<option value="htaccess"><?php esc_html_e( '.htaccess', 'as-php-checkup' ); ?></option>
							<option value="nginx"><?php esc_html_e( 'NGINX', 'as-php-checkup' ); ?></option>
							<option value="wp_config"><?php esc_html_e( 'wp-config.php', 'as-php-checkup' ); ?></option>
						</select>
						
						<button class="button button-primary generate-config">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Generate Configuration', 'as-php-checkup' ); ?>
						</button>
					</div>
					
					<div id="generated-config" class="generated-config" style="display:none;">
						<h4><?php esc_html_e( 'Generated Configuration:', 'as-php-checkup' ); ?></h4>
						<pre id="config-output"></pre>
						<button class="button copy-config">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy to Clipboard', 'as-php-checkup' ); ?>
						</button>
						<button class="button download-generated-config">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download File', 'as-php-checkup' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="tab-content" id="tab-settings" style="display:none;">
				<?php $this->render_check_settings(); ?>
			</div>

			<div class="tab-content" id="tab-tools" style="display:none;">
				<h2><?php esc_html_e( 'Tools & Utilities', 'as-php-checkup' ); ?></h2>
				
				<div class="tools-grid">
					<div class="tool-card">
						<h3><?php esc_html_e( 'Export Full Report', 'as-php-checkup' ); ?></h3>
						<p><?php esc_html_e( 'Download a comprehensive report of all checks and system information.', 'as-php-checkup' ); ?></p>
						<select id="export-format">
							<option value="json"><?php esc_html_e( 'JSON Format', 'as-php-checkup' ); ?></option>
							<option value="csv"><?php esc_html_e( 'CSV Format', 'as-php-checkup' ); ?></option>
							<option value="txt"><?php esc_html_e( 'Text Format', 'as-php-checkup' ); ?></option>
							<option value="html"><?php esc_html_e( 'HTML Format', 'as-php-checkup' ); ?></option>
						</select>
						<button class="button button-primary export-full-report">
							<?php esc_html_e( 'Export Report', 'as-php-checkup' ); ?>
						</button>
					</div>
					
					<div class="tool-card">
						<h3><?php esc_html_e( 'Cache Management', 'as-php-checkup' ); ?></h3>
						<p><?php esc_html_e( 'Clear cached check results and plugin analysis data.', 'as-php-checkup' ); ?></p>
						<?php
						// Get cache statistics - New in 1.3.0
						$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();
						$cache_stats = $cache_manager->get_cache_stats();
						$cache_size = $cache_manager->get_cache_size();
						?>
						<div class="cache-stats">
							<small>
								<?php 
								printf(
									/* translators: 1: hit rate percentage, 2: cache size */
									esc_html__( 'Hit Rate: %1$s%% | Size: %2$s', 'as-php-checkup' ),
									$cache_stats['hit_rate'],
									size_format( $cache_size )
								);
								?>
							</small>
						</div>
						<button class="button clear-cache">
							<?php esc_html_e( 'Clear All Cache', 'as-php-checkup' ); ?>
						</button>
					</div>
					
					<div class="tool-card">
						<h3><?php esc_html_e( 'Scheduled Tasks', 'as-php-checkup' ); ?></h3>
						<p><?php esc_html_e( 'Configure automatic plugin analysis and checks.', 'as-php-checkup' ); ?></p>
						<?php
						$next_scheduled = wp_next_scheduled( 'as_php_checkup_daily_analysis' );
						if ( $next_scheduled ) {
							printf(
								'<small>' . esc_html__( 'Next run: %s', 'as-php-checkup' ) . '</small>',
								esc_html( human_time_diff( $next_scheduled, current_time( 'timestamp' ) ) . ' ' . __( 'from now', 'as-php-checkup' ) )
							);
						}
						?>
						<button class="button run-scheduled-task">
							<?php esc_html_e( 'Run Now', 'as-php-checkup' ); ?>
						</button>
					</div>
					
					<div class="tool-card">
						<h3><?php esc_html_e( 'PHP Info', 'as-php-checkup' ); ?></h3>
						<p><?php esc_html_e( 'View detailed PHP configuration information.', 'as-php-checkup' ); ?></p>
						<button class="button view-phpinfo">
							<?php esc_html_e( 'View PHP Info', 'as-php-checkup' ); ?>
						</button>
					</div>
				</div>
				
				<!-- WP-CLI Commands Reference -->
				<?php if ( defined( 'WP_CLI' ) && WP_CLI ) : ?>
					<div class="cli-reference">
						<h3><?php esc_html_e( 'WP-CLI Commands', 'as-php-checkup' ); ?></h3>
						<pre>
# <?php esc_html_e( 'Check PHP configuration status', 'as-php-checkup' ); ?>
wp as-php-checkup status

# <?php esc_html_e( 'Analyze plugin requirements', 'as-php-checkup' ); ?>
wp as-php-checkup analyze

# <?php esc_html_e( 'Export report', 'as-php-checkup' ); ?>
wp as-php-checkup export --format=json

# <?php esc_html_e( 'Generate configuration', 'as-php-checkup' ); ?>
wp as-php-checkup generate-config --type=php_ini

# <?php esc_html_e( 'Apply solution', 'as-php-checkup' ); ?>
wp as-php-checkup apply-solution --type=user_ini
						</pre>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tabs
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function render_tabs() {
		$tabs = array(
			'overview'  => __( 'Overview', 'as-php-checkup' ),
			'system'    => __( 'System Info', 'as-php-checkup' ),
			'plugins'   => __( 'Plugin Analysis', 'as-php-checkup' ),
			'solutions' => __( 'Solutions', 'as-php-checkup' ),
			'settings'  => __( 'Check Settings', 'as-php-checkup' ),
			'tools'     => __( 'Tools', 'as-php-checkup' ),
		);
		
		?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
				<a href="#<?php echo esc_attr( $tab_id ); ?>" 
				   class="nav-tab <?php echo 'overview' === $tab_id ? 'nav-tab-active' : ''; ?>"
				   data-tab="<?php echo esc_attr( $tab_id ); ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Get score class
	 *
	 * @since 1.0.0
	 * @param int $score Health score.
	 * @return string
	 */
	private function get_score_class( $score ) {
		if ( $score >= 90 ) {
			return 'excellent';
		} elseif ( $score >= 70 ) {
			return 'good';
		} elseif ( $score >= 50 ) {
			return 'warning';
		}
		return 'critical';
	}

	/**
	 * Get score message
	 *
	 * @since 1.0.0
	 * @param int $score Health score.
	 * @return string
	 */
	private function get_score_message( $score ) {
		if ( $score >= 90 ) {
			return __( 'Excellent configuration!', 'as-php-checkup' );
		} elseif ( $score >= 70 ) {
			return __( 'Good, but could be improved.', 'as-php-checkup' );
		} elseif ( $score >= 50 ) {
			return __( 'Several issues need attention.', 'as-php-checkup' );
		}
		return __( 'Critical issues detected!', 'as-php-checkup' );
	}

	/**
	 * Get PHP version status
	 *
	 * @since 1.0.0
	 * @param string $version PHP version.
	 * @return string
	 */
	private function get_php_version_status( $version ) {
		$major_version = floatval( substr( $version, 0, 3 ) );
		
		if ( $major_version >= 8.2 ) {
			return __( 'Latest stable version', 'as-php-checkup' );
		} elseif ( $major_version >= 8.0 ) {
			return __( 'Supported version', 'as-php-checkup' );
		} elseif ( $major_version >= 7.4 ) {
			return __( 'Older version, consider upgrading', 'as-php-checkup' );
		}
		
		return __( 'Outdated version, upgrade recommended', 'as-php-checkup' );
	}

	/**
	 * Get status label
	 *
	 * @since 1.0.0
	 * @param string $status Check status.
	 * @return string
	 */
	private function get_status_label( $status ) {
		switch ( $status ) {
			case 'ok':
				return __( 'Passed', 'as-php-checkup' );
			case 'warning':
				return __( 'Warning', 'as-php-checkup' );
			case 'error':
				return __( 'Failed', 'as-php-checkup' );
			default:
				return __( 'Unknown', 'as-php-checkup' );
		}
	}

	/**
	 * AJAX handler for refresh check
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_refresh_check() {
		// Security check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'as_php_checkup_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'as-php-checkup' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'as-php-checkup' ) );
		}

		// Clear cache and run fresh check - Enhanced in 1.3.0
		$checkup = AS_PHP_Checkup::get_instance();
		$checkup->clear_cache();
		
		$results = $checkup->run_checkup();
		$health_score = $checkup->get_health_score();
		
		wp_send_json_success( array(
			'results'      => $results,
			'health_score' => $health_score,
			'message'      => __( 'Check completed successfully', 'as-php-checkup' ),
		) );
	}

	/**
	 * AJAX handler for export report
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_export_report() {
		// Security check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'as_php_checkup_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'as-php-checkup' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'as-php-checkup' ) );
		}

		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'json';
		
		$checkup = AS_PHP_Checkup::get_instance();
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		
		$report_data = array(
			'generated_at'    => current_time( 'mysql' ),
			'site_url'        => get_site_url(),
			'wordpress_version' => get_bloginfo( 'version' ),
			'plugin_version'  => AS_PHP_CHECKUP_VERSION,
			'check_results'   => $checkup->get_check_results(),
			'system_info'     => $checkup->get_system_info(),
			'health_score'    => $checkup->get_health_score(),
			'plugin_analysis' => $analyzer->get_analysis_report(),
		);

		$filename = 'php-checkup-report-' . date( 'Y-m-d-H-i-s' );
		
		switch ( $format ) {
			case 'json':
				$content = wp_json_encode( $report_data, JSON_PRETTY_PRINT );
				$filename .= '.json';
				$content_type = 'application/json';
				break;
				
			case 'csv':
				$content = $this->generate_csv_report( $report_data );
				$filename .= '.csv';
				$content_type = 'text/csv';
				break;
				
			case 'txt':
				$content = $this->generate_text_report( $report_data );
				$filename .= '.txt';
				$content_type = 'text/plain';
				break;
				
			case 'html':
				$content = $this->generate_html_report( $report_data );
				$filename .= '.html';
				$content_type = 'text/html';
				break;
				
			default:
				wp_die( esc_html__( 'Invalid export format', 'as-php-checkup' ) );
		}

		// Send file headers
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * AJAX handler for analyze plugins
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function ajax_analyze_plugins() {
		// Security check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'as_php_checkup_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'as-php-checkup' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'as-php-checkup' ) );
		}

		// Clear cache and run fresh analysis - Enhanced in 1.3.0
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$analyzer->clear_cache();
		
		$analysis = $analyzer->analyze_all_plugins();
		$report = $analyzer->get_analysis_report();
		
		wp_send_json_success( array(
			'analysis' => $analysis,
			'report'   => $report,
			'message'  => __( 'Plugin analysis completed', 'as-php-checkup' ),
		) );
	}

	/**
	 * Generate CSV report
	 *
	 * @since 1.0.0
	 * @param array $data Report data.
	 * @return string
	 */
	private function generate_csv_report( $data ) {
		$csv = "PHP Checkup Report\n";
		$csv .= "Generated: {$data['generated_at']}\n";
		$csv .= "Site: {$data['site_url']}\n";
		$csv .= "Health Score: {$data['health_score']}%\n\n";
		
		$csv .= "Category,Setting,Current,Recommended,Status\n";
		
		foreach ( $data['check_results'] as $category => $category_data ) {
			foreach ( $category_data['items'] as $item ) {
				$csv .= sprintf(
					'"%s","%s","%s","%s","%s"' . "\n",
					$category_data['label'],
					$item['label'],
					$item['current'],
					$item['recommended'],
					$item['status']
				);
			}
		}
		
		return $csv;
	}

	/**
	 * Generate text report
	 *
	 * @since 1.0.0
	 * @param array $data Report data.
	 * @return string
	 */
	private function generate_text_report( $data ) {
		$text = "=====================================\n";
		$text .= "       PHP CHECKUP REPORT\n";
		$text .= "=====================================\n\n";
		
		$text .= "Generated: {$data['generated_at']}\n";
		$text .= "Site: {$data['site_url']}\n";
		$text .= "WordPress: {$data['wordpress_version']}\n";
		$text .= "Health Score: {$data['health_score']}%\n\n";
		
		$text .= "SYSTEM INFORMATION\n";
		$text .= "-----------------\n";
		foreach ( $data['system_info'] as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			$text .= sprintf( "%-20s: %s\n", $key, $value );
		}
		
		$text .= "\nCONFIGURATION CHECKS\n";
		$text .= "-------------------\n";
		
		foreach ( $data['check_results'] as $category => $category_data ) {
			$text .= "\n{$category_data['label']}:\n";
			foreach ( $category_data['items'] as $item ) {
				$status_symbol = 'ok' === $item['status'] ? '✓' : ( 'warning' === $item['status'] ? '⚠' : '✗' );
				$text .= sprintf(
					"  %s %-30s Current: %-15s Recommended: %s\n",
					$status_symbol,
					$item['label'],
					$item['current'],
					$item['recommended']
				);
			}
		}
		
		return $text;
	}

	/**
	 * Generate HTML report
	 *
	 * @since 1.0.0
	 * @param array $data Report data.
	 * @return string
	 */
	private function generate_html_report( $data ) {
		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>PHP Checkup Report</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		h1 { color: #333; }
		.info { background: #f5f5f5; padding: 10px; margin: 10px 0; }
		.score { font-size: 24px; font-weight: bold; }
		table { width: 100%; border-collapse: collapse; margin: 20px 0; }
		th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
		th { background: #f0f0f0; }
		.status-ok { color: green; }
		.status-warning { color: orange; }
		.status-error { color: red; }
	</style>
</head>
<body>
	<h1>PHP Checkup Report</h1>
	
	<div class="info">
		<p><strong>Generated:</strong> ' . esc_html( $data['generated_at'] ) . '</p>
		<p><strong>Site:</strong> ' . esc_html( $data['site_url'] ) . '</p>
		<p><strong>WordPress Version:</strong> ' . esc_html( $data['wordpress_version'] ) . '</p>
		<p class="score"><strong>Health Score:</strong> ' . esc_html( $data['health_score'] ) . '%</p>
	</div>';
		
		$html .= '<h2>Configuration Checks</h2>';
		
		foreach ( $data['check_results'] as $category => $category_data ) {
			$html .= '<h3>' . esc_html( $category_data['label'] ) . '</h3>';
			$html .= '<table>';
			$html .= '<tr><th>Setting</th><th>Current</th><th>Recommended</th><th>Status</th></tr>';
			
			foreach ( $category_data['items'] as $item ) {
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $item['label'] ) . '</td>';
				$html .= '<td>' . esc_html( $item['current'] ) . '</td>';
				$html .= '<td>' . esc_html( $item['recommended'] ) . '</td>';
				$html .= '<td class="status-' . esc_attr( $item['status'] ) . '">' . esc_html( $item['status'] ) . '</td>';
				$html .= '</tr>';
			}
			
			$html .= '</table>';
		}
		
		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Render check settings tab
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private function render_check_settings() {
		$check_config = AS_PHP_Checkup_Check_Config::get_instance();
		$current_profile = get_option( 'as_php_checkup_config_profile', 'balanced' );
		$profiles = $check_config->get_available_profiles();
		$checks_grouped = $check_config->get_checks_grouped();
		$enabled_config = get_option( 'as_php_checkup_enabled_checks', array() );

		// Calculate statistics
		$total_checks = 0;
		$enabled_checks = 0;
		$critical_checks = 0;
		foreach ( $checks_grouped as $category_checks ) {
			foreach ( $category_checks as $check_key => $check_data ) {
				$total_checks++;
				if ( 'critical' === $check_data['severity'] ) {
					$critical_checks++;
					$enabled_checks++;
				} elseif ( $check_config->is_check_enabled( $check_key ) ) {
					$enabled_checks++;
				}
			}
		}

		?>
		<h2><?php esc_html_e( 'Check Configuration Settings', 'as-php-checkup' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure which checks are enabled or disabled. Security checks cannot be disabled.', 'as-php-checkup' ); ?>
		</p>

		<!-- Configuration Overview -->
		<div class="config-overview">
			<div class="overview-cards">
				<div class="overview-card">
					<h3><?php esc_html_e( 'Active Profile', 'as-php-checkup' ); ?></h3>
					<div class="profile-name"><?php echo esc_html( $profiles[ $current_profile ]['name'] ); ?></div>
					<p><?php echo esc_html( $profiles[ $current_profile ]['description'] ); ?></p>
				</div>

				<div class="overview-card">
					<h3><?php esc_html_e( 'Checks Status', 'as-php-checkup' ); ?></h3>
					<div class="checks-stats">
						<div class="stat">
							<span class="stat-number"><?php echo esc_html( $enabled_checks ); ?></span> /
							<span class="stat-total"><?php echo esc_html( $total_checks ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Enabled', 'as-php-checkup' ); ?></span>
						</div>
						<div class="stat">
							<span class="stat-number critical-count"><?php echo esc_html( $critical_checks ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Security (Mandatory)', 'as-php-checkup' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Profile Selector -->
		<div class="profile-selector-section">
			<h3><?php esc_html_e( 'Configuration Profiles', 'as-php-checkup' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Quick presets for different security and performance needs.', 'as-php-checkup' ); ?>
			</p>

			<div class="profile-options">
				<?php foreach ( $profiles as $profile_key => $profile_data ) : ?>
					<label class="profile-option <?php echo $current_profile === $profile_key ? 'selected' : ''; ?>">
						<input type="radio"
							   name="check_profile"
							   value="<?php echo esc_attr( $profile_key ); ?>"
							   <?php checked( $current_profile, $profile_key ); ?>
							   <?php echo 'custom' === $profile_key && 'custom' !== $current_profile ? 'disabled' : ''; ?>>
						<div class="profile-content">
							<h4><?php echo esc_html( $profile_data['name'] ); ?></h4>
							<p><?php echo esc_html( $profile_data['description'] ); ?></p>
						</div>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Individual Check Configuration -->
		<div class="checks-configuration-section">
			<h3>
				<?php esc_html_e( 'Individual Check Configuration', 'as-php-checkup' ); ?>
				<small><?php esc_html_e( '(Modifying individual checks will switch to Custom profile)', 'as-php-checkup' ); ?></small>
			</h3>

			<div class="checks-list">
				<?php foreach ( $checks_grouped as $category => $category_checks ) : ?>
					<div class="check-category">
						<h4 class="category-title"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $category ) ) ); ?></h4>

						<table class="wp-list-table widefat fixed striped check-config-table">
							<thead>
								<tr>
									<th class="check-name"><?php esc_html_e( 'Check', 'as-php-checkup' ); ?></th>
									<th class="check-severity"><?php esc_html_e( 'Severity', 'as-php-checkup' ); ?></th>
									<th class="check-reason"><?php esc_html_e( 'Reason', 'as-php-checkup' ); ?></th>
									<th class="check-toggle"><?php esc_html_e( 'Enabled', 'as-php-checkup' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $category_checks as $check_key => $check_data ) : ?>
									<?php
									$is_critical = 'critical' === $check_data['severity'];
									$is_enabled = $check_config->is_check_enabled( $check_key );
									$can_disable = $check_data['disableable'];
									?>
									<tr class="check-row severity-<?php echo esc_attr( $check_data['severity'] ); ?>">
										<td class="check-name">
											<strong><?php echo esc_html( str_replace( '_', '.', $check_key ) ); ?></strong>
											<?php if ( ! $can_disable ) : ?>
												<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Security check - Cannot be disabled', 'as-php-checkup' ); ?>"></span>
											<?php endif; ?>
										</td>
										<td class="check-severity">
											<span class="severity-badge severity-<?php echo esc_attr( $check_data['severity'] ); ?>">
												<?php echo esc_html( ucfirst( $check_data['severity'] ) ); ?>
											</span>
										</td>
										<td class="check-reason">
											<small><?php echo esc_html( $check_data['reason'] ); ?></small>
										</td>
										<td class="check-toggle">
											<?php if ( $can_disable ) : ?>
												<label class="toggle-switch">
													<input type="checkbox"
														   name="checks_enabled[]"
														   value="<?php echo esc_attr( $check_key ); ?>"
														   <?php checked( $is_enabled ); ?>
														   class="check-toggle-input"
														   data-check="<?php echo esc_attr( $check_key ); ?>">
													<span class="toggle-slider"></span>
												</label>
											<?php else : ?>
												<span class="check-mandatory">
													<span class="dashicons dashicons-yes-alt"></span>
													<?php esc_html_e( 'Mandatory', 'as-php-checkup' ); ?>
												</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Save Actions -->
		<div class="config-actions">
			<button type="button" class="button button-primary button-large save-check-config">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save Configuration', 'as-php-checkup' ); ?>
			</button>
			<button type="button" class="button button-secondary reset-to-defaults">
				<span class="dashicons dashicons-undo"></span>
				<?php esc_html_e( 'Reset to Defaults', 'as-php-checkup' ); ?>
			</button>
			<span class="save-status"></span>
		</div>

		<div class="config-help">
			<h4><?php esc_html_e( 'Configuration Help', 'as-php-checkup' ); ?></h4>
			<dl>
				<dt><strong><?php esc_html_e( 'Critical (Security)', 'as-php-checkup' ); ?>:</strong></dt>
				<dd><?php esc_html_e( 'Security-related checks that cannot be disabled. These protect your site from vulnerabilities.', 'as-php-checkup' ); ?></dd>

				<dt><strong><?php esc_html_e( 'Recommended', 'as-php-checkup' ); ?>:</strong></dt>
				<dd><?php esc_html_e( 'Important checks for stability and best practices. Can be disabled if needed for specific hosting environments.', 'as-php-checkup' ); ?></dd>

				<dt><strong><?php esc_html_e( 'Optional', 'as-php-checkup' ); ?>:</strong></dt>
				<dd><?php esc_html_e( 'Performance optimization checks like OPcache. These are helpful but not required.', 'as-php-checkup' ); ?></dd>
			</dl>
		</div>
		<?php
	}

	/**
	 * AJAX handler for loading profile configuration
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function ajax_load_profile() {
		// Security check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'as_php_checkup_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'as-php-checkup' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'as-php-checkup' ) );
		}

		$profile = isset( $_POST['profile'] ) ? sanitize_text_field( wp_unslash( $_POST['profile'] ) ) : 'balanced';

		$check_config = AS_PHP_Checkup_Check_Config::get_instance();
		$result = $check_config->load_profile( $profile );

		if ( $result ) {
			// Get enabled checks for this profile
			$enabled_checks = array();
			$checks_grouped = $check_config->get_checks_grouped();

			foreach ( $checks_grouped as $category_checks ) {
				foreach ( $category_checks as $check_key => $check_data ) {
					if ( $check_config->is_check_enabled( $check_key ) ) {
						$enabled_checks[] = $check_key;
					}
				}
			}

			wp_send_json_success( array(
				'profile'        => $profile,
				'enabled_checks' => $enabled_checks,
				'message'        => __( 'Profile loaded successfully', 'as-php-checkup' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to load profile', 'as-php-checkup' ),
			) );
		}
	}

	/**
	 * AJAX handler for saving check configuration
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function ajax_save_config() {
		// Security check
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'as_php_checkup_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'as-php-checkup' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'as-php-checkup' ) );
		}

		$profile = isset( $_POST['profile'] ) ? sanitize_text_field( wp_unslash( $_POST['profile'] ) ) : 'custom';
		$enabled_checks = isset( $_POST['enabled_checks'] ) && is_array( $_POST['enabled_checks'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_checks'] ) )
			: array();

		$check_config = AS_PHP_Checkup_Check_Config::get_instance();

		// Build configuration array
		$config = array();
		$checks_grouped = $check_config->get_checks_grouped();

		foreach ( $checks_grouped as $category_checks ) {
			foreach ( $category_checks as $check_key => $check_data ) {
				// Critical checks are always enabled
				if ( 'critical' === $check_data['severity'] ) {
					$config[ $check_key ] = true;
				} else {
					$config[ $check_key ] = in_array( $check_key, $enabled_checks, true );
				}
			}
		}

		$result = $check_config->save_configuration( $config, $profile );

		if ( $result ) {
			// Clear cache to force re-check with new configuration
			$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();
			$cache_manager->delete( 'check_results' );
			$cache_manager->delete( 'check_results_lock' );

			wp_send_json_success( array(
				'profile' => $profile,
				'message' => __( 'Configuration saved successfully', 'as-php-checkup' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to save configuration', 'as-php-checkup' ),
			) );
		}
	}
}