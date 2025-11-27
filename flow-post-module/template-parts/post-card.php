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
        <div class="flow-photo-gallery-container relative rounded-t-xl overflow-hidden" style="height: 350px;"
            data-post-id="<?php echo $post_id; ?>">
            <div class="grid <?php echo $grid_class; ?> gap-0.5 h-full">
                <?php foreach ($gallery_ids as $index => $attachment_id):
                    $image_url = wp_get_attachment_image_url($attachment_id, 'large');
                    $full_image_url = wp_get_attachment_image_url($attachment_id, 'full');
                    if ($image_url): ?>
                        <img class="w-full h-full object-cover gallery-image" src="<?php echo esc_url($image_url); ?>"
                            data-full-src="<?php echo esc_url($full_image_url); ?>" data-index="<?php echo $index; ?>"
                            alt="Gallery Image">
                    <?php endif; endforeach; ?>
            </div>

            <!-- Expand Button (shows on hover) -->
            <button class="flow-expand-btn" aria-label="View fullscreen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3">
                    </path>
                </svg>
            </button>

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

        <!-- Fullscreen Modal (hidden by default) -->
        <div class="flow-fullscreen-modal" id="modal-<?php echo $post_id; ?>" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <button class="modal-close" aria-label="Close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <img class="modal-image" src="" alt="Fullscreen view">
            </div>
        </div>

        <style>
            /* Photo Gallery Styles */
            .flow-photo-gallery-container {
                position: relative;
            }

            .flow-photo-gallery-container .gallery-image {
                cursor: pointer;
            }

            /* Expand Button */
            .flow-expand-btn {
                position: absolute;
                top: 12px;
                right: 12px;
                width: 40px;
                height: 40px;
                background: rgba(0, 0, 0, 0.6);
                border: none;
                border-radius: 50%;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                opacity: 0;
                transition: all 0.3s ease;
                z-index: 5;
            }

            .flow-photo-gallery-container:hover .flow-expand-btn {
                opacity: 1;
            }

            .flow-expand-btn:hover {
                background: rgba(0, 0, 0, 0.8);
                transform: scale(1.1);
            }

            /* Fullscreen Modal */
            .flow-fullscreen-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                z-index: 9999;
            }

            .modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.95);
                backdrop-filter: blur(10px);
            }

            .modal-content {
                position: relative;
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px;
            }

            .modal-image {
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
                border-radius: 8px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            }

            .modal-close {
                position: absolute;
                top: 20px;
                right: 20px;
                width: 44px;
                height: 44px;
                background: rgba(255, 255, 255, 0.1);
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .modal-close:hover {
                background: rgba(255, 255, 255, 0.2);
                transform: scale(1.1);
            }
        </style>

        <script>
            (function () {
                const postId = <?php echo $post_id; ?>;
                const container = document.querySelector(`[data-post-id="${postId}"].flow-photo-gallery-container`);
                const modal = document.getElementById(`modal-${postId}`);
                const modalImage = modal.querySelector('.modal-image');
                const expandBtn = container.querySelector('.flow-expand-btn');
                const closeBtn = modal.querySelector('.modal-close');
                const overlay = modal.querySelector('.modal-overlay');
                const galleryImages = container.querySelectorAll('.gallery-image');

                // Open modal function
                function openModal(imageSrc) {
                    modalImage.src = imageSrc;
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }

                // Close modal function
                function closeModal() {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }

                // Expand button click - show first image
                expandBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const firstImage = galleryImages[0];
                    const fullSrc = firstImage.dataset.fullSrc || firstImage.src;
                    openModal(fullSrc);
                });

                // Gallery image click
                galleryImages.forEach(function (img) {
                    img.addEventListener('click', function () {
                        const fullSrc = this.dataset.fullSrc || this.src;
                        openModal(fullSrc);
                    });
                });

                // Close button click
                closeBtn.addEventListener('click', closeModal);

                // Overlay click
                overlay.addEventListener('click', closeModal);

                // ESC key to close
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && modal.style.display === 'block') {
                        closeModal();
                    }
                });
            })();
        </script>
    <?php endif; ?>

    <!-- Inner Content Area -->
    <div class="p-4 md:p-6">

        <!-- 2. Title and Reactions Row -->
        <div class="flex items-start gap-4 mb-2">
            <!-- Title (66% width) -->
            <h2 class="text-2xl font-extrabold text-gray-900 text-left" style="flex: 0 0 66%;">
                <?php the_title(); ?>
            </h2>

            <!-- Reactions (33% width) - aligned to top right -->
            <div style="flex: 0 0 33%; display: flex; align-items: flex-start; justify-content: flex-end;">
                <?php
                // Get reaction data
                $reaction_counts = Flow_Sub_Reactions::get_reaction_counts($post_id);
                $like_count = $reaction_counts['likes'];
                $dislike_count = $reaction_counts['dislikes'];
                $user_reaction = is_user_logged_in() ? Flow_Sub_Reactions::get_user_reaction($post_id, get_current_user_id()) : null;

                // Include reaction buttons template
                include(plugin_dir_path(__FILE__) . 'reaction-buttons.php');
                ?>
            </div>
        </div>

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

        <!-- 4. Comment Count & Share -->
        <div class="flex justify-between items-center text-gray-500 mb-6 pb-3 border-b border-border-light">
            <div class="flex items-center space-x-4">
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

            <!-- Scrollable Comments Container -->
            <div class="flow-comments-container">
                <?php
                $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
                if ($comments):
                    foreach ($comments as $comment):
                        // Get custom profile picture for commenter
                        $c_avatar = get_user_meta($comment->user_id, 'profile_picture', true);
                        if (empty($c_avatar)) {
                            $c_avatar = get_avatar_url($comment->comment_author_email, ['size' => 40]);
                        }
                        ?>
                        <div class="flex space-x-3 mb-4">
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
        </div>

        <style>
            /* Scrollable Comments Section */
            .flow-comments-container {
                max-height: 350px;
                overflow-y: auto;
                position: relative;
                scroll-behavior: smooth;
                padding-right: 8px;
            }

            /* Custom Scrollbar Styling */
            .flow-comments-container::-webkit-scrollbar {
                width: 6px;
            }

            .flow-comments-container::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }

            .flow-comments-container::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 3px;
            }

            .flow-comments-container::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }

            /* Fade Gradient at Bottom */
            .flow-comments-container::after {
                content: '';
                position: sticky;
                bottom: 0;
                left: 0;
                right: 0;
                height: 60px;
                background: linear-gradient(to bottom, transparent 0%, rgba(255, 255, 255, 0.8) 50%, white 100%);
                pointer-events: none;
                display: block;
            }

            /* Hide gradient when scrolled to bottom */
            .flow-comments-container.scrolled-to-bottom::after {
                display: none;
            }
        </style>

        <script>
            (function() {
                const commentsContainers = document.querySelectorAll('.flow-comments-container');
                
                commentsContainers.forEach(function(container) {
                    // Check if content is scrollable
                    function updateGradient() {
                        const isScrollable = container.scrollHeight > container.clientHeight;
                        const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 5;
                        
                        if (!isScrollable || isAtBottom) {
                            container.classList.add('scrolled-to-bottom');
                        } else {
                            container.classList.remove('scrolled-to-bottom');
                        }
                    }
                    
                    // Initial check
                    updateGradient();
                    
                    // Update on scroll
                    container.addEventListener('scroll', updateGradient);
                    
                    // Update on window resize
                    window.addEventListener('resize', updateGradient);
                });
            })();
        </script>

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
                            class="bg-black text-white p-2 px-5 rounded-md font-medium text-sm hover:bg-gray-800 transition-colors">Publicar</button>
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