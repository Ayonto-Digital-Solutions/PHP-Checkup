<?php
/**
 * Secure Solution Provider Trait (Deprecated - Use AS_PHP_Checkup_Security instead)
 *
 * @package AS_PHP_Checkup
 * @since 1.2.1
 * @version 1.4.0 - Deprecated in favor of trait-security.php
 * @deprecated 1.4.0 Use AS_PHP_Checkup_Security trait instead
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This file is deprecated as of version 1.4.0
 * It was a duplicate of trait-security.php with minor differences
 *
 * Use AS_PHP_Checkup_Security trait from trait-security.php instead
 * This file is kept for backwards compatibility only
 *
 * @deprecated 1.4.0
 */

// Load the proper security trait
require_once AS_PHP_CHECKUP_PLUGIN_DIR . 'includes/trait-security.php';

/**
 * Alias for backwards compatibility
 *
 * @deprecated 1.4.0
 */
trait AS_PHP_Checkup_Solution_Provider_Secure {
	use AS_PHP_Checkup_Security;
}
