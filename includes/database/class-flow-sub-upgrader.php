<?php
/**
 * Flow Sub Database Upgrader
 * 
 * Handles automatic database schema upgrades.
 * Runs on admin_init to detect version changes and missing tables.
 *
 * @package Flow_Sub
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Flow_Sub_Upgrader
{
    /**
     * Check if upgrade is needed and run if necessary
     * 
     * Lightweight check that runs on every admin page load.
     * Only triggers installer if version changed or tables missing.
     *
     * @return void
     */
    public static function maybe_upgrade()
    {
        // Only run in admin context
        if (!is_admin()) {
            return;
        }

        // Check if upgrade is needed
        if (self::needs_upgrade()) {
            self::run_upgrade();
        }
    }

    /**
     * Determine if database upgrade is needed
     * 
     * Compares stored DB version with current constant.
     * Also checks if all required tables exist.
     *
     * @return bool True if upgrade needed
     */
    public static function needs_upgrade()
    {
        // Get stored database version
        $stored_version = Flow_Sub_Installer::get_db_version();
        $current_version = FLOW_SUB_DB_VERSION;

        // Check if version has changed
        if (version_compare($stored_version, $current_version, '<')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Flow Sub Upgrader: Version upgrade needed. Stored: {$stored_version}, Current: {$current_version}");
            }
            return true;
        }

        // Check if all tables exist (in case of manual deletion)
        if (!Flow_Sub_Installer::verify_tables()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Flow Sub Upgrader: Missing tables detected');
            }
            return true;
        }

        return false;
    }

    /**
     * Run the database upgrade
     * 
     * Executes the installer to create/update tables.
     * Logs the upgrade process for debugging.
     *
     * @return void
     */
    public static function run_upgrade()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Flow Sub Upgrader: Starting database upgrade');
        }

        // Run the installer
        Flow_Sub_Installer::install();

        // Log completion
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Flow Sub Upgrader: Database upgrade complete');
        }

        // Fire action hook for other plugins/themes
        do_action('flow_sub_database_upgraded', FLOW_SUB_DB_VERSION);
    }

    /**
     * Force upgrade (for manual/admin use)
     * 
     * Bypasses version check and forces reinstall of all tables.
     * Useful for development or fixing corrupted tables.
     *
     * @return void
     */
    public static function force_upgrade()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Flow Sub Upgrader: Force upgrade initiated');
        }

        Flow_Sub_Installer::install();
    }

    /**
     * Get upgrade status information
     * 
     * Returns diagnostic information about database state.
     * Useful for admin dashboard or debugging.
     *
     * @return array Status information
     */
    public static function get_upgrade_status()
    {
        $stored_version = Flow_Sub_Installer::get_db_version();
        $current_version = FLOW_SUB_DB_VERSION;
        $tables_exist = Flow_Sub_Installer::verify_tables();
        $needs_upgrade = self::needs_upgrade();

        return [
            'stored_version' => $stored_version,
            'current_version' => $current_version,
            'tables_exist' => $tables_exist,
            'needs_upgrade' => $needs_upgrade,
            'is_up_to_date' => !$needs_upgrade && $tables_exist,
        ];
    }

    /**
     * Verify database integrity
     * 
     * Checks that all tables exist and have correct structure.
     * Returns array of issues found.
     *
     * @return array Array of issues (empty if all OK)
     */
    public static function verify_integrity()
    {
        global $wpdb;
        $issues = [];

        // Check version
        $stored_version = Flow_Sub_Installer::get_db_version();
        if ($stored_version !== FLOW_SUB_DB_VERSION) {
            $issues[] = "Version mismatch: stored={$stored_version}, expected=" . FLOW_SUB_DB_VERSION;
        }

        // Check each table
        $schemas = Flow_Sub_Installer::get_schema_sql();
        foreach ($schemas as $table_name => $schema) {
            $full_table_name = $wpdb->prefix . $table_name;

            if (!Flow_Sub_Installer::table_exists($table_name)) {
                $issues[] = "Missing table: {$full_table_name}";
            } else {
                // Table exists, could add column verification here
                // For now, just verify it exists
            }
        }

        return $issues;
    }
}
