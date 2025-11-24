<?php
/**
 * Cache Manager Class
 *
 * @package AS_PHP_Checkup
 * @since 1.2.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup_Cache_Manager class
 *
 * @since 1.2.1
 */
class AS_PHP_Checkup_Cache_Manager {

	/**
	 * Instance of this class
	 *
	 * @since 1.2.1
	 * @var AS_PHP_Checkup_Cache_Manager|null
	 */
	private static $instance = null;

	/**
	 * Cache key prefix
	 *
	 * @since 1.2.1
	 * @var string
	 */
	private $cache_prefix = 'as_php_checkup_';

	/**
	 * Cache expiration times
	 *
	 * @since 1.2.1
	 * @var array
	 */
	private $cache_times = array(
		'system_info'        => HOUR_IN_SECONDS,
		'plugin_analysis'    => DAY_IN_SECONDS,
		'check_results'      => HOUR_IN_SECONDS,
		'hosting_detection'  => WEEK_IN_SECONDS,
		'plugin_requirements'=> 12 * HOUR_IN_SECONDS,
	);

	/**
	 * Constructor
	 *
	 * @since 1.2.1
	 */
	private function __construct() {
		// Register cache clear on plugin update
		add_action( 'upgrader_process_complete', array( $this, 'clear_plugin_cache' ), 10, 2 );
		add_action( 'activated_plugin', array( $this, 'clear_plugin_analysis_cache' ) );
		add_action( 'deactivated_plugin', array( $this, 'clear_plugin_analysis_cache' ) );
		add_action( 'switch_theme', array( $this, 'clear_all_cache' ) );
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.2.1
	 * @return AS_PHP_Checkup_Cache_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get cached data
	 *
	 * @since 1.2.1
	 * @param string $key Cache key.
	 * @return mixed|false
	 */
	public function get( $key ) {
		$cache_key = $this->get_cache_key( $key );
		$data = get_transient( $cache_key );
		
		if ( false !== $data ) {
			// Log cache hit for debugging
			$this->log_cache_event( 'hit', $key );
			return $data;
		}
		
		// Log cache miss
		$this->log_cache_event( 'miss', $key );
		return false;
	}

	/**
	 * Set cached data
	 *
	 * @since 1.2.1
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $expiration Optional custom expiration.
	 * @return bool
	 */
	public function set( $key, $data, $expiration = null ) {
		$cache_key = $this->get_cache_key( $key );
		
		if ( null === $expiration ) {
			$expiration = isset( $this->cache_times[ $key ] ) ? 
			             $this->cache_times[ $key ] : HOUR_IN_SECONDS;
		}
		
		$result = set_transient( $cache_key, $data, $expiration );
		
		// Log cache set
		$this->log_cache_event( 'set', $key, array( 'expiration' => $expiration ) );
		
		return $result;
	}

	/**
	 * Delete cached data
	 *
	 * @since 1.2.1
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		$cache_key = $this->get_cache_key( $key );
		$result = delete_transient( $cache_key );
		
		// Log cache delete
		$this->log_cache_event( 'delete', $key );
		
		return $result;
	}

	/**
	 * Get or set cached data
	 *
	 * @since 1.2.1
	 * @param string   $key Cache key.
	 * @param callable $callback Callback to generate data.
	 * @param int      $expiration Optional custom expiration.
	 * @return mixed
	 */
	public function remember( $key, $callback, $expiration = null ) {
		$data = $this->get( $key );
		
		if ( false === $data ) {
			$data = call_user_func( $callback );
			$this->set( $key, $data, $expiration );
		}
		
		return $data;
	}

	/**
	 * Clear all plugin cache
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function clear_all_cache() {
		global $wpdb;
		
		// Delete all transients with our prefix
		$sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE %s 
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . $this->cache_prefix ) . '%'
		);
		
		$wpdb->query( $sql );
		
		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		
		// Log cache clear
		$this->log_cache_event( 'clear_all', 'all' );
	}

	/**
	 * Clear plugin analysis cache
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function clear_plugin_analysis_cache() {
		$this->delete( 'plugin_analysis' );
		$this->delete( 'plugin_requirements' );
		$this->delete( 'check_results' );
	}

	/**
	 * Clear cache on plugin update
	 *
	 * @since 1.2.1
	 * @param object $upgrader_object Upgrader object.
	 * @param array  $options Update options.
	 * @return void
	 */
	public function clear_plugin_cache( $upgrader_object, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			$this->clear_plugin_analysis_cache();
		}
	}

	/**
	 * Get formatted cache key
	 *
	 * @since 1.2.1
	 * @param string $key Raw cache key.
	 * @return string
	 */
	private function get_cache_key( $key ) {
		// Add site ID for multisite compatibility
		if ( is_multisite() ) {
			return $this->cache_prefix . get_current_blog_id() . '_' . $key;
		}
		
		return $this->cache_prefix . $key;
	}

	/**
	 * Log cache events for debugging
	 *
	 * @since 1.2.1
	 * @param string $event Event type.
	 * @param string $key Cache key.
	 * @param array  $data Additional data.
	 * @return void
	 */
	private function log_cache_event( $event, $key, $data = array() ) {
		if ( ! defined( 'AS_PHP_CHECKUP_DEBUG' ) || ! AS_PHP_CHECKUP_DEBUG ) {
			return;
		}
		
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'event'     => $event,
			'key'       => $key,
			'data'      => $data,
		);
		
		// Use WordPress debug log if available
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 
				sprintf( 
					'[AS PHP Checkup Cache] %s: %s - %s',
					$event,
					$key,
					wp_json_encode( $data )
				) 
			);
		}
		
		// Store in option for admin viewing (last 100 events)
		$cache_log = get_option( 'as_php_checkup_cache_log', array() );
		$cache_log[] = $log_entry;
		
		if ( count( $cache_log ) > 100 ) {
			$cache_log = array_slice( $cache_log, -100 );
		}
		
		update_option( 'as_php_checkup_cache_log', $cache_log, false );
	}

	/**
	 * Get cache statistics
	 *
	 * @since 1.2.1
	 * @return array
	 */
	public function get_cache_stats() {
		$cache_log = get_option( 'as_php_checkup_cache_log', array() );
		
		$stats = array(
			'total_events' => count( $cache_log ),
			'hits'         => 0,
			'misses'       => 0,
			'sets'         => 0,
			'deletes'      => 0,
			'hit_rate'     => 0,
		);
		
		foreach ( $cache_log as $entry ) {
			switch ( $entry['event'] ) {
				case 'hit':
					$stats['hits']++;
					break;
				case 'miss':
					$stats['misses']++;
					break;
				case 'set':
					$stats['sets']++;
					break;
				case 'delete':
					$stats['deletes']++;
					break;
			}
		}
		
		// Calculate hit rate
		$total_requests = $stats['hits'] + $stats['misses'];
		if ( $total_requests > 0 ) {
			$stats['hit_rate'] = round( ( $stats['hits'] / $total_requests ) * 100, 2 );
		}
		
		return $stats;
	}

	/**
	 * Preload critical cache
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function preload_cache() {
		// Only preload if classes are available
		if ( ! class_exists( 'AS_PHP_Checkup' ) || ! class_exists( 'AS_PHP_Checkup_Plugin_Analyzer' ) ) {
			// Classes not loaded yet, skip preloading
			return;
		}
		
		// Preload system info
		if ( false === $this->get( 'system_info' ) ) {
			$checkup = AS_PHP_Checkup::get_instance();
			$system_info = $checkup->get_system_info();
			$this->set( 'system_info', $system_info );
		}
		
		// Preload plugin analysis
		if ( false === $this->get( 'plugin_analysis' ) ) {
			$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
			$analysis = $analyzer->analyze_all_plugins();
			$this->set( 'plugin_analysis', $analysis );
		}
	}

	/**
	 * Get cache size
	 *
	 * @since 1.2.1
	 * @return int Size in bytes
	 */
	public function get_cache_size() {
		global $wpdb;
		
		$sql = $wpdb->prepare(
			"SELECT SUM(LENGTH(option_value)) as size 
			FROM {$wpdb->options} 
			WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%'
		);
		
		$size = $wpdb->get_var( $sql );
		
		return intval( $size );
	}
}