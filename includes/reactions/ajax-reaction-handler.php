<?php
/**
 * Flow Sub Reaction AJAX Handler
 * 
 * Handles AJAX requests for reaction toggling.
 * Includes security checks, validation, and error handling.
 *
 * @package Flow_Sub
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Register AJAX handlers
 */
add_action('wp_ajax_flow_toggle_reaction', 'flow_handle_reaction_ajax');
add_action('wp_ajax_nopriv_flow_toggle_reaction', 'flow_handle_reaction_ajax_nopriv');

/**
 * Handle reaction toggle AJAX request (logged in users)
 *
 * @return void
 */
function flow_handle_reaction_ajax()
{
    // Verify nonce
    if (!check_ajax_referer('flow_reaction_nonce', 'nonce', false)) {
        wp_send_json_error([
            'message' => 'Security check failed. Please refresh the page and try again.',
        ], 403);
        return;
    }

    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => 'You must be logged in to react to posts.',
        ], 401);
        return;
    }

    // Get and validate post ID
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error([
            'message' => 'Invalid post ID.',
        ], 400);
        return;
    }

    // Verify post exists and is a flow-post
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'flow-post') {
        wp_send_json_error([
            'message' => 'Post not found or invalid post type.',
        ], 404);
        return;
    }

    // Get and validate reaction
    $reaction = isset($_POST['reaction']) ? intval($_POST['reaction']) : -1;
    if (!in_array($reaction, [0, 1])) {
        wp_send_json_error([
            'message' => 'Invalid reaction type.',
        ], 400);
        return;
    }

    // Get current user ID
    $user_id = get_current_user_id();

    // Toggle the reaction
    $result = Flow_Sub_Reactions::toggle_reaction($post_id, $user_id, $reaction);

    // Check for errors
    if (is_wp_error($result)) {
        wp_send_json_error([
            'message' => $result->get_error_message(),
        ], 500);
        return;
    }

    // Success response
    wp_send_json_success([
        'like_count' => $result['like_count'],
        'dislike_count' => $result['dislike_count'],
        'user_reaction' => $result['user_reaction'],
        'message' => flow_get_reaction_message($result['user_reaction']),
    ]);
}

/**
 * Handle reaction toggle for non-logged-in users
 * 
 * Returns error message prompting login.
 *
 * @return void
 */
function flow_handle_reaction_ajax_nopriv()
{
    wp_send_json_error([
        'message' => 'Please log in to react to posts.',
        'login_required' => true,
    ], 401);
}

/**
 * Get user-friendly message based on reaction state
 *
 * @param int|null $reaction User's current reaction (1, 0, or null)
 * @return string Message
 */
function flow_get_reaction_message($reaction)
{
    if ($reaction === 1) {
        return 'You liked this post';
    } elseif ($reaction === 0) {
        return 'You disliked this post';
    } else {
        return 'Reaction removed';
    }
}

/**
 * Enqueue reaction scripts and styles
 * 
 * Only loads on flow-post archive pages.
 *
 * @return void
 */
function flow_enqueue_reaction_scripts()
{
    // Only load on flow-post pages
    if (!is_post_type_archive('flow-post') && get_post_type() !== 'flow-post') {
        return;
    }

    // Enqueue JavaScript
    wp_enqueue_script(
        'flow-reactions',
        FLOW_SUB_URL . 'flow-post-module/assets/js/reactions.js',
        ['jquery'],
        FLOW_SUB_VERSION,
        true
    );

    // Localize script with AJAX URL and nonce
    wp_localize_script('flow-reactions', 'flowReactions', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('flow_reaction_nonce'),
        'strings' => [
            'error' => __('An error occurred. Please try again.', 'flow-sub'),
            'loginRequired' => __('Please log in to react to posts.', 'flow-sub'),
        ],
    ]);

    // Enqueue CSS (if you create a separate CSS file)
    // wp_enqueue_style(
    //     'flow-reactions',
    //     FLOW_SUB_URL . 'flow-post-module/assets/css/reactions.css',
    //     [],
    //     FLOW_SUB_VERSION
    // );
}
add_action('wp_enqueue_scripts', 'flow_enqueue_reaction_scripts');
