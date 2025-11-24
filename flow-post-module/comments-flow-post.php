<?php
/**
 * Custom Comment Template for Flow Posts
 */

if (post_password_required()) {
    return;
}

// 1. Check if comments are open or if there are comments already
if (have_comments() || comments_open()):
    ?>
    <div class="space-y-4">
        <h3 class="text-lg font-bold text-gray-900">Discussion (<?php echo get_comments_number(); ?>)</h3>

        <?php if (have_comments()): ?>
            <div class="flow-comment-list space-y-4">
                <?php
                // 2. Display the comments using the custom callback function
                wp_list_comments(array(
                    'style' => 'div', // Use <div>s instead of <li>s
                    'short_ping' => true,
                    'avatar_size' => 32,
                    'callback' => 'flow_comment_callback', // Our custom render function
                ));
                ?>
            </div>

            <?php the_comments_pagination(array(
                'prev_text' => '<span class="text-primary-blue hover:underline">&larr; Older Comments</span>',
                'next_text' => '<span class="text-primary-blue hover:underline">Newer Comments &rarr;</span>',
                'screen_reader_text' => 'Comment navigation',
            )); ?>

        <?php endif; // End have_comments() ?>
    </div>

    <div class="mt-6 pt-4 border-t border-border-light">
        <?php
        // Only show the form if comments are open
        if (comments_open()):

            // Get commenter data
            $commenter = wp_get_current_commenter();

            // Define custom arguments to style the comment form elements
            $fields = [
                'author' => '<p class="comment-form-author">' .
                    '<label for="author" class="block text-sm font-medium text-gray-700 mb-1">' . __('Name', 'flow-sub') . '</label> ' .
                    '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30" class="comment-input w-full p-3 border border-gray-300 rounded-xl shadow-inner text-sm outline-none" required /></p>',
                'email' => '<p class="comment-form-email">' .
                    '<label for="email" class="block text-sm font-medium text-gray-700 mb-1">' . __('Email', 'flow-sub') . '</label> ' .
                    '<input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30" class="comment-input w-full p-3 border border-gray-300 rounded-xl shadow-inner text-sm outline-none" required /></p>',
                'url' => '', // Remove URL field
            ];

            // Define the comment field (main text area)
            $comment_field = '<textarea id="comment" name="comment" rows="3" placeholder="Join the discussion..." class="comment-input flex-grow p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-blue focus:border-primary-blue transition-shadow shadow-inner text-sm outline-none" required></textarea>';

            // The main comment form call
            comment_form([
                'title_reply' => '', // Hide default reply title
                'title_reply_to' => __('Reply to %s', 'flow-sub'),
                'comment_field' => '<div class="flex items-start space-x-3">' .
                    get_avatar(get_current_user_id(), 40, '', 'My Avatar', ['class' => 'w-10 h-10 rounded-full object-cover shrink-0']) .
                    $comment_field .
                    '</div>',
                'logged_in_as' => '<p class="logged-in-as text-sm text-gray-600 mb-4">' .
                    sprintf(
                        __('Logged in as <a class="text-primary-blue hover:underline" href="%1$s">%2$s</a>. <a class="text-gray-500 hover:underline" href="%3$s" title="Log out of this account">Log out?</a>', 'flow-sub'),
                        admin_url('profile.php'),
                        wp_get_current_user()->display_name,
                        wp_logout_url(apply_filters('the_permalink', get_permalink()))
                    ) . '</p>',
                'fields' => apply_filters('comment_form_default_fields', $fields),
                'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="bg-primary-blue text-white p-3 rounded-xl font-semibold hover:bg-opacity-90 transition-opacity">%4$s</button>',
                'submit_field' => '<div class="pt-2 flex justify-end">%1$s %2$s</div>',
                'comment_notes_before' => '',
                'comment_notes_after' => '',
            ]);
        else: ?>
            <p class="text-center text-gray-500 pt-4"><?php _e('Comments are closed for this post.', 'flow-sub'); ?></p>
        <?php endif; // End comments_open() ?>
    </div>
<?php
endif; // End have_comments() or comments_open()
