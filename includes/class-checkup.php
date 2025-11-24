<?php
/**
 * Core Checkup Class
 *
 * @package AS_PHP_Checkup
 * @since 1.0.0
 * @version 1.3.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup class
 *
 * @since 1.0.0
 */
class AS_PHP_Checkup {

	/**
	 * Instance of this class
	 *
	 * @since 1.0.0
	 * @var AS_PHP_Checkup|null
	 */
	private static $instance = null;

	/**
	 * Cache key
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $cache_key = 'as_php_checkup_results';

	/**
	 * Cache expiration time
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $cache_expiration = 300; // 5 minutes

	/**
	 * Recommended settings
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $recommended_settings = array(
		'basic' => array(
			'memory_limit' => array(
				'label'       => 'Memory Limit',
				'recommended' => '256M',
				'minimum'     => '128M',
				'type'        => 'memory',
				'description' => 'The maximum amount of memory a script may consume',
			),
			'max_execution_time' => array(
				'label'       => 'Max Execution Time',
				'recommended' => '120',
				'minimum'     => '60',
				'type'        => 'integer',
				'description' => 'Maximum execution time of each script, in seconds',
			),
			'max_input_time' => array(
				'label'       => 'Max Input Time',
				'recommended' => '120',
				'minimum'     => '60',
				'type'        => 'integer',
				'description' => 'Maximum amount of time each script may spend parsing request data',
			),
			'max_input_vars' => array(
				'label'       => 'Max Input Vars',
				'recommended' => '3000',
				'minimum'     => '1000',
				'type'        => 'integer',
				'description' => 'Maximum number of input variables allowed',
			),
			'post_max_size' => array(
				'label'       => 'Post Max Size',
				'recommended' => '64M',
				'minimum'     => '32M',
				'type'        => 'memory',
				'description' => 'Maximum size of POST data that PHP will accept',
			),
			'upload_max_filesize' => array(
				'label'       => 'Upload Max Filesize',
				'recommended' => '64M',
				'minimum'     => '32M',
				'type'        => 'memory',
				'description' => 'Maximum allowed size for uploaded files',
			),
			'max_file_uploads' => array(
				'label'       => 'Max File Uploads',
				'recommended' => '50',
				'minimum'     => '20',
				'type'        => 'integer',
				'description' => 'Maximum number of files that can be uploaded simultaneously',
			),
			'allow_url_fopen' => array(
				'label'       => 'Allow URL Fopen',
				'recommended' => 'On',
				'minimum'     => 'On',
				'type'        => 'boolean_string',
				'description' => 'Whether to allow treatment of URLs as files',
			),
		),
		'session' => array(
			'session.gc_maxlifetime' => array(
				'label'       => 'Session GC Max Lifetime',
				'recommended' => '1440',
				'minimum'     => '1440',
				'type'        => 'integer',
				'description' => 'Number of seconds after which data will be seen as garbage',
			),
			'session.save_handler' => array(
				'label'       => 'Session Save Handler',
				'recommended' => 'files',
				'minimum'     => 'files',
				'type'        => 'string',
				'description' => 'Handler used to store/retrieve session data',
			),
			'session.cookie_httponly' => array(
				'label'       => 'Cookie HTTPOnly',
				'recommended' => '1',
				'minimum'     => '1',
				'type'        => 'boolean',
				'description' => 'Marks the cookie as accessible only through the HTTP protocol',
			),
			'session.use_only_cookies' => array(
				'label'       => 'Use Only Cookies',
				'recommended' => '1',
				'minimum'     => '1',
				'type'        => 'boolean',
				'description' => 'Specifies whether the module will only use cookies',
			),
			'session.cookie_secure' => array(
				'label'       => 'Cookie Secure',
				'recommended' => '1',
				'minimum'     => '0',
				'type'        => 'boolean',
				'description' => 'Marks the cookie as secure (HTTPS only)',
			),
		),
		'opcache' => array(
			'opcache.enable' => array(
				'label'       => 'OPcache Enable',
				'recommended' => '1',
				'minimum'     => '1',
				'type'        => 'boolean',
				'description' => 'Enable the OPcache for better performance',
			),
			'opcache.memory_consumption' => array(
				'label'       => 'OPcache Memory',
				'recommended' => '128',
				'minimum'     => '64',
				'type'        => 'integer',
				'description' => 'The OPcache shared memory storage size in MB',
			),
			'opcache.max_accelerated_files' => array(
				'label'       => 'Max Accelerated Files',
				'recommended' => '10000',
				'minimum'     => '4000',
				'type'        => 'integer',
				'description' => 'Maximum number of scripts that can be cached',
			),
			'opcache.validate_timestamps' => array(
				'label'       => 'Validate Timestamps',
				'recommended' => '1',
				'minimum'     => '1',
				'type'        => 'boolean',
				'description' => 'Check for updated scripts based on timestamps',
			),
			'opcache.revalidate_freq' => array(
				'label'       => 'OPcache Revalidate Frequency',
				'recommended' => '1',
				'minimum'     => '2',
				'type'        => 'integer_inverse', // Lower is better!
				'description' => 'Frequency of checking for updated scripts in seconds',
			),
			'opcache.save_comments' => array(
				'label'       => 'Save Comments',
				'recommended' => '1',
				'minimum'     => '1',
				'type'        => 'boolean',
				'description' => 'Load comments from script files',
			),
			'opcache.interned_strings_buffer' => array(
				'label'       => 'Interned Strings Buffer',
				'recommended' => '16',
				'minimum'     => '8',
				'type'        => 'integer',
				'description' => 'Amount of memory for interned strings in MB',
			),
		),
		'performance' => array(
			'realpath_cache_size' => array(
				'label'       => 'Realpath Cache Size',
				'recommended' => '4096K',
				'minimum'     => '2048K',
				'type'        => 'memory',
				'description' => 'Size of the realpath cache',
			),
			'realpath_cache_ttl' => array(
				'label'       => 'Realpath Cache TTL',
				'recommended' => '120',
				'minimum'     => '60',
				'type'        => 'integer',
				'description' => 'Duration of time for which realpath information is cached',
			),
		),
	);

	/**
	 * Plugin requirements loaded from analyzer
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $plugin_requirements = array();

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Load plugin requirements on init
		add_action( 'init', array( $this, 'load_plugin_requirements' ) );
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AS_PHP_Checkup
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load plugin requirements from analyzer
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function load_plugin_requirements() {
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$this->plugin_requirements = $analyzer->get_aggregated_requirements();
		
		// Merge plugin requirements into recommended settings
		if ( ! empty( $this->plugin_requirements ) ) {
			foreach ( $this->plugin_requirements as $key => $value ) {
				if ( 'extensions' === $key || 'sources' === $key ) {
					continue;
				}
				
				// Find and update the corresponding setting
				foreach ( $this->recommended_settings as $category => &$settings ) {
					foreach ( $settings as $setting_key => &$config ) {
						if ( $this->normalize_setting_key( $setting_key ) === $this->normalize_setting_key( $key ) ) {
							// Update recommended value if plugin requires more
							if ( 'memory' === $config['type'] ) {
								$current_bytes = $this->convert_to_bytes( $config['recommended'] );
								$plugin_bytes = $this->convert_to_bytes( $value );
								if ( $plugin_bytes > $current_bytes ) {
									$config['recommended'] = $value;
								}
							} elseif ( 'integer' === $config['type'] ) {
								if ( intval( $value ) > intval( $config['recommended'] ) ) {
									$config['recommended'] = $value;
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Normalize setting key for comparison
	 *
	 * @since 1.1.0
	 * @param string $key Setting key.
	 * @return string
	 */
	private function normalize_setting_key( $key ) {
		return str_replace( array( '_', '.' ), '', strtolower( $key ) );
	}

	/**
	 * Get current PHP settings
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_current_settings() {
		$current = array();
		
		// Basic settings
		$current['memory_limit'] = ini_get( 'memory_limit' );
		$current['max_execution_time'] = ini_get( 'max_execution_time' );
		$current['max_input_time'] = ini_get( 'max_input_time' );
		$current['max_input_vars'] = ini_get( 'max_input_vars' );
		$current['post_max_size'] = ini_get( 'post_max_size' );
		$current['upload_max_filesize'] = ini_get( 'upload_max_filesize' );
		$current['max_file_uploads'] = ini_get( 'max_file_uploads' );
		$current['allow_url_fopen'] = ini_get( 'allow_url_fopen' );
		
		// Session settings
		$current['session.gc_maxlifetime'] = ini_get( 'session.gc_maxlifetime' );
		$current['session.save_handler'] = ini_get( 'session.save_handler' );
		$current['session.cookie_httponly'] = ini_get( 'session.cookie_httponly' );
		$current['session.use_only_cookies'] = ini_get( 'session.use_only_cookies' );
		$current['session.cookie_secure'] = ini_get( 'session.cookie_secure' );
		
		// OPcache settings
		$current['opcache.enable'] = ini_get( 'opcache.enable' );
		$current['opcache.memory_consumption'] = ini_get( 'opcache.memory_consumption' );
		$current['opcache.max_accelerated_files'] = ini_get( 'opcache.max_accelerated_files' );
		$current['opcache.interned_strings_buffer'] = ini_get( 'opcache.interned_strings_buffer' );
		$current['opcache.validate_timestamps'] = ini_get( 'opcache.validate_timestamps' );
		$current['opcache.revalidate_freq'] = ini_get( 'opcache.revalidate_freq' );
		$current['opcache.save_comments'] = ini_get( 'opcache.save_comments' );
		$current['opcache.fast_shutdown'] = ini_get( 'opcache.fast_shutdown' );
		
		// Performance settings
		$current['realpath_cache_size'] = ini_get( 'realpath_cache_size' );
		$current['realpath_cache_ttl'] = ini_get( 'realpath_cache_ttl' );
		$current['max_input_nesting_level'] = ini_get( 'max_input_nesting_level' );
		$current['zlib.output_compression'] = ini_get( 'zlib.output_compression' );
		
		return $current;
	}

	/**
	 * Check if a value meets requirements
	 *
	 * @since 1.0.0
	 * @version 1.3.1 - Fixed status mapping and integer_inverse type
	 * @param mixed  $current Current value.
	 * @param mixed  $recommended Recommended value.
	 * @param mixed  $minimum Minimum value.
	 * @param string $type Type of comparison.
	 * @return string Status: ok, warning, or error
	 */
	public function check_value( $current, $recommended, $minimum, $type ) {
		if ( empty( $current ) && 'boolean' !== $type && 'boolean_string' !== $type ) {
			return 'error';
		}
		
		switch ( $type ) {
			case 'string':
				if ( $current === $recommended ) {
					return 'ok';
				}
				return 'warning';
				
			case 'version':
				if ( version_compare( $current, $recommended, '>=' ) ) {
					return 'ok';
				} elseif ( version_compare( $current, $minimum, '>=' ) ) {
					return 'warning';
				}
				return 'error';
				
			case 'memory':
				$current_bytes = $this->convert_to_bytes( $current );
				$recommended_bytes = $this->convert_to_bytes( $recommended );
				$minimum_bytes = $this->convert_to_bytes( $minimum );
				
				if ( $current_bytes >= $recommended_bytes ) {
					return 'ok';
				} elseif ( $current_bytes >= $minimum_bytes ) {
					return 'warning';
				}
				return 'error';
				
			case 'integer':
				$current_int = intval( $current );
				$recommended_int = intval( $recommended );
				$minimum_int = intval( $minimum );
				
				if ( $current_int >= $recommended_int ) {
					return 'ok';
				} elseif ( $current_int >= $minimum_int ) {
					return 'warning';
				}
				return 'error';
				
			case 'integer_inverse': // Lower values are better (like revalidate_freq)
				$current_int = intval( $current );
				$recommended_int = intval( $recommended );
				$minimum_int = intval( $minimum );
				
				if ( $current_int <= $recommended_int ) {
					return 'ok';
				} elseif ( $current_int <= $minimum_int ) {
					return 'warning';
				}
				return 'error';
				
			case 'boolean':
				$current_bool = ( '1' === $current || 1 === $current || true === $current );
				$recommended_bool = ( '1' === $recommended || 1 === $recommended || true === $recommended );
				
				if ( $current_bool === $recommended_bool ) {
					return 'ok';
				}
				return 'warning';
				
			case 'boolean_string':
				$current_bool = ( 'On' === $current || '1' === $current || 1 === $current );
				$recommended_bool = ( 'On' === $recommended || '1' === $recommended || 1 === $recommended );
				
				if ( $current_bool === $recommended_bool ) {
					return 'ok';
				}
				return 'warning';
				
			default:
				// For unknown types, do simple string comparison
				if ( $current === $recommended ) {
					return 'ok';
				}
				return 'warning';
		}
	}

	/**
	 * Convert memory string to bytes
	 *
	 * @since 1.0.0
	 * @param string $value Memory value.
	 * @return int
	 */
	private function convert_to_bytes( $value ) {
		if ( is_numeric( $value ) ) {
			return intval( $value );
		}
		
		$value = trim( $value );
		$last = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = intval( $value );
		
		switch ( $last ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}
		
		return $value;
	}

	/**
	 * Get all check results
	 *
	 * @since 1.0.0
	 * @version 1.3.1 - Added status counts
	 * @return array
	 */
	public function get_check_results() {
		// Try to get from cache first
		$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();
		$cached_results = $cache_manager->get( 'check_results' );
		
		if ( false !== $cached_results ) {
			return $cached_results;
		}
		
		// Reload plugin requirements to get latest
		$this->load_plugin_requirements();
		
		$current_settings = $this->get_current_settings();
		$results = array();
		
		foreach ( $this->recommended_settings as $category => $settings ) {
			$results[ $category ] = array(
				'label'  => $this->get_category_label( $category ),
				'items'  => array(),
				'passed' => 0,
				'warnings' => 0,
				'failed' => 0,
			);
			
			foreach ( $settings as $key => $config ) {
				$current_value = isset( $current_settings[ $key ] ) ? $current_settings[ $key ] : '';
				$status = $this->check_value(
					$current_value,
					$config['recommended'],
					$config['minimum'],
					$config['type']
				);
				
				// Update status counts
				if ( 'ok' === $status ) {
					$results[ $category ]['passed']++;
				} elseif ( 'warning' === $status ) {
					$results[ $category ]['warnings']++;
				} elseif ( 'error' === $status ) {
					$results[ $category ]['failed']++;
				}
				
				// Add source information if from plugin
				$source = '';
				if ( isset( $this->plugin_requirements['sources'][ $key ] ) ) {
					$source = implode( ', ', $this->plugin_requirements['sources'][ $key ] );
				}
				
				// Prepare message based on status
				$message = '';
				if ( 'warning' === $status || 'error' === $status ) {
					if ( 'integer_inverse' === $config['type'] ) {
						$message = sprintf( 
							__( 'Should be %s or lower', 'as-php-checkup' ), 
							$config['recommended'] 
						);
					} else {
						$message = sprintf( 
							__( 'Should be at least %s', 'as-php-checkup' ), 
							$config['minimum'] 
						);
					}
				}
				
				$results[ $category ]['items'][ $key ] = array(
					'setting'     => $key,
					'label'       => $config['label'],
					'current'     => $current_value,
					'recommended' => $config['recommended'],
					'minimum'     => $config['minimum'],
					'status'      => $status,
					'description' => $config['description'],
					'type'        => $config['type'],
					'source'      => $source,
					'message'     => $message,
				);
			}
		}
		
		// Cache the results
		$cache_manager->set( 'check_results', $results, 300 ); // Cache for 5 minutes
		
		// Update last check time only when actually running a check
		update_option( 'as_php_checkup_last_check', current_time( 'timestamp' ) );
		
		return $results;
	}

	/**
	 * Get category label
	 *
	 * @since 1.0.0
	 * @param string $category Category key.
	 * @return string
	 */
	private function get_category_label( $category ) {
		$labels = array(
			'basic'       => __( 'Basic PHP Settings', 'as-php-checkup' ),
			'session'     => __( 'Session Settings', 'as-php-checkup' ),
			'opcache'     => __( 'OPcache Settings', 'as-php-checkup' ),
			'performance' => __( 'Performance Settings', 'as-php-checkup' ),
		);
		
		return isset( $labels[ $category ] ) ? $labels[ $category ] : $category;
	}

	/**
	 * Get health score
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_health_score() {
		$results = $this->get_check_results();
		$total_checks = 0;
		$passed_checks = 0;
		$warning_weight = 0.5; // Warnings count as half
		
		foreach ( $results as $category ) {
			foreach ( $category['items'] as $item ) {
				$total_checks++;
				if ( 'ok' === $item['status'] ) {
					$passed_checks++;
				} elseif ( 'warning' === $item['status'] ) {
					$passed_checks += $warning_weight;
				}
			}
		}
		
		if ( $total_checks === 0 ) {
			return 100;
		}
		
		return round( ( $passed_checks / $total_checks ) * 100 );
	}

	/**
	 * Get system information
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_system_info() {
		// Try to get from cache first
		$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();
		$cached_info = $cache_manager->get( 'system_info' );
		
		if ( false !== $cached_info ) {
			return $cached_info;
		}
		
		global $wpdb;
		
		$system_info = array(
			'php_version'        => PHP_VERSION,
			'php_sapi'           => php_sapi_name(),
			'php_user'           => get_current_user(),
			'php_ini_path'       => php_ini_loaded_file() ?: 'Not available',
			'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'Not available',
			'os'                 => PHP_OS,
			'architecture'       => php_uname( 'm' ),
			'database_version'   => $wpdb->db_version(),
			'wordpress_version'  => get_bloginfo( 'version' ),
			'loaded_extensions'  => get_loaded_extensions(),
			'disabled_functions' => ini_get( 'disable_functions' ) ?: 'None',
		);
		
		// Cache the system info for longer (30 minutes)
		$cache_manager->set( 'system_info', $system_info, 1800 );
		
		return $system_info;
	}

	/**
	 * Run full checkup
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function run_checkup() {
		// Clear cache first
		$this->clear_cache();
		
		// Get fresh results (will be cached automatically)
		$results = $this->get_check_results();
		
		return $results;
	}

	/**
	 * Clear cache
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_cache() {
		// Clear old transient (for backwards compatibility)
		delete_transient( $this->cache_key );
		
		// Clear new cache manager caches
		$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();
		$cache_manager->delete( 'check_results' );
		$cache_manager->delete( 'system_info' );
		
		// Also clear plugin analyzer cache
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$analyzer->clear_cache();
		
		// Update last check time when cache is cleared
		update_option( 'as_php_checkup_last_check', 0 );
	}
	
	/**
	 * Get last actual check time (not from cache)
	 *
	 * @since 1.3.3
	 * @return int
	 */
	public function get_last_check_time() {
		return get_option( 'as_php_checkup_last_check', 0 );
	}
	
	/**
	 * Force refresh check (clear cache and run new check)
	 *
	 * @since 1.3.3
	 * @return array
	 */
	public function force_refresh() {
		return $this->run_checkup();
	}
}