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
        $post_content = isset($_POST['post-text']) ? wp_kses_post($_POST['post-text']) : '';
        $video_url = isset($_POST['video-link']) ? esc_url_raw($_POST['video-link']) : '';

        // Basic validation - only title is required
        if (empty($post_title)) {
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

                    // Check for errors and resize the image
                    if (!is_wp_error($attachment_id) && $attachment_id > 0) {
                        // Resize the uploaded image
                        self::resize_uploaded_image($attachment_id);
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

    /**
     * Resize uploaded image to max 1600px and convert to JPG with quality compression
     * 
     * @param int $attachment_id The attachment ID
     */
    private static function resize_uploaded_image($attachment_id)
    {
        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            return;
        }

        // Get image editor
        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return;
        }

        // Get current dimensions
        $size = $image_editor->get_size();
        $width = $size['width'];
        $height = $size['height'];

        // Calculate new dimensions (max 1600px on longest side)
        $max_dimension = 1600;

        if ($width > $max_dimension || $height > $max_dimension) {
            if ($width > $height) {
                $new_width = $max_dimension;
                $new_height = intval(($height / $width) * $max_dimension);
            } else {
                $new_height = $max_dimension;
                $new_width = intval(($width / $height) * $max_dimension);
            }

            // Resize the image
            $image_editor->resize($new_width, $new_height, false);
        }

        // Set quality to 75% (between 70-80% as requested)
        $image_editor->set_quality(75);

        // Get file info
        $file_info = pathinfo($file_path);
        $new_file_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.jpg';

        // Save as JPG
        $saved = $image_editor->save($new_file_path, 'image/jpeg');

        if (!is_wp_error($saved) && file_exists($saved['path'])) {
            // Delete old file if it's not already a JPG
            if (strtolower($file_info['extension']) !== 'jpg' && strtolower($file_info['extension']) !== 'jpeg') {
                @unlink($file_path);
            }

            // Update attachment metadata
            update_attached_file($attachment_id, $saved['path']);

            // Update mime type
            wp_update_post([
                'ID' => $attachment_id,
                'post_mime_type' => 'image/jpeg'
            ]);

            // Regenerate metadata
            $metadata = wp_generate_attachment_metadata($attachment_id, $saved['path']);
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }
}
Flow_Post_Creation::init();
