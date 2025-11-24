<?php

// flow-sub/flow-post-module/class-flow-post-setup.php

class Flow_Post_Setup
{

    /**
     * Initializes the class and hooks.
     */
    public static function init()
    {
        add_action('init', [self::class, 'register_flow_post_type']);
        add_action('template_redirect', [self::class, 'restrict_flow_post_access']);

        // Hook into wp_loaded, which runs very early on every public page request 
        // after WordPress core is set up, ensuring the role is correct.
        add_action('wp_loaded', [self::class, 'flow_sync_user_role']);
    }

    /**
     * Synchronizes the user's role based on their Flow subscription status.
     * This is the "lazy" role manager for your architecture.
     */
    public static function flow_sync_user_role()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        $user_id = $user->ID;
        $role_slug = 'flow_subscriber';

        // 1. Get the real-time active status from the Flow Sub plugin's logic (via the helper).
        // Ensure helper is loaded if not already (it should be via flow-sub.php, but safety first)
        if (!function_exists('flow_sub_is_user_active')) {
            return;
        }

        $is_active_in_flow = flow_sub_is_user_active($user_id);

        // 2. Check the user's current roles.
        $has_flow_role = in_array($role_slug, (array) $user->roles);

        if ($is_active_in_flow && !$has_flow_role) {
            // User is active in Flow but doesn't have the role -> PROMOTE
            $user->add_role($role_slug);

        } elseif (!$is_active_in_flow && $has_flow_role) {
            // User is NOT active in Flow but still has the role -> DEMOTE
            $user->remove_role($role_slug);
        }
    }

    /**
     * Enforces the paywall: Checks user capability and redirects if unauthorized.
     */
    public static function restrict_flow_post_access()
    {
        // 1. Check if the current page is a single Flow Post OR the Flow Post Archive/Feed
        if (is_singular('flow-post') || is_post_type_archive('flow-post')) {

            // 2. Check if the current user has the required capability: 'read_flow_post'
            // This capability is granted to 'administrator' and 'flow_subscriber' roles.
            if (!current_user_can('read_flow_post')) {

                // 3. If the user is not logged in or lacks the capability, redirect them.

                // You can redirect to a specific subscription page, but we'll use home_url() for simplicity.
                // Replace '/membership-signup/' with your desired landing page URL.
                $redirect_url = home_url('/membership-signup/');

                // Optional: If you have a specific page for the membership offer:
                // $redirect_url = get_permalink( get_page_by_path( 'membership-signup' ) );

                wp_safe_redirect($redirect_url);
                exit; // Terminate script execution after redirection
            }
        }
    }

    /**
     * Registers the Flow Post Custom Post Type.
     */
    public static function register_flow_post_type()
    {
        $labels = [
            'name' => _x('Flow Posts', 'Post Type General Name', 'flow-sub'),
            'singular_name' => _x('Flow Post', 'Post Type Singular Name', 'flow-sub'),
            'menu_name' => __('Flow Posts', 'flow-sub'),
            'all_items' => __('All Flow Posts', 'flow-sub'),
            'add_new_item' => __('Add New Flow Post', 'flow-sub'),
            'add_new' => __('Add New', 'flow-sub'),
            'new_item' => __('New Flow Post', 'flow-sub'),
            'edit_item' => __('Edit Flow Post', 'flow-sub'),
            'update_item' => __('Update Flow Post', 'flow-sub'),
            'view_item' => __('View Flow Post', 'flow-sub'),
            'view_items' => __('View Flow Posts', 'flow-sub'),
        ];

        // Define the custom capabilities based on the singular post type name.
        $capabilities = [
            'edit_post' => 'edit_flow_post',
            'read_post' => 'read_flow_post',
            'delete_post' => 'delete_flow_post',
            'edit_posts' => 'edit_flow_posts',
            'edit_others_posts' => 'edit_others_flow_posts',
            'publish_posts' => 'publish_flow_posts',
            'read_private_posts' => 'read_private_flow_posts',
            'create_posts' => 'edit_flow_posts', // Users who can edit can create
        ];

        $args = [
            'label' => __('Flow Post', 'flow-sub'),
            'description' => __('Content restricted to Flow subscribers.', 'flow-sub'),
            'labels' => $labels,
            'supports' => ['title', 'editor', 'author', 'comments'],
            'hierarchical' => false,
            'public' => true, // Publicly queryable to allow URL structure
            'show_ui' => true, // Show in Admin
            'show_in_menu' => true, // Show in Admin Menu
            'menu_position' => 5,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => 'flow-feed', // Custom archive slug for the feed page
            'exclude_from_search' => true, // Exclude from normal WP searches
            'publicly_queryable' => true,
            'capability_type' => 'flow_post', // The base capability type (singular)
            'capabilities' => $capabilities,
            'map_meta_cap' => true, // Crucial for mapping meta capabilities like 'edit_post'
            'rewrite' => ['slug' => 'flow-post'],
        ];
        register_post_type('flow-post', $args);
    }
}
Flow_Post_Setup::init();
