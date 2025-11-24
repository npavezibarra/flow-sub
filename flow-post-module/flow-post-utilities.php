<?php
/**
 * Utility functions for Flow Post module
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom callback function to render each comment with Tailwind CSS.
 *
 * @param WP_Comment $comment Comment object.
 * @param array $args Arguments passed to wp_list_comments().
 * @param int $depth Comment depth.
 */
function flow_comment_callback($comment, $args, $depth)
{
    // Determine the HTML tag based on the style argument (default is 'div')
    $tag = ('div' === $args['style']) ? 'div' : 'li';

    // Get the commenter's avatar
    $avatar_html = get_avatar($comment, 32, '', 'Commenter Avatar', ['class' => 'w-8 h-8 rounded-full object-cover shrink-0']);
    ?>

    <<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class(empty($args['has_children']) ? '' : 'parent'); ?>>

        <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">

            <div class="flex space-x-3">

                <?php echo $avatar_html; ?>

                <div>
                    <p class="text-sm font-semibold text-gray-900">
                        <?php
                        // Link the author name if they provided a URL
                        printf('%s', get_comment_author_link());
                        ?>
                        <span class="text-xs font-normal text-gray-500 ml-1">
                            · <?php echo human_time_diff(get_comment_time('U'), current_time('timestamp')) . ' ago'; ?>
                        </span>
                    </p>

                    <?php if ('0' == $comment->comment_approved): ?>
                        <p class="comment-awaiting-moderation text-sm text-yellow-600">
                            <?php _e('Your comment is awaiting moderation.', 'flow-sub'); ?>
                        </p>
                    <?php endif; ?>

                    <div class="text-sm text-gray-700">
                        <?php comment_text(); ?>
                    </div>

                    <div class="text-xs text-gray-500 flex items-center space-x-3 mt-1">
                        <span><?php echo absint($comment->comment_ID * 1) . ' Likes'; ?></span>
                        <?php
                        // Display the reply button only if allowed
                        comment_reply_link(array_merge($args, [
                            'add_below' => 'div-comment',
                            'depth' => $depth,
                            'max_depth' => $args['max_depth'],
                            'before' => '<span class="text-primary-blue hover:underline cursor-pointer">',
                            'after' => '</span>',
                            'reply_text' => __('Reply', 'flow-sub'),
                        ]));
                        ?>
                    </div>
                </div>
            </div>

        </article>

    <?php
    // NOTE: WordPress handles the closing tag based on $tag variable if children exist
}
