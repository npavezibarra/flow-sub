<?php
/**
 * Flow Sub Reactions
 * 
 * Handles all reaction business logic (CRUD operations).
 * Manages user reactions (like/dislike) and cached counts.
 *
 * @package Flow_Sub
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Flow_Sub_Reactions
{
    /**
     * Toggle a user's reaction on a post
     * 
     * Logic:
     * - If user has same reaction → Remove it (toggle off)
     * - If user has different reaction → Switch to new reaction
     * - If user has no reaction → Add new reaction
     *
     * @param int $post_id Flow post ID
     * @param int $user_id User ID
     * @param int $reaction Reaction type (1=like, 0=dislike)
     * @return array|WP_Error Result with counts and user reaction, or error
     */
    public static function toggle_reaction($post_id, $user_id, $reaction)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        // Validate inputs
        $post_id = absint($post_id);
        $user_id = absint($user_id);
        $reaction = intval($reaction);

        if (!in_array($reaction, [0, 1])) {
            return new WP_Error('invalid_reaction', 'Reaction must be 0 or 1');
        }

        // Get current user reaction
        $current_reaction = self::get_user_reaction($post_id, $user_id);

        // Determine action
        if ($current_reaction === $reaction) {
            // Same reaction → Remove it
            self::remove_reaction($post_id, $user_id);
            $new_reaction = null;
        } elseif ($current_reaction !== null) {
            // Different reaction → Switch
            self::update_reaction($post_id, $user_id, $reaction);
            $new_reaction = $reaction;
        } else {
            // No reaction → Add new
            self::add_reaction($post_id, $user_id, $reaction);
            $new_reaction = $reaction;
        }

        // Update cached counts
        self::update_cached_counts($post_id);

        // Get updated counts
        $counts = self::get_reaction_counts($post_id);

        return [
            'like_count' => $counts['likes'],
            'dislike_count' => $counts['dislikes'],
            'user_reaction' => $new_reaction,
        ];
    }

    /**
     * Add a new reaction
     *
     * @param int $post_id Flow post ID
     * @param int $user_id User ID
     * @param int $reaction Reaction type (1=like, 0=dislike)
     * @return int|false Insert ID or false on failure
     */
    private static function add_reaction($post_id, $user_id, $reaction)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $result = $wpdb->insert(
            $table_name,
            [
                'flow_post_id' => $post_id,
                'user_id' => $user_id,
                'reaction' => $reaction,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s']
        );

        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Flow Reactions: Failed to add reaction. Error: " . $wpdb->last_error);
            }
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update existing reaction to a new type
     *
     * @param int $post_id Flow post ID
     * @param int $user_id User ID
     * @param int $new_reaction New reaction type (1=like, 0=dislike)
     * @return bool Success
     */
    private static function update_reaction($post_id, $user_id, $new_reaction)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        // Deactivate old reaction
        $wpdb->update(
            $table_name,
            ['is_active' => 0],
            [
                'flow_post_id' => $post_id,
                'user_id' => $user_id,
                'is_active' => 1,
            ],
            ['%d'],
            ['%d', '%d', '%d']
        );

        // Insert new reaction
        return self::add_reaction($post_id, $user_id, $new_reaction);
    }

    /**
     * Remove user's reaction (set to inactive)
     *
     * @param int $post_id Flow post ID
     * @param int $user_id User ID
     * @return bool Success
     */
    private static function remove_reaction($post_id, $user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $result = $wpdb->update(
            $table_name,
            ['is_active' => 0],
            [
                'flow_post_id' => $post_id,
                'user_id' => $user_id,
                'is_active' => 1,
            ],
            ['%d'],
            ['%d', '%d', '%d']
        );

        return $result !== false;
    }

    /**
     * Get user's current reaction for a post
     *
     * @param int $post_id Flow post ID
     * @param int $user_id User ID
     * @return int|null Reaction (1=like, 0=dislike) or null if no reaction
     */
    public static function get_user_reaction($post_id, $user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $reaction = $wpdb->get_var($wpdb->prepare(
            "SELECT reaction FROM $table_name 
             WHERE flow_post_id = %d 
             AND user_id = %d 
             AND is_active = 1 
             LIMIT 1",
            $post_id,
            $user_id
        ));

        return $reaction !== null ? intval($reaction) : null;
    }

    /**
     * Get reaction counts for a post
     * 
     * First checks cache (post meta), then queries database if needed.
     *
     * @param int $post_id Flow post ID
     * @param bool $force_refresh Force refresh from database
     * @return array Array with 'likes' and 'dislikes' counts
     */
    public static function get_reaction_counts($post_id, $force_refresh = false)
    {
        // Try to get from cache first
        if (!$force_refresh) {
            $like_count = get_post_meta($post_id, 'flow_post_like_count', true);
            $dislike_count = get_post_meta($post_id, 'flow_post_dislike_count', true);

            if ($like_count !== '' && $dislike_count !== '') {
                return [
                    'likes' => intval($like_count),
                    'dislikes' => intval($dislike_count),
                ];
            }
        }

        // Cache miss or force refresh → Query database
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN reaction = 1 THEN 1 ELSE 0 END) as likes,
                SUM(CASE WHEN reaction = 0 THEN 1 ELSE 0 END) as dislikes
             FROM $table_name
             WHERE flow_post_id = %d AND is_active = 1",
            $post_id
        ), ARRAY_A);

        $likes = isset($counts['likes']) ? intval($counts['likes']) : 0;
        $dislikes = isset($counts['dislikes']) ? intval($counts['dislikes']) : 0;

        // Update cache
        update_post_meta($post_id, 'flow_post_like_count', $likes);
        update_post_meta($post_id, 'flow_post_dislike_count', $dislikes);

        return [
            'likes' => $likes,
            'dislikes' => $dislikes,
        ];
    }

    /**
     * Update cached counts in post meta
     *
     * @param int $post_id Flow post ID
     * @return void
     */
    public static function update_cached_counts($post_id)
    {
        // Force refresh from database
        self::get_reaction_counts($post_id, true);
    }

    /**
     * Invalidate cache for a post
     *
     * @param int $post_id Flow post ID
     * @return void
     */
    public static function invalidate_cache($post_id)
    {
        delete_post_meta($post_id, 'flow_post_like_count');
        delete_post_meta($post_id, 'flow_post_dislike_count');
    }

    /**
     * Get reaction history for a post
     * 
     * Returns all reactions (active and inactive) for analytics.
     *
     * @param int $post_id Flow post ID
     * @param int $limit Maximum number of records
     * @return array Array of reaction records
     */
    public static function get_reaction_history($post_id, $limit = 100)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE flow_post_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $post_id,
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get user's reaction history
     * 
     * Returns all reactions by a specific user.
     *
     * @param int $user_id User ID
     * @param int $limit Maximum number of records
     * @return array Array of reaction records
     */
    public static function get_user_reaction_history($user_id, $limit = 100)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get total reaction statistics
     * 
     * Returns overall statistics across all posts.
     *
     * @return array Statistics array
     */
    public static function get_global_stats()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flow_post_reactions';

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_reactions,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_reactions,
                SUM(CASE WHEN is_active = 1 AND reaction = 1 THEN 1 ELSE 0 END) as total_likes,
                SUM(CASE WHEN is_active = 1 AND reaction = 0 THEN 1 ELSE 0 END) as total_dislikes,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT flow_post_id) as posts_with_reactions
             FROM $table_name",
            ARRAY_A
        );

        return $stats ?: [];
    }
}
