<?php
/**
 * Template part for displaying a single Flow Post card
 * 
 * Expected variables:
 * - $post_id
 * - $video_url
 * - $gallery_ids
 * - $is_video_post
 * - $is_photo_post
 * - $post_type_attr
 * - $post_date
 * - $author_id
 * - $author_name
 * - $avatar_url
 * - $time_ago
 * - $comment_count
 * - $is_subscriber
 */
?>

<!-- POST CARD -->
<div class="post-card bg-card-bg rounded-xl border border-border-light overflow-hidden"
    data-post-type="<?php echo esc_attr($post_type_attr); ?>" data-post-date="<?php echo esc_attr($post_date); ?>">

    <!-- 1. Featured Media (TOP) with Soft Paywall -->
    <?php if ($is_video_post): ?>
        <div class="media-placeholder rounded-t-xl relative">
            <?php
            // Use WordPress oEmbed
            $embed_code = wp_oembed_get($video_url, ['width' => 800]);
            if ($embed_code) {
                echo $embed_code;
            } else {
                echo '<div class="absolute inset-0 flex items-center justify-center text-white">Invalid Video URL</div>';
            }
            ?>

            <?php if (!$is_subscriber): ?>
                <!-- Paywall Overlay for Non-Subscribers -->
                <div
                    class="absolute inset-0 z-10 flex flex-col justify-center items-center bg-black bg-opacity-70 backdrop-blur-sm p-4 text-center rounded-t-xl">
                    <h3 class="text-xl font-bold text-white mb-4">Contenido Exclusivo para Suscriptores</h3>
                    <p class="text-gray-300 mb-6">Únete a Flow para desbloquear este y todo el contenido.</p>
                    <a href="<?php echo home_url('/membership-signup/'); ?>"
                        class="bg-primary-blue text-white p-3 px-8 rounded-full font-bold uppercase tracking-wider hover:bg-opacity-90 transition-opacity">
                        Suscríbete Ahora
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($is_photo_post):
        $gallery_ids = array_slice($gallery_ids, 0, 4);
        $grid_class = 'grid-cols-' . (count($gallery_ids) == 1 ? '1' : '2');
        ?>
        <div class="grid <?php echo $grid_class; ?> gap-0.5 !p-0 !pb-0 !h-auto rounded-t-xl relative">
            <?php foreach ($gallery_ids as $attachment_id):
                $image_url = wp_get_attachment_image_url($attachment_id, 'large');
                if ($image_url): ?>
                    <img class="w-full h-auto object-cover aspect-square" src="<?php echo esc_url($image_url); ?>"
                        alt="Gallery Image">
                <?php endif; endforeach; ?>

            <?php if (!$is_subscriber): ?>
                <!-- Paywall Overlay for Non-Subscribers -->
                <div
                    class="absolute inset-0 z-10 flex flex-col justify-center items-center bg-black bg-opacity-70 backdrop-blur-sm p-4 text-center rounded-t-xl">
                    <h3 class="text-xl font-bold text-white mb-4">Contenido Exclusivo para Suscriptores</h3>
                    <p class="text-gray-300 mb-6">Únete a Flow para desbloquear este y todo el contenido.</p>
                    <a href="<?php echo home_url('/membership-signup/'); ?>"
                        class="bg-primary-blue text-white p-3 px-8 rounded-full font-bold uppercase tracking-wider hover:bg-opacity-90 transition-opacity">
                        Suscríbete Ahora
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Inner Content Area -->
    <div class="p-4 md:p-6">

        <!-- 2. Post Title -->
        <h2 class="text-2xl font-extrabold text-gray-900 mb-2 text-left">
            <?php the_title(); ?>
        </h2>

        <!-- Author & Date -->
        <div class="flex items-center text-sm text-gray-500 mb-4">
            <img class="w-7 h-7 rounded-full object-cover mr-2" src="<?php echo esc_url($avatar_url); ?>" alt="Avatar">
            <p class="font-semibold text-gray-800 mr-1"><?php echo esc_html($author_name); ?></p>
            <span>· <?php echo $time_ago; ?></span>
        </div>

        <!-- 3. Post Description -->
        <div class="text-gray-700 leading-relaxed mb-6">
            <?php the_content(); ?>
        </div>

        <!-- 4. Interaction Status & Like Button -->
        <div class="flex justify-between items-center text-gray-500 mb-6 pb-3 border-b border-border-light">
            <div class="flex items-center space-x-4">
                <!-- Like Status/Button (Visual Only for now) -->
                <button class="flex items-center text-gray-500 hover:text-accent-red transition-colors">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                            clip-rule="evenodd"></path>
                    </svg>
                    <span
                        class="text-sm font-medium text-gray-700 ml-1"><?php echo rand(10, 50); // Fake random likes ?></span>
                </button>
                <!-- Comment Count -->
                <span class="text-sm text-gray-500 cursor-pointer hover:text-primary-blue"><?php echo $comment_count; ?>
                    Comentarios</span>
            </div>
            <!-- Share Icon -->
            <button class="text-gray-400 hover:text-gray-700 transition-colors">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M15 8a3 3 0 10-2.977-2.977l-4.152 2.152A2.948 2.948 0 006 8c-1.657 0-3 1.343-3 3s1.343 3 3 3c.83 0 1.58-.33 2.138-.87l4.154 2.156a3 3 0 10.237-4.708l-4.157-2.158A3.012 3.012 0 0015 8z">
                    </path>
                </svg>
            </button>
        </div>

        <!-- 5. Comments Section (Preview) -->
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-gray-900">Discusión</h3>

            <?php
            $comments = get_comments(['post_id' => $post_id, 'number' => 2, 'status' => 'approve']);
            if ($comments):
                foreach ($comments as $comment):
                    // Get custom profile picture for commenter
                    $c_avatar = get_user_meta($comment->user_id, 'profile_picture', true);
                    if (empty($c_avatar)) {
                        $c_avatar = get_avatar_url($comment->comment_author_email, ['size' => 40]);
                    }
                    ?>
                    <div class="flex space-x-3">
                        <img class="w-8 h-8 rounded-full object-cover shrink-0" src="<?php echo esc_url($c_avatar); ?>"
                            alt="Commenter Avatar">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                <?php echo esc_html($comment->comment_author); ?> <span
                                    class="text-xs font-normal text-gray-500 ml-1">·
                                    hace
                                    <?php echo human_time_diff(strtotime($comment->comment_date), current_time('timestamp')); ?></span>
                            </p>
                            <p class="text-sm text-gray-700"><?php echo get_comment_text($comment); ?></p>
                        </div>
                    </div>
                <?php endforeach;
            else: ?>
                <p class="text-sm text-gray-500 italic">Aún no hay comentarios. ¡Sé el primero!</p>
            <?php endif; ?>
        </div>

        <!-- 6. Comment Input Bar (Only for Subscribers) -->
        <?php if ($is_subscriber): ?>
            <div class="mt-6 pt-4 border-t border-border-light">
                <div class="flex items-center space-x-3">
                    <?php
                    // Get custom profile picture for current user
                    $current_user_avatar = get_user_meta(get_current_user_id(), 'profile_picture', true);
                    if (empty($current_user_avatar)) {
                        $current_user_avatar = get_avatar_url(get_current_user_id(), ['size' => 40]);
                    }
                    ?>
                    <img class="w-10 h-10 rounded-full object-cover shrink-0"
                        src="<?php echo esc_url($current_user_avatar); ?>" alt="My Avatar">
                    <form action="<?php echo esc_url(site_url('/wp-comments-post.php')); ?>" method="post"
                        class="flex-grow flex items-center space-x-2 flow-comment-form">
                        <input type="hidden" name="comment_post_ID" value="<?php echo $post_id; ?>" />
                        <input type="hidden" name="redirect_to"
                            value="<?php echo esc_url(get_post_type_archive_link('flow-post')); ?>" />
                        <input type="text" name="comment" placeholder="Únete a la discusión..."
                            class="comment-input flex-grow p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-blue focus:border-primary-blue transition-shadow shadow-inner text-sm outline-none"
                            required>
                        <button type="submit"
                            class="bg-black text-white p-3 px-6 rounded-md font-semibold hover:bg-gray-800 transition-colors">Publicar</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Non-subscribers see a message -->
            <div class="mt-6 pt-4 border-t border-border-light">
                <p class="text-center text-gray-500 italic py-4">
                    <a href="<?php echo home_url('/membership-signup/'); ?>"
                        class="text-primary-blue hover:underline font-semibold">Suscríbete</a> para unirte a
                    la
                    discusión
                </p>
            </div>
        <?php endif; ?>

    </div>
</div>
<!-- End Post Card -->