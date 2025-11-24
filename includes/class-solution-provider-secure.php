<?php
/**
 * Secure Solution Provider Class (Security Patch)
 *
 * @package AS_PHP_Checkup
 * @since 1.2.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_PHP_Checkup_Solution_Provider_Secure class
 *
 * @since 1.2.1
 */
trait AS_PHP_Checkup_Solution_Provider_Secure {

	/**
	 * Allowed solution types whitelist
	 *
	 * @since 1.2.1
	 * @var array
	 */
	private $allowed_solution_types = array(
		'php_ini',
		'user_ini',
		'htaccess',
		'wp_config',
		'nginx',
		'hosting_wpengine',
		'hosting_kinsta',
		'hosting_siteground',
		'hosting_cloudways',
		'hosting_cpanel',
		'hosting_plesk',
		'hosting_gridpane',
		'hosting_runcloud',
		'hosting_generic'
	);

	/**
	 * Allowed config types for download
	 *
	 * @since 1.2.1
	 * @var array
	 */
	private $allowed_config_types = array(
		'php_ini',
		'user_ini',
		'htaccess',
		'nginx',
		'wp_config'
	);

	/**
	 * Validate solution type against whitelist
	 *
	 * @since 1.2.1
	 * @param string $solution_type Solution type to validate.
	 * @return bool
	 */
	protected function validate_solution_type( $solution_type ) {
		return in_array( $solution_type, $this->allowed_solution_types, true );
	}

	/**
	 * Validate config type against whitelist
	 *
	 * @since 1.2.1
	 * @param string $config_type Config type to validate.
	 * @return bool
	 */
	protected function validate_config_type( $config_type ) {
		return in_array( $config_type, $this->allowed_config_types, true );
	}

	/**
	 * Validate file path for security
	 *
	 * @since 1.2.1
	 * @param string $file_path File path to validate.
	 * @return bool|WP_Error
	 */
	protected function validate_file_path( $file_path ) {
		// Normalize path
		$file_path = wp_normalize_path( $file_path );
		$abspath = wp_normalize_path( ABSPATH );
		
		// Check for path traversal attempts
		if ( strpos( $file_path, '..' ) !== false ) {
			return new WP_Error(
				'path_traversal_detected',
				__( 'Path traversal attempt detected', 'as-php-checkup' )
			);
		}
		
		// Ensure path is within ABSPATH
		if ( strpos( $file_path, $abspath ) !== 0 ) {
			return new WP_Error(
				'invalid_path',
				__( 'File path must be within WordPress directory', 'as-php-checkup' )
			);
		}
		
		// Check for sensitive files
		$sensitive_files = array(
			'wp-config.php',
			'.htaccess',
			'php.ini',
			'.user.ini',
			'nginx.conf'
		);
		
		$filename = basename( $file_path );
		if ( ! in_array( $filename, $sensitive_files, true ) ) {
			// If not a sensitive file, ensure it has .backup extension
			if ( strpos( $filename, '.backup' ) === false ) {
				return new WP_Error(
					'invalid_backup_file',
					__( 'Invalid backup file name', 'as-php-checkup' )
				);
			}
		}
		
		return true;
	}

	/**
	 * Write file atomically with proper locking
	 *
	 * @since 1.2.1
	 * @param string $file_path Target file path.
	 * @param string $content File content.
	 * @param bool   $create_backup Whether to create backup.
	 * @return bool|WP_Error
	 */
	protected function write_file_atomic( $file_path, $content, $create_backup = true ) {
		// Validate path first
		$validation = $this->validate_file_path( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		
		// Create backup if requested and file exists
		if ( $create_backup && file_exists( $file_path ) ) {
			$backup_result = $this->create_backup_file( $file_path );
			if ( is_wp_error( $backup_result ) ) {
				return $backup_result;
			}
		}
		
		// Generate temporary file
		$temp_file = $file_path . '.tmp.' . uniqid( '', true );
		
		// Write to temporary file with exclusive lock
		$bytes_written = file_put_contents( $temp_file, $content, LOCK_EX );
		
		if ( false === $bytes_written ) {
			@unlink( $temp_file );
			return new WP_Error(
				'write_failed',
				__( 'Failed to write configuration file', 'as-php-checkup' )
			);
		}
		
		// Set proper permissions (0644 for files)
		@chmod( $temp_file, 0644 );
		
		// Atomically move temp file to target
		if ( ! @rename( $temp_file, $file_path ) ) {
			// Fallback to copy + unlink
			if ( @copy( $temp_file, $file_path ) ) {
				@unlink( $temp_file );
			} else {
				@unlink( $temp_file );
				return new WP_Error(
					'move_failed',
					__( 'Failed to move configuration file', 'as-php-checkup' )
				);
			}
		}
		
		// Clear opcode cache for the file
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file_path, true );
		}
		
		return true;
	}

	/**
	 * Create backup file with cleanup
	 *
	 * @since 1.2.1
	 * @param string $file_path Original file path.
	 * @return string|WP_Error Backup file path or error.
	 */
	protected function create_backup_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'file_not_exists',
				__( 'Original file does not exist', 'as-php-checkup' )
			);
		}
		
		$backup_path = $file_path . '.backup-' . date( 'Y-m-d-H-i-s' );
		
		// Validate backup path
		$validation = $this->validate_file_path( $backup_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		
		// Copy file
		if ( ! @copy( $file_path, $backup_path ) ) {
			return new WP_Error(
				'backup_failed',
				__( 'Failed to create backup file', 'as-php-checkup' )
			);
		}
		
		// Set read-only permissions for backup
		@chmod( $backup_path, 0444 );
		
		// Schedule cleanup (keep backups for 7 days)
		$this->schedule_backup_cleanup( dirname( $file_path ) );
		
		// Log backup creation
		$this->log_operation( 'backup_created', array(
			'original' => $file_path,
			'backup'   => $backup_path,
		) );
		
		return $backup_path;
	}

	/**
	 * Schedule backup cleanup
	 *
	 * @since 1.2.1
	 * @param string $directory Directory to clean.
	 * @return void
	 */
	protected function schedule_backup_cleanup( $directory ) {
		if ( ! wp_next_scheduled( 'as_php_checkup_cleanup_backups' ) ) {
			wp_schedule_event( time(), 'daily', 'as_php_checkup_cleanup_backups' );
		}
		
		// Store directory for cleanup
		$cleanup_dirs = get_option( 'as_php_checkup_cleanup_dirs', array() );
		if ( ! in_array( $directory, $cleanup_dirs, true ) ) {
			$cleanup_dirs[] = $directory;
			update_option( 'as_php_checkup_cleanup_dirs', $cleanup_dirs );
		}
	}

	/**
	 * Clean old backup files
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function cleanup_old_backups() {
		$cleanup_dirs = get_option( 'as_php_checkup_cleanup_dirs', array() );
		$max_age = 7 * DAY_IN_SECONDS; // 7 days
		
		foreach ( $cleanup_dirs as $directory ) {
			if ( ! is_dir( $directory ) ) {
				continue;
			}
			
			$files = glob( $directory . '/*.backup-*' );
			if ( ! $files ) {
				continue;
			}
			
			foreach ( $files as $file ) {
				if ( ( time() - filemtime( $file ) ) > $max_age ) {
					@unlink( $file );
					$this->log_operation( 'backup_deleted', array(
						'file' => $file,
						'age'  => human_time_diff( filemtime( $file ), time() ),
					) );
				}
			}
		}
	}

	/**
	 * Generate action-specific nonce
	 *
	 * @since 1.2.1
	 * @param string $action Action name.
	 * @return string
	 */
	protected function generate_action_nonce( $action ) {
		return wp_create_nonce( 'as_php_checkup_' . $action );
	}

	/**
	 * Verify action-specific nonce
	 *
	 * @since 1.2.1
	 * @param string $nonce Nonce to verify.
	 * @param string $action Action name.
	 * @return bool
	 */
	protected function verify_action_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, 'as_php_checkup_' . $action );
	}

	/**
	 * Log operation for audit trail
	 *
	 * @since 1.2.1
	 * @param string $operation Operation name.
	 * @param array  $data Operation data.
	 * @return void
	 */
	protected function log_operation( $operation, $data = array() ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'operation' => $operation,
			'data'      => $data,
			'ip'        => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);
		
		// Get existing logs
		$logs = get_option( 'as_php_checkup_audit_log', array() );
		
		// Add new entry
		$logs[] = $log_entry;
		
		// Keep only last 1000 entries
		if ( count( $logs ) > 1000 ) {
			$logs = array_slice( $logs, -1000 );
		}
		
		// Save logs
		update_option( 'as_php_checkup_audit_log', $logs, false );
		
		// Trigger action for external logging
		do_action( 'as_php_checkup_log_operation', $operation, $data );
	}

	/**
	 * Validate generated configuration
	 *
	 * @since 1.2.1
	 * @param string $content Configuration content.
	 * @param string $type Configuration type.
	 * @return bool|WP_Error
	 */
	protected function validate_configuration( $content, $type ) {
		if ( empty( $content ) ) {
			return new WP_Error(
				'empty_config',
				__( 'Configuration content is empty', 'as-php-checkup' )
			);
		}
		
		switch ( $type ) {
			case 'php_ini':
			case 'user_ini':
				return $this->validate_ini_syntax( $content );
				
			case 'htaccess':
				return $this->validate_htaccess_syntax( $content );
				
			case 'nginx':
				return $this->validate_nginx_syntax( $content );
				
			case 'wp_config':
				return $this->validate_php_syntax( $content );
				
			default:
				return true;
		}
	}

	/**
	 * Validate INI syntax
	 *
	 * @since 1.2.1
	 * @param string $content INI content.
	 * @return bool|WP_Error
	 */
	private function validate_ini_syntax( $content ) {
		// Try to parse INI
		$parsed = @parse_ini_string( $content );
		
		if ( false === $parsed ) {
			return new WP_Error(
				'invalid_ini_syntax',
				__( 'Invalid INI file syntax', 'as-php-checkup' )
			);
		}
		
		// Check for dangerous directives
		$dangerous = array(
			'auto_prepend_file',
			'auto_append_file',
			'open_basedir',
			'disable_functions',
			'disable_classes',
		);
		
		foreach ( $dangerous as $directive ) {
			if ( isset( $parsed[ $directive ] ) ) {
				return new WP_Error(
					'dangerous_directive',
					sprintf(
						/* translators: %s: directive name */
						__( 'Dangerous directive detected: %s', 'as-php-checkup' ),
						$directive
					)
				);
			}
		}
		
		return true;
	}

	/**
	 * Validate .htaccess syntax
	 *
	 * @since 1.2.1
	 * @param string $content Htaccess content.
	 * @return bool|WP_Error
	 */
	private function validate_htaccess_syntax( $content ) {
		// Check for common syntax errors
		$lines = explode( "\n", $content );
		$if_module_open = 0;
		
		foreach ( $lines as $line_num => $line ) {
			$line = trim( $line );
			
			// Skip comments and empty lines
			if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
				continue;
			}
			
			// Check for IfModule balance
			if ( strpos( $line, '<IfModule' ) === 0 ) {
				$if_module_open++;
			} elseif ( strpos( $line, '</IfModule>' ) === 0 ) {
				$if_module_open--;
				if ( $if_module_open < 0 ) {
					return new WP_Error(
						'htaccess_syntax_error',
						sprintf(
							/* translators: %d: line number */
							__( 'Unmatched </IfModule> at line %d', 'as-php-checkup' ),
							$line_num + 1
						)
					);
				}
			}
		}
		
		if ( $if_module_open !== 0 ) {
			return new WP_Error(
				'htaccess_syntax_error',
				__( 'Unclosed <IfModule> directive', 'as-php-checkup' )
			);
		}
		
		return true;
	}

	/**
	 * Validate NGINX syntax
	 *
	 * @since 1.2.1
	 * @param string $content NGINX content.
	 * @return bool|WP_Error
	 */
	private function validate_nginx_syntax( $content ) {
		// Basic syntax validation
		$open_braces = substr_count( $content, '{' );
		$close_braces = substr_count( $content, '}' );
		
		if ( $open_braces !== $close_braces ) {
			return new WP_Error(
				'nginx_syntax_error',
				__( 'Unmatched braces in NGINX configuration', 'as-php-checkup' )
			);
		}
		
		// Check for required directives
		if ( strpos( $content, 'location' ) === false ) {
			return new WP_Error(
				'nginx_missing_location',
				__( 'Missing location directive in NGINX configuration', 'as-php-checkup' )
			);
		}
		
		return true;
	}

	/**
	 * Validate PHP syntax
	 *
	 * @since 1.2.1
	 * @param string $content PHP content.
	 * @return bool|WP_Error
	 */
	private function validate_php_syntax( $content ) {
		// Check for PHP opening tag if expected
		if ( strpos( $content, '<?php' ) === false && strpos( $content, 'define(' ) !== false ) {
			return new WP_Error(
				'php_syntax_error',
				__( 'Missing PHP opening tag', 'as-php-checkup' )
			);
		}
		
		// Use tokenizer if available
		if ( function_exists( 'token_get_all' ) ) {
			$tokens = @token_get_all( $content );
			if ( false === $tokens ) {
				return new WP_Error(
					'php_syntax_error',
					__( 'PHP syntax error detected', 'as-php-checkup' )
				);
			}
		}
		
		return true;
	}

	/**
	 * Get audit logs
	 *
	 * @since 1.2.1
	 * @param int $limit Number of logs to retrieve.
	 * @return array
	 */
	public function get_audit_logs( $limit = 100 ) {
		$logs = get_option( 'as_php_checkup_audit_log', array() );
		
		// Sort by timestamp (newest first)
		usort( $logs, function( $a, $b ) {
			return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
		} );
		
		// Return limited results
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear audit logs
	 *
	 * @since 1.2.1
	 * @return bool
	 */
	public function clear_audit_logs() {
		$this->log_operation( 'audit_logs_cleared' );
		return update_option( 'as_php_checkup_audit_log', array() );
	}

	/**
	 * Export audit logs
	 *
	 * @since 1.2.1
	 * @param string $format Export format (csv, json).
	 * @return string
	 */
	public function export_audit_logs( $format = 'csv' ) {
		$logs = $this->get_audit_logs( 9999 );
		
		if ( 'json' === $format ) {
			return wp_json_encode( $logs, JSON_PRETTY_PRINT );
		}
		
		// CSV format
		$csv = array();
		$csv[] = 'Timestamp,User ID,Operation,IP,Data';
		
		foreach ( $logs as $log ) {
			$csv[] = sprintf(
				'"%s",%d,"%s","%s","%s"',
				$log['timestamp'],
				$log['user_id'],
				$log['operation'],
				$log['ip'],
				wp_json_encode( $log['data'] )
			);
		}
		
		return implode( "\n", $csv );
	}

	/**
	 * Check if backup cleanup is needed
	 *
	 * @since 1.2.1
	 * @return bool
	 */
	public function needs_backup_cleanup() {
		$last_cleanup = get_option( 'as_php_checkup_last_cleanup', 0 );
		return ( time() - $last_cleanup ) > DAY_IN_SECONDS;
	}

	/**
	 * Run backup cleanup if needed
	 *
	 * @since 1.2.1
	 * @return void
	 */
	public function maybe_cleanup_backups() {
		if ( $this->needs_backup_cleanup() ) {
			$this->cleanup_old_backups();
			update_option( 'as_php_checkup_last_cleanup', time() );
		}
	}
}