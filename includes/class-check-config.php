<?php
/**
 * Check Configuration Manager
 *
 * @package AS_PHP_Checkup
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup_Check_Config class
 *
 * Manages which checks are enabled/disabled and their severity levels
 *
 * @since 1.4.0
 */
class AS_PHP_Checkup_Check_Config {

	/**
	 * Instance of this class
	 *
	 * @since 1.4.0
	 * @var AS_PHP_Checkup_Check_Config|null
	 */
	private static $instance = null;

	/**
	 * Check severity levels
	 *
	 * @since 1.4.0
	 * @var array
	 */
	const SEVERITY_CRITICAL = 'critical';   // Security-related, cannot be disabled
	const SEVERITY_RECOMMENDED = 'recommended'; // Should be enabled, can be disabled
	const SEVERITY_OPTIONAL = 'optional';   // Can be disabled by default

	/**
	 * Check severity configuration
	 *
	 * Maps check keys to their severity level and whether they can be disabled
	 *
	 * @since 1.4.0
	 * @var array
	 */
	private $check_severities = array(
		// Basic Settings - Mix of critical and recommended
		'memory_limit'         => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Required for WordPress and most plugins to function properly',
		),
		'max_execution_time'   => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Prevents timeouts during long operations',
		),
		'max_input_time'       => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Required for file uploads and form processing',
		),
		'max_input_vars'       => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Required for complex forms and admin pages',
		),
		'post_max_size'        => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Required for file uploads',
		),
		'upload_max_filesize'  => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Controls maximum upload file size',
		),
		'max_file_uploads'     => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Only needed for bulk upload features',
		),
		'allow_url_fopen'      => array(
			'severity'     => self::SEVERITY_RECOMMENDED,
			'disableable'  => true,
			'category'     => 'basic',
			'reason'       => 'Required for many WordPress core features and plugins',
		),

		// Session Settings - Security-related (CRITICAL)
		'session.gc_maxlifetime'    => array(
			'severity'     => self::SEVERITY_CRITICAL,
			'disableable'  => false,
			'category'     => 'session',
			'reason'       => 'Security: Prevents session fixation attacks',
		),
		'session.save_handler'      => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'session',
			'reason'       => 'Performance optimization, not security-critical',
		),
		'session.cookie_httponly'   => array(
			'severity'     => self::SEVERITY_CRITICAL,
			'disableable'  => false,
			'category'     => 'session',
			'reason'       => 'Security: Prevents XSS attacks on session cookies',
		),
		'session.use_only_cookies'  => array(
			'severity'     => self::SEVERITY_CRITICAL,
			'disableable'  => false,
			'category'     => 'session',
			'reason'       => 'Security: Prevents session hijacking via URL',
		),
		'session.cookie_secure'     => array(
			'severity'     => self::SEVERITY_CRITICAL,
			'disableable'  => false,
			'category'     => 'session',
			'reason'       => 'Security: Prevents session hijacking on HTTPS sites',
		),

		// OPcache Settings - All optional (performance)
		'opcache.enable'                  => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'Performance optimization, not required',
		),
		'opcache.memory_consumption'      => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'OPcache tuning, only relevant if OPcache is enabled',
		),
		'opcache.max_accelerated_files'   => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'OPcache tuning, only relevant if OPcache is enabled',
		),
		'opcache.validate_timestamps'     => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'Development convenience, not production-critical',
		),
		'opcache.revalidate_freq'         => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'OPcache tuning, only relevant if OPcache is enabled',
		),
		'opcache.save_comments'           => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'Required for some frameworks, but not WordPress core',
		),
		'opcache.interned_strings_buffer' => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'opcache',
			'reason'       => 'OPcache tuning, only relevant if OPcache is enabled',
		),

		// Performance Settings - Optional
		'realpath_cache_size'    => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'performance',
			'reason'       => 'Performance optimization, not required',
		),
		'realpath_cache_ttl'     => array(
			'severity'     => self::SEVERITY_OPTIONAL,
			'disableable'  => true,
			'category'     => 'performance',
			'reason'       => 'Performance optimization, not required',
		),
	);

	/**
	 * Configuration profiles
	 *
	 * @since 1.4.0
	 * @var array
	 */
	private $profiles = array(
		'security_focused' => array(
			'name'        => 'Security Focused',
			'description' => 'Only critical security checks enabled',
			'checks'      => array(),
		),
		'balanced' => array(
			'name'        => 'Balanced',
			'description' => 'Security + recommended checks (default)',
			'checks'      => array(),
		),
		'complete' => array(
			'name'        => 'Complete',
			'description' => 'All checks including optional performance checks',
			'checks'      => array(),
		),
		'custom' => array(
			'name'        => 'Custom',
			'description' => 'Custom configuration',
			'checks'      => array(),
		),
	);

	/**
	 * Constructor
	 *
	 * @since 1.4.0
	 */
	private function __construct() {
		$this->init_profiles();
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.4.0
	 * @return AS_PHP_Checkup_Check_Config
	 */
	public static function get_instance(): AS_PHP_Checkup_Check_Config {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize configuration profiles
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private function init_profiles(): void {
		// Security Focused: Only critical checks
		foreach ( $this->check_severities as $check => $config ) {
			if ( self::SEVERITY_CRITICAL === $config['severity'] ) {
				$this->profiles['security_focused']['checks'][ $check ] = true;
			}
		}

		// Balanced: Critical + Recommended
		foreach ( $this->check_severities as $check => $config ) {
			if ( self::SEVERITY_CRITICAL === $config['severity'] ||
			     self::SEVERITY_RECOMMENDED === $config['severity'] ) {
				$this->profiles['balanced']['checks'][ $check ] = true;
			}
		}

		// Complete: All checks
		foreach ( $this->check_severities as $check => $config ) {
			$this->profiles['complete']['checks'][ $check ] = true;
		}
	}

	/**
	 * Check if a specific check is enabled
	 *
	 * @since 1.4.0
	 * @param string $check_key Check key to verify.
	 * @return bool
	 */
	public function is_check_enabled( string $check_key ): bool {
		$current_config = $this->get_current_configuration();

		// Critical checks are always enabled
		if ( isset( $this->check_severities[ $check_key ] ) &&
		     self::SEVERITY_CRITICAL === $this->check_severities[ $check_key ]['severity'] ) {
			return true;
		}

		// Check user configuration
		return isset( $current_config[ $check_key ] ) && true === $current_config[ $check_key ];
	}

	/**
	 * Get current configuration
	 *
	 * @since 1.4.0
	 * @return array
	 */
	public function get_current_configuration(): array {
		$saved_config = get_option( 'as_php_checkup_check_config', array() );

		// If no saved config, use balanced profile as default
		if ( empty( $saved_config ) ) {
			return $this->profiles['balanced']['checks'];
		}

		return $saved_config;
	}

	/**
	 * Get current profile name
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public function get_current_profile(): string {
		return get_option( 'as_php_checkup_active_profile', 'balanced' );
	}

	/**
	 * Save configuration
	 *
	 * @since 1.4.0
	 * @param array  $config  Configuration array.
	 * @param string $profile Profile name.
	 * @return bool
	 */
	public function save_configuration( array $config, string $profile = 'custom' ): bool {
		// Ensure critical checks are always enabled
		foreach ( $this->check_severities as $check => $check_config ) {
			if ( self::SEVERITY_CRITICAL === $check_config['severity'] ) {
				$config[ $check ] = true;
			}
		}

		update_option( 'as_php_checkup_check_config', $config );
		update_option( 'as_php_checkup_active_profile', $profile );

		return true;
	}

	/**
	 * Load profile
	 *
	 * @since 1.4.0
	 * @param string $profile_name Profile name to load.
	 * @return bool
	 */
	public function load_profile( string $profile_name ): bool {
		if ( ! isset( $this->profiles[ $profile_name ] ) ) {
			return false;
		}

		return $this->save_configuration(
			$this->profiles[ $profile_name ]['checks'],
			$profile_name
		);
	}

	/**
	 * Get all profiles
	 *
	 * @since 1.4.0
	 * @return array
	 */
	public function get_profiles(): array {
		return $this->profiles;
	}

	/**
	 * Get check severity
	 *
	 * @since 1.4.0
	 * @param string $check_key Check key.
	 * @return string|null
	 */
	public function get_check_severity( string $check_key ): ?string {
		return $this->check_severities[ $check_key ]['severity'] ?? null;
	}

	/**
	 * Can check be disabled?
	 *
	 * @since 1.4.0
	 * @param string $check_key Check key.
	 * @return bool
	 */
	public function can_disable_check( string $check_key ): bool {
		return $this->check_severities[ $check_key ]['disableable'] ?? true;
	}

	/**
	 * Get check configuration
	 *
	 * @since 1.4.0
	 * @param string $check_key Check key.
	 * @return array|null
	 */
	public function get_check_config( string $check_key ): ?array {
		return $this->check_severities[ $check_key ] ?? null;
	}

	/**
	 * Get all checks grouped by category and severity
	 *
	 * @since 1.4.0
	 * @return array
	 */
	public function get_checks_grouped(): array {
		$grouped = array(
			'critical'    => array(),
			'recommended' => array(),
			'optional'    => array(),
		);

		foreach ( $this->check_severities as $check => $config ) {
			$grouped[ $config['severity'] ][ $check ] = $config;
		}

		return $grouped;
	}

	/**
	 * Get statistics about current configuration
	 *
	 * @since 1.4.0
	 * @return array
	 */
	public function get_config_statistics(): array {
		$current_config = $this->get_current_configuration();
		$stats = array(
			'total_checks'       => count( $this->check_severities ),
			'enabled_checks'     => 0,
			'critical_enabled'   => 0,
			'recommended_enabled'=> 0,
			'optional_enabled'   => 0,
			'profile'            => $this->get_current_profile(),
		);

		foreach ( $this->check_severities as $check => $config ) {
			if ( $this->is_check_enabled( $check ) ) {
				$stats['enabled_checks']++;

				switch ( $config['severity'] ) {
					case self::SEVERITY_CRITICAL:
						$stats['critical_enabled']++;
						break;
					case self::SEVERITY_RECOMMENDED:
						$stats['recommended_enabled']++;
						break;
					case self::SEVERITY_OPTIONAL:
						$stats['optional_enabled']++;
						break;
				}
			}
		}

		return $stats;
	}
}
