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

        // Admin Hooks
        add_action('add_meta_boxes', [self::class, 'add_flow_post_meta_boxes']);
        add_action('save_post_flow-post', [self::class, 'save_flow_post_meta']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_scripts']);

        // Template Loading
        add_filter('template_include', [self::class, 'load_flow_post_template']);
    }

    /**
     * Loads the custom archive template from the plugin directory.
     */
    public static function load_flow_post_template($template)
    {
        if (is_post_type_archive('flow-post')) {
            $plugin_template = plugin_dir_path(__FILE__) . 'archive-flow-post.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
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

            // 2. Check if the current user has the required capability: 'access_flow_feed'
            // This capability is granted to 'administrator' and 'flow_subscriber' roles.
            // We use a custom capability to avoid conflicts with map_meta_cap and 'read_post'
            $can_read = current_user_can('access_flow_feed');

            if (!$can_read) {
                // 3. If the user is not logged in or lacks the capability, redirect them.
                $redirect_url = home_url('/membership-signup/');
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

    /**
     * Enqueues the necessary scripts for the Media Uploader.
     */
    public static function enqueue_admin_scripts($hook)
    {
        global $post;

        // Only load on the Flow Post edit screen
        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ('flow-post' === $post->post_type) {
                // Core WordPress Media Uploader scripts and styles
                wp_enqueue_media();

                // Enqueue our custom script for the gallery button
                wp_enqueue_script(
                    'flow-gallery-uploader',
                    plugin_dir_url(__FILE__) . 'flow-gallery-uploader.js',
                    ['jquery'],
                    '1.0',
                    true
                );
            }
        }
    }

    /**
     * Adds the two custom meta boxes to the Flow Post editor screen.
     */
    public static function add_flow_post_meta_boxes()
    {
        // 1. Video Link Meta Box
        add_meta_box(
            'flow_post_video_meta_box',
            __('Video Content (YouTube/Vimeo)', 'flow-sub'),
            [self::class, 'render_video_meta_box'],
            'flow-post',
            'normal',
            'high'
        );

        // 2. Gallery Images Meta Box
        add_meta_box(
            'flow_post_gallery_meta_box',
            __('Photo Gallery', 'flow-sub'),
            [self::class, 'render_gallery_meta_box'],
            'flow-post',
            'normal',
            'high'
        );
    }

    /**
     * Renders the HTML for the Video Link meta box.
     */
    public static function render_video_meta_box($post)
    {
        // Nonce for security
        wp_nonce_field('flow_post_meta_save', 'flow_post_video_nonce');

        // Get existing meta value
        $video_url = get_post_meta($post->ID, 'flow_post_video_url', true);

        ?>
        <p>
            <label for="flow_post_video_url">
                <?php _e('Paste the full YouTube or Vimeo video URL here (e.g., https://youtu.be/dQw4w9WgXcQ):', 'flow-sub'); ?>
            </label>
        </p>
        <input type="url" id="flow_post_video_url" name="flow_post_video_url" value="<?php echo esc_url($video_url); ?>"
            class="large-text" placeholder="https://www.youtube.com/watch?v=..." style="width: 100%;" />
        <p class="description">
            <?php _e('If a video link is present, it will take precedence over the photo gallery.', 'flow-sub'); ?>
        </p>
        <?php
    }

    /**
     * Renders the HTML for the Photo Gallery meta box (requires JS).
     */
    public static function render_gallery_meta_box($post)
    {
        // Get existing meta value (comma-separated IDs)
        $gallery_ids = get_post_meta($post->ID, 'flow_post_gallery_ids', true);
        $ids = array_filter(explode(',', $gallery_ids));
        ?>
        <div id="flow-gallery-container">
            <ul class="flow-gallery-list clearfix" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
                <?php
                if (!empty($ids)) {
                    foreach ($ids as $id) {
                        $image = wp_get_attachment_image_src($id, 'thumbnail');
                        if ($image) {
                            echo '<li data-id="' . esc_attr($id) . '" style="position: relative;">';
                            echo '<img src="' . esc_url($image[0]) . '" style="width: 80px; height: 80px; object-fit: cover; border: 1px solid #ddd;"/>';
                            echo '<a href="#" class="flow-gallery-remove" style="position: absolute; top: -5px; right: -5px; background: #dc3232; color: #fff; border-radius: 50%; width: 18px; height: 18px; text-align: center; line-height: 16px; font-size: 10px; text-decoration: none;">&times;</a>';
                            echo '</li>';
                        }
                    }
                }
                ?>
            </ul>

            <input type="hidden" id="flow_post_gallery_ids" name="flow_post_gallery_ids"
                value="<?php echo esc_attr($gallery_ids); ?>" />

            <button type="button" class="button button-primary" id="flow-gallery-upload-button">
                <?php _e('Select/Add Gallery Images', 'flow-sub'); ?>
            </button>
            <button type="button" class="button" id="flow-gallery-clear-button"
                style="<?php echo empty($ids) ? 'display: none;' : ''; ?>">
                <?php _e('Clear Gallery', 'flow-sub'); ?>
            </button>
            <p class="description"><?php _e('Select up to 4 images for the post gallery.', 'flow-sub'); ?></p>
        </div>
        <?php
    }

    /**
     * Saves the custom meta fields when the Flow Post is saved.
     * Hooked to save_post_flow-post.
     *
     * @param int $post_id The post ID.
     */
    public static function save_flow_post_meta($post_id)
    {
        // --- 1. Security Checks ---
        // Verify nonce
        if (!isset($_POST['flow_post_video_nonce']) || !wp_verify_nonce($_POST['flow_post_video_nonce'], 'flow_post_meta_save')) {
            return $post_id;
        }

        // Check user permission
        if (!current_user_can('edit_flow_post', $post_id)) {
            return $post_id;
        }

        // Prevent autosave/revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // --- 2. Save Video URL ---
        if (isset($_POST['flow_post_video_url'])) {
            // Sanitize as a URL
            $video_url = sanitize_url($_POST['flow_post_video_url']);
            update_post_meta($post_id, 'flow_post_video_url', $video_url);
        }

        // --- 3. Save Gallery IDs ---
        if (isset($_POST['flow_post_gallery_ids'])) {
            // Sanitize: ensure it's a comma-separated list of integers
            $raw_ids = explode(',', sanitize_text_field($_POST['flow_post_gallery_ids']));
            $valid_ids = array_map('intval', $raw_ids);
            $valid_ids = array_filter($valid_ids); // Remove zeros/empty

            // Limit to 4 images as per your requirement
            $final_ids = array_slice($valid_ids, 0, 4);

            // Save as a comma-separated string
            update_post_meta($post_id, 'flow_post_gallery_ids', implode(',', $final_ids));
        }
    }
}
Flow_Post_Setup::init();
