<?php
/**
 * Plugin Analyzer Class
 *
 * @package AS_PHP_Checkup
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup_Plugin_Analyzer class
 *
 * @since 1.1.0
 */
class AS_PHP_Checkup_Plugin_Analyzer {

	/**
	 * Instance of this class
	 *
	 * @since 1.1.0
	 * @var AS_PHP_Checkup_Plugin_Analyzer|null
	 */
	private static $instance = null;

	/**
	 * Cache manager instance
	 *
	 * @since 1.3.0
	 * @var AS_PHP_Checkup_Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Known plugin requirements database
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $known_requirements = array();

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		$this->init_known_requirements();
		// Initialize cache manager - New in 1.3.0
		$this->cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.1.0
	 * @return AS_PHP_Checkup_Plugin_Analyzer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize known plugin requirements
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function init_known_requirements() {
		// Popular plugins and their typical requirements
		$this->known_requirements = array(
			'woocommerce/woocommerce.php' => array(
				'memory_limit'        => '256M',
				'max_execution_time'  => 300,
				'max_input_vars'      => 3000,
				'upload_max_filesize' => '64M',
				'post_max_size'       => '64M',
				'extensions'          => array( 'curl', 'dom', 'hash', 'iconv', 'json', 'mbstring', 'openssl', 'pcre', 'xml', 'zip' ),
			),
			'wordpress-seo/wp-seo.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
				'extensions'      => array( 'xml', 'openssl' ),
			),
			'elementor/elementor.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 300,
				'max_input_vars'      => 5000,
				'upload_max_filesize' => '128M',
				'post_max_size'       => '128M',
			),
			'elementor-pro/elementor-pro.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 300,
				'max_input_vars'      => 5000,
			),
			'wp-rocket/wp-rocket.php' => array(
				'memory_limit'    => '256M',
				'extensions'      => array( 'curl', 'dom' ),
			),
			'all-in-one-wp-migration/all-in-one-wp-migration.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 0,
				'upload_max_filesize' => '2048M',
				'post_max_size'       => '2048M',
			),
			'wpforms-lite/wpforms.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'wpforms/wpforms.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 5000,
			),
			'contact-form-7/wp-contact-form-7.php' => array(
				'memory_limit'    => '128M',
				'max_input_vars'  => 1000,
			),
			'updraftplus/updraftplus.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 0,
				'upload_max_filesize' => '2048M',
				'post_max_size'       => '2048M',
			),
			'backwpup/backwpup.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 0,
				'extensions'          => array( 'curl', 'zip' ),
			),
			'duplicator/duplicator.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 0,
				'upload_max_filesize' => '2048M',
				'post_max_size'       => '2048M',
				'extensions'          => array( 'zip' ),
			),
			'wordfence/wordfence.php' => array(
				'memory_limit'    => '256M',
				'extensions'      => array( 'curl' ),
			),
			'sucuri-scanner/sucuri.php' => array(
				'memory_limit'    => '256M',
				'extensions'      => array( 'curl', 'json' ),
			),
			'w3-total-cache/w3-total-cache.php' => array(
				'memory_limit'    => '256M',
				'extensions'      => array( 'curl', 'zlib' ),
			),
			'wp-super-cache/wp-cache.php' => array(
				'memory_limit'    => '128M',
			),
			'autoptimize/autoptimize.php' => array(
				'memory_limit'    => '256M',
			),
			'wp-optimize/wp-optimize.php' => array(
				'memory_limit'    => '256M',
			),
			'jetpack/jetpack.php' => array(
				'memory_limit'    => '256M',
				'extensions'      => array( 'curl', 'xml', 'json' ),
			),
			'akismet/akismet.php' => array(
				'extensions'      => array( 'curl' ),
			),
			'buddypress/bp-loader.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'bbpress/bbpress.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'mailchimp-for-wp/mailchimp-for-wp.php' => array(
				'extensions'      => array( 'curl' ),
			),
			'really-simple-ssl/rlrsssl-really-simple-ssl.php' => array(
				'extensions'      => array( 'openssl' ),
			),
			'google-analytics-for-wordpress/googleanalytics.php' => array(
				'extensions'      => array( 'curl', 'json' ),
			),
			'redirection/redirection.php' => array(
				'memory_limit'    => '128M',
			),
			'broken-link-checker/broken-link-checker.php' => array(
				'memory_limit'    => '256M',
				'max_execution_time' => 300,
				'extensions'      => array( 'curl' ),
			),
			'wp-mail-smtp/wp_mail_smtp.php' => array(
				'extensions'      => array( 'openssl' ),
			),
			'advanced-custom-fields/acf.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'advanced-custom-fields-pro/acf.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 5000,
			),
			'custom-post-type-ui/custom-post-type-ui.php' => array(
				'memory_limit'    => '128M',
			),
			'wpml-multilingual-cms/sitepress.php' => array(
				'memory_limit'    => '512M',
				'max_input_vars'  => 5000,
				'extensions'      => array( 'mbstring', 'xml' ),
			),
			'polylang/polylang.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'translatepress-multilingual/index.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
				'extensions'      => array( 'mbstring' ),
			),
			'nextgen-gallery/nggallery.php' => array(
				'memory_limit'        => '256M',
				'upload_max_filesize' => '128M',
				'extensions'          => array( 'gd', 'imagick' ),
			),
			'envira-gallery-lite/envira-gallery-lite.php' => array(
				'memory_limit'        => '256M',
				'upload_max_filesize' => '128M',
				'extensions'          => array( 'gd' ),
			),
			'wp-smushit/wp-smush.php' => array(
				'memory_limit'        => '256M',
				'max_execution_time'  => 300,
				'extensions'          => array( 'gd', 'imagick' ),
			),
			'imagify/imagify.php' => array(
				'memory_limit'        => '256M',
				'extensions'          => array( 'curl', 'gd' ),
			),
			'shortpixel-image-optimiser/wp-shortpixel.php' => array(
				'memory_limit'        => '256M',
				'extensions'          => array( 'curl', 'gd' ),
			),
			'regenerate-thumbnails/regenerate-thumbnails.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 600,
				'extensions'          => array( 'gd', 'imagick' ),
			),
			'wps-hide-login/wps-hide-login.php' => array(
				'memory_limit'    => '64M',
			),
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => array(
				'memory_limit'    => '64M',
			),
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'seo-by-rank-math/rank-math.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
				'extensions'      => array( 'mbstring' ),
			),
			'the-events-calendar/the-events-calendar.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'event-organiser/event-organiser.php' => array(
				'memory_limit'    => '128M',
			),
			'give/give.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'wp-migrate-db/wp-migrate-db.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 0,
			),
			'better-search-replace/better-search-replace.php' => array(
				'memory_limit'        => '512M',
				'max_execution_time'  => 600,
			),
			'learnpress/learnpress.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'tutor/tutor.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'lifterlms/lifterlms.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
			'buddyboss-platform/buddyboss-platform.php' => array(
				'memory_limit'    => '512M',
				'max_input_vars'  => 5000,
				'extensions'      => array( 'curl', 'gd', 'mbstring' ),
			),
			'peepso-core/peepso.php' => array(
				'memory_limit'    => '256M',
				'max_input_vars'  => 3000,
			),
		);

		// Allow filtering of known requirements
		$this->known_requirements = apply_filters( 'as_php_checkup_known_requirements', $this->known_requirements );
	}

	/**
	 * Analyze all active plugins
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function analyze_all_plugins() {
		// Try to get from cache first - Enhanced with Cache Manager in 1.3.0
		$cached_analysis = $this->cache_manager->get( 'plugin_analysis' );
		if ( false !== $cached_analysis ) {
			return $cached_analysis;
		}

		$active_plugins = get_option( 'active_plugins', array() );
		$analysis_results = array();

		foreach ( $active_plugins as $plugin ) {
			$plugin_data = $this->analyze_plugin( $plugin );
			if ( $plugin_data ) {
				$analysis_results[ $plugin ] = $plugin_data;
			}
		}

		// Check for active network plugins in multisite
		if ( is_multisite() ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
			foreach ( $network_plugins as $plugin => $timestamp ) {
				$plugin_data = $this->analyze_plugin( $plugin );
				if ( $plugin_data ) {
					$analysis_results[ $plugin ] = $plugin_data;
				}
			}
		}

		// Store analysis results
		update_option( 'as_php_checkup_plugin_analysis', $analysis_results );
		update_option( 'as_php_checkup_last_analysis', current_time( 'timestamp' ) );

		// Cache the analysis - Enhanced with Cache Manager in 1.3.0
		$this->cache_manager->set( 'plugin_analysis', $analysis_results, DAY_IN_SECONDS );

		return $analysis_results;
	}

	/**
	 * Analyze individual plugin
	 *
	 * @since 1.1.0
	 * @param string $plugin Plugin file path.
	 * @return array|null
	 */
	private function analyze_plugin( $plugin ) {
		// Skip our own plugin
		if ( strpos( $plugin, 'as-php-checkup' ) !== false ) {
			return null;
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
		if ( ! file_exists( $plugin_file ) ) {
			return null;
		}

		$plugin_data = get_plugin_data( $plugin_file );
		if ( empty( $plugin_data['Name'] ) ) {
			return null;
		}

		$requirements = $this->extract_requirements( $plugin );

		return array(
			'name'          => $plugin_data['Name'],
			'version'       => $plugin_data['Version'],
			'author'        => $plugin_data['Author'],
			'requires_php'  => ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			'requirements'  => $requirements,
			'analyzed_at'   => current_time( 'timestamp' ),
		);
	}

	/**
	 * Extract requirements from plugin
	 *
	 * @since 1.1.0
	 * @param string $plugin Plugin file path.
	 * @return array
	 */
	private function extract_requirements( $plugin ) {
		// First check if we have known requirements
		if ( isset( $this->known_requirements[ $plugin ] ) ) {
			return $this->known_requirements[ $plugin ];
		}

		// Try to extract from plugin headers and readme
		$requirements = array();
		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
		$plugin_dir = dirname( $plugin_file );

		// Check readme.txt or README.md
		$readme_files = array(
			$plugin_dir . '/readme.txt',
			$plugin_dir . '/README.txt',
			$plugin_dir . '/readme.md',
			$plugin_dir . '/README.md',
		);

		foreach ( $readme_files as $readme_file ) {
			if ( file_exists( $readme_file ) ) {
				$readme_content = file_get_contents( $readme_file );
				$requirements = array_merge( $requirements, $this->parse_readme_requirements( $readme_content ) );
				break;
			}
		}

		// Check plugin main file for ini_set calls or defined requirements
		if ( file_exists( $plugin_file ) ) {
			$plugin_content = file_get_contents( $plugin_file );
			$requirements = array_merge( $requirements, $this->parse_plugin_requirements( $plugin_content ) );
		}

		// Check for composer.json
		$composer_file = $plugin_dir . '/composer.json';
		if ( file_exists( $composer_file ) ) {
			$composer_content = file_get_contents( $composer_file );
			$requirements = array_merge( $requirements, $this->parse_composer_requirements( $composer_content ) );
		}

		return $requirements;
	}

	/**
	 * Parse requirements from readme content
	 *
	 * @since 1.1.0
	 * @param string $content Readme content.
	 * @return array
	 */
	private function parse_readme_requirements( $content ) {
		$requirements = array();

		// Look for memory limit mentions
		if ( preg_match( '/memory[_\s]*limit[:\s]*(\d+[MG])/i', $content, $matches ) ) {
			$requirements['memory_limit'] = $matches[1];
		}

		// Look for max execution time
		if ( preg_match( '/max[_\s]*execution[_\s]*time[:\s]*(\d+)/i', $content, $matches ) ) {
			$requirements['max_execution_time'] = intval( $matches[1] );
		}

		// Look for max input vars
		if ( preg_match( '/max[_\s]*input[_\s]*vars[:\s]*(\d+)/i', $content, $matches ) ) {
			$requirements['max_input_vars'] = intval( $matches[1] );
		}

		// Look for upload size
		if ( preg_match( '/upload[_\s]*max[_\s]*filesize[:\s]*(\d+[MG])/i', $content, $matches ) ) {
			$requirements['upload_max_filesize'] = $matches[1];
		}

		// Look for PHP extensions
		$extensions = array();
		$common_extensions = array( 'curl', 'gd', 'imagick', 'mbstring', 'xml', 'json', 'openssl', 'zip', 'soap', 'iconv' );
		
		foreach ( $common_extensions as $ext ) {
			if ( stripos( $content, $ext ) !== false && stripos( $content, 'require' ) !== false ) {
				$extensions[ $ext ] = true;
			}
		}
		
		if ( ! empty( $extensions ) ) {
			$requirements['extensions'] = $extensions;
		}

		return $requirements;
	}

	/**
	 * Parse requirements from plugin content
	 *
	 * @since 1.1.0
	 * @param string $content Plugin content.
	 * @return array
	 */
	private function parse_plugin_requirements( $content ) {
		$requirements = array();

		// Look for ini_set calls
		if ( preg_match_all( '/ini_set\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]?([^\'"]+)[\'"]?\s*\)/', $content, $matches ) ) {
			foreach ( $matches[1] as $index => $setting ) {
				$value = $matches[2][ $index ];
				
				switch ( $setting ) {
					case 'memory_limit':
						$requirements['memory_limit'] = $value;
						break;
					case 'max_execution_time':
						$requirements['max_execution_time'] = intval( $value );
						break;
					case 'max_input_vars':
						$requirements['max_input_vars'] = intval( $value );
						break;
					case 'upload_max_filesize':
						$requirements['upload_max_filesize'] = $value;
						break;
					case 'post_max_size':
						$requirements['post_max_size'] = $value;
						break;
				}
			}
		}

		// Look for defined constants
		if ( preg_match( '/define\s*\(\s*[\'"]WP_MEMORY_LIMIT[\'"]\s*,\s*[\'"](\d+[MG])[\'"]/', $content, $matches ) ) {
			$requirements['memory_limit'] = $matches[1];
		}

		// Look for extension_loaded checks
		$extensions = array();
		if ( preg_match_all( '/extension_loaded\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches ) ) {
			foreach ( $matches[1] as $ext ) {
				$extensions[ $ext ] = true;
			}
		}

		// Look for function_exists checks that indicate extension requirements
		$function_to_extension = array(
			'curl_init'        => 'curl',
			'imagecreate'      => 'gd',
			'imagick'          => 'imagick',
			'mb_strlen'        => 'mbstring',
			'simplexml_load'   => 'xml',
			'json_encode'      => 'json',
			'openssl_encrypt'  => 'openssl',
			'zip_open'         => 'zip',
			'soap_client'      => 'soap',
			'iconv'            => 'iconv',
			'mysqli_connect'   => 'mysqli',
			'pdo'              => 'pdo',
		);

		foreach ( $function_to_extension as $function => $ext ) {
			if ( stripos( $content, $function ) !== false ) {
				$extensions[ $ext ] = true;
			}
		}

		if ( ! empty( $extensions ) ) {
			$requirements['extensions'] = $extensions;
		}

		return $requirements;
	}

	/**
	 * Parse requirements from composer.json
	 *
	 * @since 1.1.0
	 * @param string $content Composer.json content.
	 * @return array
	 */
	private function parse_composer_requirements( $content ) {
		$requirements = array();
		
		$composer = json_decode( $content, true );
		
		if ( ! $composer ) {
			return $requirements;
		}

		// Check PHP version requirement
		if ( isset( $composer['require']['php'] ) ) {
			// Extract PHP version, e.g., ">=7.4" -> "7.4"
			preg_match( '/[\d.]+/', $composer['require']['php'], $matches );
			if ( ! empty( $matches[0] ) ) {
				$requirements['php_version'] = $matches[0];
			}
		}

		// Check for PHP extensions
		$extensions = array();
		if ( isset( $composer['require'] ) ) {
			foreach ( $composer['require'] as $package => $version ) {
				if ( strpos( $package, 'ext-' ) === 0 ) {
					$ext = str_replace( 'ext-', '', $package );
					$extensions[ $ext ] = true;
				}
			}
		}

		if ( ! empty( $extensions ) ) {
			$requirements['extensions'] = $extensions;
		}

		return $requirements;
	}

	/**
	 * Get aggregated requirements from all plugins
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_aggregated_requirements() {
		// Try to get from cache first - Enhanced with Cache Manager in 1.3.0
		$cached_requirements = $this->cache_manager->get( 'plugin_requirements' );
		if ( false !== $cached_requirements ) {
			return $cached_requirements;
		}

		$analysis = $this->analyze_all_plugins();
		$aggregated = array(
			'memory_limit'        => '64M',
			'max_execution_time'  => 30,
			'max_input_time'      => 60,
			'max_input_vars'      => 1000,
			'upload_max_filesize' => '2M',
			'post_max_size'       => '8M',
			'extensions'          => array(),
		);

		foreach ( $analysis as $plugin_data ) {
			if ( empty( $plugin_data['requirements'] ) ) {
				continue;
			}

			$reqs = $plugin_data['requirements'];

			// Memory limit - take the highest
			if ( isset( $reqs['memory_limit'] ) ) {
				if ( $this->compare_memory_values( $reqs['memory_limit'], $aggregated['memory_limit'] ) > 0 ) {
					$aggregated['memory_limit'] = $reqs['memory_limit'];
				}
			}

			// Execution time - take the highest
			if ( isset( $reqs['max_execution_time'] ) ) {
				$aggregated['max_execution_time'] = max( $aggregated['max_execution_time'], intval( $reqs['max_execution_time'] ) );
			}

			// Input time - take the highest
			if ( isset( $reqs['max_input_time'] ) ) {
				$aggregated['max_input_time'] = max( $aggregated['max_input_time'], intval( $reqs['max_input_time'] ) );
			}

			// Input vars - take the highest
			if ( isset( $reqs['max_input_vars'] ) ) {
				$aggregated['max_input_vars'] = max( $aggregated['max_input_vars'], intval( $reqs['max_input_vars'] ) );
			}

			// Upload max filesize - take the highest
			if ( isset( $reqs['upload_max_filesize'] ) ) {
				if ( $this->compare_memory_values( $reqs['upload_max_filesize'], $aggregated['upload_max_filesize'] ) > 0 ) {
					$aggregated['upload_max_filesize'] = $reqs['upload_max_filesize'];
				}
			}

			// Post max size - take the highest
			if ( isset( $reqs['post_max_size'] ) ) {
				if ( $this->compare_memory_values( $reqs['post_max_size'], $aggregated['post_max_size'] ) > 0 ) {
					$aggregated['post_max_size'] = $reqs['post_max_size'];
				}
			}

			// Extensions - merge all required
			if ( isset( $reqs['extensions'] ) ) {
				$aggregated['extensions'] = array_merge( $aggregated['extensions'], $reqs['extensions'] );
			}
		}

		// Cache the aggregated requirements - Enhanced with Cache Manager in 1.3.0
		$this->cache_manager->set( 'plugin_requirements', $aggregated, 12 * HOUR_IN_SECONDS );

		return $aggregated;
	}

	/**
	 * Compare memory values
	 *
	 * @since 1.1.0
	 * @param string $value1 First value.
	 * @param string $value2 Second value.
	 * @return int
	 */
	private function compare_memory_values( $value1, $value2 ) {
		$bytes1 = $this->convert_to_bytes( $value1 );
		$bytes2 = $this->convert_to_bytes( $value2 );

		if ( $bytes1 > $bytes2 ) {
			return 1;
		} elseif ( $bytes1 < $bytes2 ) {
			return -1;
		}

		return 0;
	}

	/**
	 * Convert memory value to bytes
	 *
	 * @since 1.1.0
	 * @param string $value Memory value with suffix.
	 * @return int
	 */
	private function convert_to_bytes( $value ) {
		$value = trim( $value );
		$last = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = intval( $value );

		switch ( $last ) {
			case 'g':
				$value *= 1073741824;
				break;
			case 'm':
				$value *= 1048576;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}

	/**
	 * Get plugin analysis report
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_analysis_report() {
		$analysis = $this->analyze_all_plugins();
		$aggregated = $this->get_aggregated_requirements();

		$report = array(
			'total_plugins'          => count( $analysis ),
			'analyzed_at'            => get_option( 'as_php_checkup_last_analysis', 0 ),
			'aggregated_requirements' => $aggregated,
			'plugin_details'         => array(),
		);

		foreach ( $analysis as $plugin_file => $data ) {
			$report['plugin_details'][] = array(
				'name'         => $data['name'],
				'version'      => $data['version'],
				'requires_php' => $data['requires_php'],
				'has_requirements' => ! empty( $data['requirements'] ),
				'requirements' => $data['requirements'],
			);
		}

		// Sort plugins by name
		usort( $report['plugin_details'], function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return $report;
	}

	/**
	 * Check if specific extension is required by any plugin
	 *
	 * @since 1.1.0
	 * @param string $extension Extension name.
	 * @return array
	 */
	public function get_plugins_requiring_extension( $extension ) {
		$analysis = $this->analyze_all_plugins();
		$plugins_requiring = array();

		foreach ( $analysis as $plugin_file => $data ) {
			if ( isset( $data['requirements']['extensions'][ $extension ] ) && $data['requirements']['extensions'][ $extension ] ) {
				$plugins_requiring[] = array(
					'name'    => $data['name'],
					'version' => $data['version'],
					'file'    => $plugin_file,
				);
			}
		}

		return $plugins_requiring;
	}

	/**
	 * Get compatibility issues
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_compatibility_issues() {
		$issues = array();
		$current_php = PHP_VERSION;
		$analysis = $this->analyze_all_plugins();

		foreach ( $analysis as $plugin_file => $data ) {
			$plugin_issues = array();

			// Check PHP version
			if ( ! empty( $data['requires_php'] ) && version_compare( $current_php, $data['requires_php'], '<' ) ) {
				$plugin_issues[] = sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'Requires PHP %1$s or higher (current: %2$s)', 'as-php-checkup' ),
					$data['requires_php'],
					$current_php
				);
			}

			// Check memory limit
			if ( ! empty( $data['requirements']['memory_limit'] ) ) {
				$current_memory = ini_get( 'memory_limit' );
				if ( $this->compare_memory_values( $current_memory, $data['requirements']['memory_limit'] ) < 0 ) {
					$plugin_issues[] = sprintf(
						/* translators: 1: required memory limit, 2: current memory limit */
						__( 'Requires memory limit of %1$s (current: %2$s)', 'as-php-checkup' ),
						$data['requirements']['memory_limit'],
						$current_memory
					);
				}
			}

			// Check extensions
			if ( ! empty( $data['requirements']['extensions'] ) ) {
				foreach ( $data['requirements']['extensions'] as $ext => $required ) {
					if ( $required && ! extension_loaded( $ext ) ) {
						$plugin_issues[] = sprintf(
							/* translators: %s: extension name */
							__( 'Requires %s PHP extension', 'as-php-checkup' ),
							$ext
						);
					}
				}
			}

			if ( ! empty( $plugin_issues ) ) {
				$issues[] = array(
					'plugin' => $data['name'],
					'issues' => $plugin_issues,
				);
			}
		}

		return $issues;
	}

	/**
	 * Get plugin statistics
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_statistics() {
		$analysis = $this->analyze_all_plugins();
		
		$stats = array(
			'total_plugins'           => count( $analysis ),
			'plugins_with_requirements' => 0,
			'plugins_without_requirements' => 0,
			'most_common_extensions'  => array(),
			'highest_memory_requirement' => '0M',
			'average_memory_requirement' => '0M',
			'last_analysis'           => get_option( 'as_php_checkup_last_analysis', 0 ),
		);

		$memory_requirements = array();
		$extension_count = array();

		foreach ( $analysis as $data ) {
			if ( ! empty( $data['requirements'] ) ) {
				$stats['plugins_with_requirements']++;
				
				// Collect memory requirements
				if ( isset( $data['requirements']['memory_limit'] ) ) {
					$memory_requirements[] = $data['requirements']['memory_limit'];
				}
				
				// Count extensions
				if ( isset( $data['requirements']['extensions'] ) ) {
					foreach ( $data['requirements']['extensions'] as $ext => $required ) {
						if ( $required ) {
							if ( ! isset( $extension_count[ $ext ] ) ) {
								$extension_count[ $ext ] = 0;
							}
							$extension_count[ $ext ]++;
						}
					}
				}
			} else {
				$stats['plugins_without_requirements']++;
			}
		}

		// Calculate highest memory requirement
		if ( ! empty( $memory_requirements ) ) {
			$highest_bytes = 0;
			$total_bytes = 0;
			
			foreach ( $memory_requirements as $mem ) {
				$bytes = $this->convert_to_bytes( $mem );
				$highest_bytes = max( $highest_bytes, $bytes );
				$total_bytes += $bytes;
			}
			
			$stats['highest_memory_requirement'] = $this->format_bytes( $highest_bytes );
			$stats['average_memory_requirement'] = $this->format_bytes( $total_bytes / count( $memory_requirements ) );
		}

		// Sort extensions by count
		arsort( $extension_count );
		$stats['most_common_extensions'] = array_slice( $extension_count, 0, 10, true );

		return $stats;
	}

	/**
	 * Format bytes to human readable
	 *
	 * @since 1.1.0
	 * @param int $bytes Bytes value.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return round( $bytes / 1073741824, 1 ) . 'G';
		} elseif ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576 ) . 'M';
		} elseif ( $bytes >= 1024 ) {
			return round( $bytes / 1024 ) . 'K';
		}
		
		return $bytes . 'B';
	}

	/**
	 * Clear plugin analysis cache
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function clear_cache() {
		$this->cache_manager->clear_plugin_analysis_cache();
	}
}