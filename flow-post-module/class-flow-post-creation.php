<?php
// flow-sub/flow-post-module/class-flow-post-creation.php

class Flow_Post_Creation
{

    public static function init()
    {
        // Hook the submission handler for logged-in users
        add_action('admin_post_create_flow_post', [self::class, 'handle_flow_post_submission']);
    }

    /**
     * Handles the front-end form submission for creating a new Flow Post.
     */
    public static function handle_flow_post_submission()
    {
        // 1. Security Check: Nonce verification
        if (!isset($_POST['flow_post_nonce']) || !wp_verify_nonce($_POST['flow_post_nonce'], 'create_flow_post_action')) {
            wp_die('Security check failed.', 403);
        }

        // 2. Permission Check: Only admins/users with publish rights can submit
        if (!current_user_can('publish_flow_posts')) {
            wp_die('You do not have permission to publish Flow Posts.', 403);
        }

        // --- 3. Gather and Sanitize Data ---
        $post_title = sanitize_text_field($_POST['post-title']);
        $post_content = wp_kses_post($_POST['post-text']);
        $video_url = isset($_POST['video-link']) ? esc_url_raw($_POST['video-link']) : '';

        // Basic validation
        if (empty($post_title) || empty($post_content)) {
            wp_safe_redirect(add_query_arg('flow_status', 'missing_fields', get_post_type_archive_link('flow-post')));
            exit;
        }

        // --- 4. Insert the Post ---
        $post_data = [
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_status' => 'publish',
            'post_type' => 'flow-post',
            'post_author' => get_current_user_id(),
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id) || $post_id === 0) {
            // Handle post insertion error
            wp_safe_redirect(add_query_arg('flow_status', 'post_error', get_post_type_archive_link('flow-post')));
            exit;
        }

        // --- 5. Handle Media Uploads (Photos) ---
        $gallery_ids = [];
        $files = $_FILES['photo-upload'];

        if (!empty($files['name'][0]) && is_array($files['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $upload_overrides = ['test_form' => false];
            $file_keys = array_keys($files['name']);

            // Loop through each uploaded file item
            foreach ($file_keys as $key) {
                if (count($gallery_ids) >= 4) {
                    break; // Max 4 photos allowed
                }

                // Prepare the $_FILES array for the current single file upload
                $current_file = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];

                // If there's no error, process the file
                if ($current_file['error'] === UPLOAD_ERR_OK) {

                    // We need to set the global $_FILES array for media_handle_upload to work correctly
                    $_FILES['upload_file'] = $current_file;

                    // Use media_handle_upload to handle security, moving, and attachment creation
                    $attachment_id = media_handle_upload('upload_file', $post_id, [
                        'post_title' => $current_file['name'],
                        'post_status' => 'inherit',
                    ]);

                    // Check for errors and record the ID
                    if (!is_wp_error($attachment_id) && $attachment_id > 0) {
                        $gallery_ids[] = $attachment_id;
                    }

                    // Cleanup global variable for next loop iteration
                    unset($_FILES['upload_file']);
                }
            }
        }

        // --- 6. Save Post Meta ---
        update_post_meta($post_id, 'flow_post_video_url', $video_url);
        if (!empty($gallery_ids)) {
            // Save as comma-separated string for easy retrieval
            update_post_meta($post_id, 'flow_post_gallery_ids', implode(',', $gallery_ids));
        }


        // --- 7. Success Redirect ---
        wp_safe_redirect(add_query_arg('flow_status', 'success', get_post_type_archive_link('flow-post')));
        exit;
    }
}
Flow_Post_Creation::init();
