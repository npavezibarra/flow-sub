<?php
/**
 * Flow Sub Database Installer
 * 
 * Handles database schema creation and management using dbDelta.
 * Follows modern WordPress plugin architecture with centralized schema definitions.
 *
 * @package Flow_Sub
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Flow_Sub_Installer
{
    /**
     * Install all database tables
     * 
     * Creates all required tables using dbDelta.
     * Safe to run multiple times (idempotent).
     *
     * @return void
     */
    public static function install()
    {
        global $wpdb;

        // Require dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Get all table schemas
        $schemas = self::get_schema_sql();

        // Execute dbDelta for each table
        foreach ($schemas as $table_name => $sql) {
            $result = dbDelta($sql);

            // Log the result for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Flow Sub Installer: Creating table {$table_name}");
                error_log(print_r($result, true));
            }
        }

        // Update database version in options
        update_option('flow_sub_db_version', FLOW_SUB_DB_VERSION);

        // Log successful installation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Flow Sub Installer: Database installation complete. Version: ' . FLOW_SUB_DB_VERSION);
        }
    }

    /**
     * Get all database schema SQL statements
     * 
     * Centralized location for all table definitions.
     * Returns an associative array of table_name => CREATE TABLE SQL.
     *
     * @return array Array of table schemas
     */
    public static function get_schema_sql()
    {
        $schemas = [];

        // Add reactions table schema
        $schemas['flow_post_reactions'] = self::get_reactions_table_schema();

        // Future tables can be added here
        // $schemas['another_table'] = self::get_another_table_schema();

        return $schemas;
    }

    /**
     * Get reactions table schema
     * 
     * Defines the structure for wp_flow_post_reactions table.
     * Tracks user reactions (like/dislike) with full history.
     *
     * @return string CREATE TABLE SQL statement
     */
    private static function get_reactions_table_schema()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'flow_post_reactions';
        $charset_collate = $wpdb->get_charset_collate();

        // IMPORTANT: dbDelta is very sensitive to formatting
        // - Must have two spaces after PRIMARY KEY
        // - Must have spaces around parentheses
        // - Must not have trailing commas
        // - Key definitions must be on separate lines
        $sql = "CREATE TABLE $table_name (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  flow_post_id bigint(20) UNSIGNED NOT NULL,
  user_id bigint(20) UNSIGNED NOT NULL,
  reaction tinyint(1) NOT NULL COMMENT '1=like, 0=dislike',
  is_active tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=current, 0=historical',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_post_user (flow_post_id, user_id),
  KEY idx_user (user_id),
  KEY idx_post_active (flow_post_id, is_active),
  KEY idx_created (created_at)
) $charset_collate;";

        return $sql;
    }

    /**
     * Get current database version from options
     *
     * @return string Database version or '0' if not set
     */
    public static function get_db_version()
    {
        return get_option('flow_sub_db_version', '0');
    }

    /**
     * Check if a specific table exists
     *
     * @param string $table_name Table name without prefix
     * @return bool True if table exists
     */
    public static function table_exists($table_name)
    {
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table_name;
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $full_table_name);
        return $wpdb->get_var($query) === $full_table_name;
    }

    /**
     * Verify all required tables exist
     *
     * @return bool True if all tables exist
     */
    public static function verify_tables()
    {
        $required_tables = array_keys(self::get_schema_sql());

        foreach ($required_tables as $table_name) {
            if (!self::table_exists($table_name)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Flow Sub Installer: Missing table - {$table_name}");
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Drop all plugin tables (use with caution)
     * 
     * Only for development/testing or complete uninstall.
     *
     * @return void
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = array_keys(self::get_schema_sql());

        foreach ($tables as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $wpdb->query("DROP TABLE IF EXISTS $full_table_name");

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Flow Sub Installer: Dropped table {$full_table_name}");
            }
        }

        // Remove database version option
        delete_option('flow_sub_db_version');
    }
}
