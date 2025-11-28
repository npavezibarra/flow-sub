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

        // AJAX Hooks for Comment Submission
        add_action('wp_ajax_submit_flow_comment', [self::class, 'ajax_submit_flow_comment']);
        add_action('wp_ajax_nopriv_submit_flow_comment', [self::class, 'ajax_submit_flow_comment']);

        // AJAX Hooks for Post Filtering
        add_action('wp_ajax_flow_filter_posts', [self::class, 'ajax_filter_posts']);
        add_action('wp_ajax_nopriv_flow_filter_posts', [self::class, 'ajax_filter_posts']);

        // Template Loading
        add_filter('template_include', [self::class, 'load_flow_post_template']);

        // AJAX Hook for Email Check
        add_action('wp_ajax_nopriv_flow_check_email_exists', [self::class, 'ajax_check_email_exists']);
        add_action('wp_ajax_flow_check_email_exists', [self::class, 'ajax_check_email_exists']);
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
     * Only redirects on single posts - archive uses soft paywall with conditional rendering.
     */
    public static function restrict_flow_post_access()
    {
        // Only check for single Flow Posts (not the archive/feed)
        if (is_singular('flow-post')) {

            // Check if the current user has the required capability: 'access_flow_feed'
            // This capability is granted to 'administrator' and 'flow_subscriber' roles.
            $can_read = current_user_can('access_flow_feed');

            if (!$can_read) {
                // If the user is not logged in or lacks the capability, redirect them.
                $redirect_url = home_url('/membership-signup/');
                wp_safe_redirect($redirect_url);
                exit; // Terminate script execution after redirection
            }
        }
        // Archive page (is_post_type_archive('flow-post')) is now accessible to all
        // Soft paywall logic is handled in the template itself
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
            'name_admin_bar' => __('Flow Post', 'flow-sub'),
            'archives' => __('Flow Post Archives', 'flow-sub'),
            'attributes' => __('Flow Post Attributes', 'flow-sub'),
            'parent_item_colon' => __('Parent Flow Post:', 'flow-sub'),
            'all_items' => __('All Flow Posts', 'flow-sub'),
            'add_new_item' => __('Add New Flow Post', 'flow-sub'),
            'add_new' => __('Add New', 'flow-sub'),
            'new_item' => __('New Flow Post', 'flow-sub'),
            'edit_item' => __('Edit Flow Post', 'flow-sub'),
            'update_item' => __('Update Flow Post', 'flow-sub'),
            'view_item' => __('View Flow Post', 'flow-sub'),
            'view_items' => __('View Flow Posts', 'flow-sub'),
            'search_items' => __('Search Flow Post', 'flow-sub'),
            'not_found' => __('Not found', 'flow-sub'),
            'not_found_in_trash' => __('Not found in Trash', 'flow-sub'),
            'featured_image' => __('Featured Image', 'flow-sub'),
            'set_featured_image' => __('Set featured image', 'flow-sub'),
            'remove_featured_image' => __('Remove featured image', 'flow-sub'),
            'use_featured_image' => __('Use as featured image', 'flow-sub'),
            'insert_into_item' => __('Insert into flow post', 'flow-sub'),
            'uploaded_to_this_item' => __('Uploaded to this flow post', 'flow-sub'),
            'items_list' => __('Flow Posts list', 'flow-sub'),
            'items_list_navigation' => __('Flow Posts list navigation', 'flow-sub'),
            'filter_items_list' => __('Filter flow posts list', 'flow-sub'),
        ];
        $args = [
            'label' => __('Flow Post', 'flow-sub'),
            'description' => __('Post Type Description', 'flow-sub'),
            'labels' => $labels,
            'supports' => ['title', 'editor', 'thumbnail', 'comments', 'author'],
            'taxonomies' => [],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'flow_post', // The base capability type (singular)
            'capabilities' => [
                'edit_post' => 'edit_flow_post',
                'read_post' => 'read_flow_post',
                'delete_post' => 'delete_flow_post',
                'edit_posts' => 'edit_flow_posts',
                'edit_others_posts' => 'edit_others_flow_posts',
                'publish_posts' => 'publish_flow_posts',
                'read_private_posts' => 'read_private_flow_posts',
                'create_posts' => 'edit_flow_posts', // Users who can edit can create
            ],
            'map_meta_cap' => true,
            'rewrite' => ['slug' => 'flow-feed'],
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

    /**
     * AJAX handler for submitting comments without page refresh
     */
    public static function ajax_submit_flow_comment()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'flow_comment_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to comment');
            return;
        }

        // Get current user
        $current_user = wp_get_current_user();

        // Check if user has subscriber role
        $is_subscriber = in_array('flow_subscriber', (array) $current_user->roles) ||
            in_array('administrator', (array) $current_user->roles);

        if (!$is_subscriber) {
            wp_send_json_error('You must be a subscriber to comment');
            return;
        }

        // Get and sanitize data
        $post_id = isset($_POST['comment_post_ID']) ? intval($_POST['comment_post_ID']) : 0;
        $comment_text = isset($_POST['comment']) ? sanitize_text_field($_POST['comment']) : '';

        if (empty($comment_text) || $post_id === 0) {
            wp_send_json_error('Invalid comment data');
            return;
        }

        // Prepare comment data
        $comment_data = array(
            'comment_post_ID' => $post_id,
            'comment_author' => $current_user->display_name,
            'comment_author_email' => $current_user->user_email,
            'comment_author_url' => $current_user->user_url,
            'comment_content' => $comment_text,
            'comment_type' => 'comment',
            'comment_parent' => 0,
            'user_id' => $current_user->ID,
            'comment_approved' => 1, // Auto-approve
        );

        // Insert comment
        $comment_id = wp_insert_comment($comment_data);

        if ($comment_id) {
            // Get custom profile picture or fallback to WordPress avatar
            $avatar_url = get_user_meta($current_user->ID, 'profile_picture', true);
            if (empty($avatar_url)) {
                $avatar_url = get_avatar_url($current_user->ID, ['size' => 40]);
            }

            // Return success with comment data
            wp_send_json_success(array(
                'comment_id' => $comment_id,
                'author' => $current_user->display_name,
                'avatar' => $avatar_url,
                'comment_text' => esc_html($comment_text),
            ));
        } else {
            wp_send_json_error('Failed to insert comment');
        }
    }

    /**
     * AJAX handler for filtering Flow Posts
     */
    public static function ajax_filter_posts()
    {
        $post_types = isset($_POST['post_types']) ? (array) $_POST['post_types'] : [];
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Build query args - get all posts first
        $args = [
            'post_type' => 'flow-post',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Add date query if specified
        if (!empty($date_from) || !empty($date_to)) {
            $date_query = [];

            if (!empty($date_from)) {
                $date_query['after'] = $date_from;
            }

            if (!empty($date_to)) {
                $date_query['before'] = $date_to;
            }

            $date_query['inclusive'] = true;
            $args['date_query'] = [$date_query];
        }

        $query = new WP_Query($args);

        ob_start();

        $found_posts = false;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Get post data
                $post_id = get_the_ID();
                $video_url = get_post_meta($post_id, 'flow_post_video_url', true);
                $gallery_ids = get_post_meta($post_id, 'flow_post_gallery_ids', true);

                // Determine post type
                $is_video_post = !empty($video_url);
                $is_photo_post = !empty($gallery_ids) && !$is_video_post;
                $is_text_post = !$is_video_post && !$is_photo_post;

                // Filter by post type if specified
                if (!empty($post_types)) {
                    $matches_filter = false;

                    if (in_array('video', $post_types) && $is_video_post) {
                        $matches_filter = true;
                    }
                    if (in_array('photo', $post_types) && $is_photo_post) {
                        $matches_filter = true;
                    }
                    if (in_array('text', $post_types) && $is_text_post) {
                        $matches_filter = true;
                    }

                    // Skip this post if it doesn't match the filter
                    if (!$matches_filter) {
                        continue;
                    }
                }

                // Filter by date if specified
                if (!empty($date_from)) {
                    $post_date = get_the_date('Y-m-d', $post_id);
                    if ($post_date < $date_from) {
                        continue;
                    }
                }
                if (!empty($date_to)) {
                    $post_date = get_the_date('Y-m-d', $post_id);
                    if ($post_date > $date_to) {
                        continue;
                    }
                }

                $found_posts = true;

                if (!empty($gallery_ids) && is_string($gallery_ids)) {
                    $gallery_ids = array_map('intval', explode(',', $gallery_ids));
                } elseif (empty($gallery_ids)) {
                    $gallery_ids = [];
                }

                // Build post type attribute
                $post_type_attr = [];
                if ($is_video_post)
                    $post_type_attr[] = 'video';
                if ($is_photo_post)
                    $post_type_attr[] = 'photo';
                if ($is_text_post)
                    $post_type_attr[] = 'text';
                $post_type_attr = implode(',', $post_type_attr);

                $post_date = get_the_date('Y-m-d');

                // Get author info
                $author_id = get_the_author_meta('ID');
                $author_name = get_the_author_meta('display_name');
                $avatar_custom = get_user_meta($author_id, 'profile_picture', true);
                if (empty($avatar_custom)) {
                    $avatar_url = get_avatar_url($author_id, ['size' => 40]);
                } else {
                    $avatar_url = $avatar_custom;
                }
                $time_ago = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago';
                $comment_count = get_comments_number();

                // Check if user is a subscriber
                $current_user = wp_get_current_user();
                $is_subscriber = in_array('flow_subscriber', (array) $current_user->roles) || in_array('administrator', (array) $current_user->roles);

                // Render post card using template
                include(plugin_dir_path(__FILE__) . 'template-parts/post-card.php');
            }

            if (!$found_posts) {
                echo '<div class="text-center py-12 text-gray-500">No se encontraron publicaciones que coincidan con los filtros.</div>';
            }
        } else {
            echo '<div class="text-center py-12 text-gray-500">No se encontraron publicaciones que coincidan con los filtros.</div>';
        }

        wp_reset_postdata();
        $content = ob_get_clean();

        wp_send_json_success(['html' => $content]);
        wp_die();
    }
    /**
     * AJAX handler to check if an email exists.
     */
    public static function ajax_check_email_exists()
    {
        // Verify nonce if you want to be strict, but for a public login check it might be optional
        // depending on security requirements. Let's assume we want to be open for the login form.

        if (isset($_POST['email'])) {
            $email = sanitize_email($_POST['email']);
            if (email_exists($email)) {
                wp_send_json_success(['exists' => true]);
            } else {
                wp_send_json_success(['exists' => false]);
            }
        }
        wp_send_json_error(['message' => 'No email provided']);
    }
}
Flow_Post_Setup::init();
