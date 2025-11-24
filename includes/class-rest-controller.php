<?php
/**
 * REST API Controller
 *
 * @package AS_PHP_Checkup
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup_REST_Controller class
 *
 * @since 1.1.0
 */
class AS_PHP_Checkup_REST_Controller {

	/**
	 * Instance of this class
	 *
	 * @since 1.1.0
	 * @var AS_PHP_Checkup_REST_Controller|null
	 */
	private static $instance = null;

	/**
	 * REST namespace
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $namespace = 'as-php-checkup/v1';

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Constructor logic if needed
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.1.0
	 * @return AS_PHP_Checkup_REST_Controller
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes() {
		// Status endpoint
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(),
				),
				'schema' => array( $this, 'get_status_schema' ),
			)
		);
		
		// System info endpoint
		register_rest_route(
			$this->namespace,
			'/system-info',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_system_info' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(),
				),
				'schema' => array( $this, 'get_system_info_schema' ),
			)
		);
		
		// Plugin analysis endpoint
		register_rest_route(
			$this->namespace,
			'/plugin-analysis',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugin_analysis' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'refresh' => array(
							'description' => __( 'Force refresh the analysis', 'as-php-checkup' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
				'schema' => array( $this, 'get_plugin_analysis_schema' ),
			)
		);
		
		// Refresh endpoint
		register_rest_route(
			$this->namespace,
			'/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refresh_check' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(),
				),
			)
		);
		
		// Export endpoint
		register_rest_route(
			$this->namespace,
			'/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_report' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'format' => array(
							'description' => __( 'Export format', 'as-php-checkup' ),
							'type'        => 'string',
							'enum'        => array( 'json', 'csv' ),
							'default'     => 'json',
						),
					),
				),
			)
		);
	}

	/**
	 * Check read permission
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_read_permission( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check write permission
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_write_permission( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get status
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_status( $request ) {
		$checkup = AS_PHP_Checkup::get_instance();
		$results = $checkup->get_check_results();
		
		// Calculate overall health score
		$total_checks = 0;
		$optimal_checks = 0;
		$acceptable_checks = 0;
		$warning_checks = 0;
		
		foreach ( $results as $category ) {
			foreach ( $category['items'] as $item ) {
				$total_checks++;
				switch ( $item['status'] ) {
					case 'optimal':
						$optimal_checks++;
						break;
					case 'acceptable':
						$acceptable_checks++;
						break;
					case 'warning':
						$warning_checks++;
						break;
				}
			}
		}
		
		$health_score = $total_checks > 0 ? 
		               round( ( ( $optimal_checks * 100 ) + ( $acceptable_checks * 50 ) ) / $total_checks ) : 0;
		
		$response_data = array(
			'success'      => true,
			'health_score' => $health_score,
			'summary'      => array(
				'total'      => $total_checks,
				'optimal'    => $optimal_checks,
				'acceptable' => $acceptable_checks,
				'warning'    => $warning_checks,
			),
			'results'      => $results,
			'last_check'   => get_option( 'as_php_checkup_last_check', current_time( 'timestamp' ) ),
			'version'      => AS_PHP_CHECKUP_VERSION,
		);
		
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get system info
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_system_info( $request ) {
		$checkup = AS_PHP_Checkup::get_instance();
		$system_info = $checkup->get_system_info();
		
		$response_data = array(
			'success'     => true,
			'system_info' => $system_info,
			'timestamp'   => current_time( 'timestamp' ),
		);
		
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get plugin analysis
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_plugin_analysis( $request ) {
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		
		// Check if refresh is requested
		if ( $request->get_param( 'refresh' ) ) {
			$analyzer->analyze_all_plugins();
		}
		
		$analyzed_data = $analyzer->get_analyzed_data();
		$combined_requirements = $analyzer->get_combined_requirements();
		
		$response_data = array(
			'success'               => true,
			'analyzed_plugins'      => $analyzed_data,
			'combined_requirements' => $combined_requirements,
			'analysis_time'         => get_option( 'as_php_checkup_analysis_time', 0 ),
			'active_plugins_count'  => count( get_option( 'active_plugins', array() ) ),
		);
		
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Refresh check
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function refresh_check( $request ) {
		// Clear cache
		wp_cache_flush();
		
		// Update last check time
		update_option( 'as_php_checkup_last_check', current_time( 'timestamp' ) );
		
		// Re-analyze plugins
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$analyzer->analyze_all_plugins();
		
		// Get fresh results
		$checkup = AS_PHP_Checkup::get_instance();
		$results = $checkup->get_check_results();
		
		$response_data = array(
			'success' => true,
			'message' => __( 'Check refreshed successfully', 'as-php-checkup' ),
			'results' => $results,
			'time'    => current_time( 'timestamp' ),
		);
		
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Export report
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function export_report( $request ) {
		$format = $request->get_param( 'format' );
		
		$checkup = AS_PHP_Checkup::get_instance();
		$results = $checkup->get_check_results();
		$system_info = $checkup->get_system_info();
		
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$plugin_analysis = $analyzer->get_analyzed_data();
		
		if ( 'csv' === $format ) {
			$content = $this->generate_csv_report( $results, $system_info, $plugin_analysis );
			$filename = 'php-checkup-report-' . date( 'Y-m-d-H-i-s' ) . '.csv';
			$mime_type = 'text/csv';
		} else {
			$export_data = array(
				'report'          => 'AS PHP Checkup Report',
				'version'         => AS_PHP_CHECKUP_VERSION,
				'generated'       => current_time( 'mysql' ),
				'results'         => $results,
				'system_info'     => $system_info,
				'plugin_analysis' => $plugin_analysis,
			);
			$content = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
			$filename = 'php-checkup-report-' . date( 'Y-m-d-H-i-s' ) . '.json';
			$mime_type = 'application/json';
		}
		
		$response_data = array(
			'success'   => true,
			'content'   => $content,
			'filename'  => $filename,
			'mime_type' => $mime_type,
		);
		
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Generate CSV report
	 *
	 * @since 1.1.0
	 * @param array $results Check results.
	 * @param array $system_info System information.
	 * @param array $plugin_analysis Plugin analysis data.
	 * @return string
	 */
	private function generate_csv_report( $results, $system_info, $plugin_analysis ) {
		$csv = array();
		
		// Header
		$csv[] = 'AS PHP Checkup Report';
		$csv[] = 'Generated: ' . current_time( 'mysql' );
		$csv[] = 'Version: ' . AS_PHP_CHECKUP_VERSION;
		$csv[] = '';
		
		// PHP Settings
		$csv[] = 'PHP SETTINGS';
		$csv[] = 'Category,Setting,Current Value,Recommended,Minimum,Status,Required By';
		
		foreach ( $results as $category_key => $category ) {
			foreach ( $category['items'] as $key => $item ) {
				$csv[] = sprintf(
					'%s,%s,%s,%s,%s,%s,%s',
					$category['label'],
					$item['label'],
					$item['current'] ? $item['current'] : 'Not set',
					$item['recommended'],
					$item['minimum'],
					ucfirst( $item['status'] ),
					! empty( $item['source'] ) ? $item['source'] : 'Base recommendation'
				);
			}
		}
		
		// Plugin Analysis
		if ( ! empty( $plugin_analysis ) ) {
			$csv[] = '';
			$csv[] = 'PLUGIN REQUIREMENTS';
			$csv[] = 'Plugin,Requirement,Value';
			
			foreach ( $plugin_analysis as $plugin_file => $requirements ) {
				foreach ( $requirements as $key => $value ) {
					if ( 'name' !== $key ) {
						$csv[] = sprintf(
							'%s,%s,%s',
							$requirements['name'],
							$key,
							$value
						);
					}
				}
			}
		}
		
		// System Info
		$csv[] = '';
		$csv[] = 'SYSTEM INFORMATION';
		$csv[] = 'Component,Property,Value';
		
		// WordPress info
		foreach ( $system_info['wordpress'] as $key => $value ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
			$display_value = is_bool( $value ) ? ( $value ? 'Yes' : 'No' ) : $value;
			$csv[] = sprintf( 'WordPress,%s,%s', $label, $display_value );
		}
		
		// Server info
		foreach ( $system_info['server'] as $key => $value ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
			$csv[] = sprintf( 'Server,%s,%s', $label, $value );
		}
		
		// PHP Extensions
		foreach ( $system_info['php_extensions'] as $extension => $loaded ) {
			$csv[] = sprintf( 'PHP Extension,%s,%s', strtoupper( $extension ), $loaded ? 'Loaded' : 'Not Loaded' );
		}
		
		return implode( "\n", $csv );
	}

	/**
	 * Get status schema
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_status_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'status',
			'type'       => 'object',
			'properties' => array(
				'success'      => array(
					'type' => 'boolean',
				),
				'health_score' => array(
					'type' => 'integer',
				),
				'summary'      => array(
					'type' => 'object',
				),
				'results'      => array(
					'type' => 'object',
				),
				'last_check'   => array(
					'type' => 'integer',
				),
				'version'      => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Get system info schema
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_system_info_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'system-info',
			'type'       => 'object',
			'properties' => array(
				'success'     => array(
					'type' => 'boolean',
				),
				'system_info' => array(
					'type' => 'object',
				),
				'timestamp'   => array(
					'type' => 'integer',
				),
			),
		);
	}

	/**
	 * Get plugin analysis schema
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_plugin_analysis_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'plugin-analysis',
			'type'       => 'object',
			'properties' => array(
				'success'               => array(
					'type' => 'boolean',
				),
				'analyzed_plugins'      => array(
					'type' => 'object',
				),
				'combined_requirements' => array(
					'type' => 'object',
				),
				'analysis_time'         => array(
					'type' => 'integer',
				),
				'active_plugins_count'  => array(
					'type' => 'integer',
				),
			),
		);
	}
}