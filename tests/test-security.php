<?php
/**
 * Security Tests
 *
 * @package AS_PHP_Checkup
 * @subpackage Tests
 * @since 1.4.0
 */

/**
 * Security test class
 *
 * @since 1.4.0
 */
class AS_PHP_Checkup_Security_Tests extends WP_UnitTestCase {

	/**
	 * Test CSV injection protection
	 *
	 * @since 1.4.0
	 */
	public function test_csv_injection_protection() {
		$controller = AS_PHP_Checkup_REST_Controller::get_instance();
		$reflection = new ReflectionClass( $controller );
		$method = $reflection->getMethod( 'sanitize_csv_field' );
		$method->setAccessible( true );

		// Test formula injection attempts
		$dangerous_inputs = array(
			'=1+1'                => "'=1+1",
			'+1+1'                => "'+1+1",
			'-1+1'                => "'-1+1",
			'@SUM(A1:A10)'        => "'@SUM(A1:A10)",
			"\t=cmd|'/c calc'!A1" => "'\t=cmd|'/c calc'!A1",
		);

		foreach ( $dangerous_inputs as $input => $expected ) {
			$result = $method->invoke( $controller, $input );
			$this->assertStringStartsWith( "'", $result, "Formula injection not prevented for: {$input}" );
		}

		// Test normal inputs
		$this->assertEquals( 'normal text', $method->invoke( $controller, 'normal text' ) );
		$this->assertEquals( '123', $method->invoke( $controller, '123' ) );
	}

	/**
	 * Test file size limits
	 *
	 * @since 1.4.0
	 */
	public function test_file_size_limits() {
		$analyzer = AS_PHP_Checkup_Plugin_Analyzer::get_instance();
		$reflection = new ReflectionClass( $analyzer );
		$method = $reflection->getMethod( 'safe_read_file' );
		$method->setAccessible( true );

		// Test with non-existent file
		$result = $method->invoke( $analyzer, '/path/to/nonexistent/file.txt', 1024 );
		$this->assertFalse( $result, 'Non-existent file should return false' );

		// Test constants are defined
		$this->assertTrue( defined( 'AS_PHP_CHECKUP_MAX_README_SIZE' ), 'README size constant not defined' );
		$this->assertTrue( defined( 'AS_PHP_CHECKUP_MAX_PLUGIN_FILE_SIZE' ), 'Plugin file size constant not defined' );
		$this->assertTrue( defined( 'AS_PHP_CHECKUP_MAX_COMPOSER_SIZE' ), 'Composer file size constant not defined' );

		// Verify reasonable limits
		$this->assertEquals( 512 * 1024, AS_PHP_CHECKUP_MAX_README_SIZE );
		$this->assertEquals( 1024 * 1024, AS_PHP_CHECKUP_MAX_PLUGIN_FILE_SIZE );
		$this->assertEquals( 100 * 1024, AS_PHP_CHECKUP_MAX_COMPOSER_SIZE );
	}

	/**
	 * Test bounds checking in convert_to_bytes
	 *
	 * @since 1.4.0
	 */
	public function test_convert_to_bytes_bounds() {
		$checkup = AS_PHP_Checkup::get_instance();
		$reflection = new ReflectionClass( $checkup );
		$method = $reflection->getMethod( 'convert_to_bytes' );
		$method->setAccessible( true );

		// Test empty string
		$this->assertEquals( 0, $method->invoke( $checkup, '' ) );

		// Test null
		$this->assertEquals( 0, $method->invoke( $checkup, null ) );

		// Test negative values
		$this->assertEquals( 0, $method->invoke( $checkup, '-100M' ) );

		// Test valid conversions
		$this->assertEquals( 1024, $method->invoke( $checkup, '1K' ) );
		$this->assertEquals( 1048576, $method->invoke( $checkup, '1M' ) );
		$this->assertEquals( 1073741824, $method->invoke( $checkup, '1G' ) );

		// Test numeric values
		$this->assertEquals( 512, $method->invoke( $checkup, 512 ) );
		$this->assertEquals( 512, $method->invoke( $checkup, '512' ) );
	}

	/**
	 * Test REST API validation
	 *
	 * @since 1.4.0
	 */
	public function test_rest_api_validation() {
		$controller = AS_PHP_Checkup_REST_Controller::get_instance();

		// Test export format validation
		$this->assertTrue( $controller->validate_export_format( 'json', new WP_REST_Request(), 'format' ) );
		$this->assertTrue( $controller->validate_export_format( 'csv', new WP_REST_Request(), 'format' ) );

		// Test invalid format
		$result = $controller->validate_export_format( 'xml', new WP_REST_Request(), 'format' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_format', $result->get_error_code() );
	}

	/**
	 * Test cache stampede protection
	 *
	 * @since 1.4.0
	 */
	public function test_cache_stampede_protection() {
		$checkup = AS_PHP_Checkup::get_instance();
		$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();

		// Clear cache first
		$cache_manager->delete( 'check_results' );
		$cache_manager->delete( 'check_results_lock' );

		// First call should set lock and compute
		$results1 = $checkup->get_check_results();
		$this->assertIsArray( $results1 );

		// Lock should exist
		$lock = $cache_manager->get( 'check_results_lock' );
		$this->assertNotFalse( $lock );

		// Results should be cached
		$cached = $cache_manager->get( 'check_results' );
		$this->assertNotFalse( $cached );
	}

	/**
	 * Test SQL injection protection in cache manager
	 *
	 * @since 1.4.0
	 */
	public function test_sql_injection_protection() {
		global $wpdb;
		$cache_manager = AS_PHP_Checkup_Cache_Manager::get_instance();

		// This should not cause SQL errors
		$cache_manager->clear_all_cache();

		// Verify no SQL errors occurred
		$this->assertEmpty( $wpdb->last_error );
	}

	/**
	 * Test constants are defined
	 *
	 * @since 1.4.0
	 */
	public function test_constants_defined() {
		$required_constants = array(
			'AS_PHP_CHECKUP_VERSION',
			'AS_PHP_CHECKUP_CACHE_CHECK_RESULTS',
			'AS_PHP_CHECKUP_CACHE_SYSTEM_INFO',
			'AS_PHP_CHECKUP_CACHE_PLUGIN_ANALYSIS',
			'AS_PHP_CHECKUP_MAX_README_SIZE',
			'AS_PHP_CHECKUP_MAX_PLUGIN_FILE_SIZE',
			'AS_PHP_CHECKUP_MAX_AUDIT_LOG_ENTRIES',
			'AS_PHP_CHECKUP_BACKUP_RETENTION_DAYS',
		);

		foreach ( $required_constants as $constant ) {
			$this->assertTrue( defined( $constant ), "Constant {$constant} is not defined" );
		}

		// Verify version is 1.4.0
		$this->assertEquals( '1.4.0', AS_PHP_CHECKUP_VERSION );
	}
}
