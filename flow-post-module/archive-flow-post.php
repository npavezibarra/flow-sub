<?php
/**
 * Template Name: Flow Posts Archive Feed
 * Template Post Type: flow-post
 */

// Check if the current user is an administrator (can manage options)
$is_admin = current_user_can('manage_options');

// Get the submission status for displaying messages (from the redirect)
$flow_status = isset($_GET['flow_status']) ? sanitize_key($_GET['flow_status']) : '';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>

    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Configure Tailwind for custom colors and Inter font -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#1DA1F2',
                        'card-bg': '#FFFFFF',
                        'light-gray': '#EFF3F4',
                        'border-light': '#E5E7EB',
                        'accent-red': '#EF4444',
                        'accent-green': '#10B981',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        /* Custom styles for media placeholder and feed layout */
        body {
            background-color: #f7f9f9;
        }

        .post-card {
            box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.05);
            /* Softer, elevated shadow */
        }

        /* Video/Media Placeholder styles */
        .media-placeholder {
            position: relative;
            padding-bottom: 56.25%;
            /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            background: #1f2937;
        }

        .media-placeholder iframe,
        .media-placeholder object,
        .media-placeholder embed {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .media-placeholder img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.8;
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 72px;
            height: 72px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
            z-index: 10;
        }

        .play-button:hover {
            transform: translate(-50%, -50%) scale(1.05);
        }

        .play-triangle {
            width: 0;
            height: 0;
            border-left: 20px solid #1DA1F2;
            border-top: 12px solid transparent;
            border-bottom: 12px solid transparent;
            margin-left: 6px;
        }

        /* Style for the comment input focus */
        .comment-input:focus {
            box-shadow: 0 0 0 3px rgba(29, 161, 242, 0.5);
        }

        /* Admin Form Styles */
        .form-hidden {
            display: none;
        }

        #photo-upload-area.drag-over {
            border-color: #1DA1F2;
            background-color: #E0F2FE;
        }

        /* Sticky header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Add padding to body to prevent content from going under fixed header */
        body {
            padding-top: 80px;
            /* Adjust this value based on your header height */
        }
    </style>
</head>

<body <?php body_class('font-sans min-h-screen'); ?>>

    <?php
    // Load the WordPress theme header template part
    echo do_blocks('<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->');
    ?>

    <div class="flex justify-center py-8">
        <div class="w-full max-w-xl space-y-8 px-4 sm:px-0">
            <h1 class="text-3xl font-extrabold text-gray-900 mb-8 text-center">Feed de Contenido Flow</h1>

            <?php if ($is_admin): ?>
                <!-- Admin Post Creation Toggle -->
                <div class="flex justify-end mb-6">
                    <button id="toggle-post-form"
                        class="bg-primary-blue text-white p-3 px-6 rounded-xl font-bold hover:bg-opacity-90 transition-opacity flex items-center shadow-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Crear Publicación
                    </button>
                </div>

                <!-- Status Messages -->
                <?php if ($flow_status === 'success'): ?>
                    <div class="bg-accent-green text-white p-4 rounded-lg shadow-md text-center font-semibold mb-6">
                        ✅ ¡Publicación Flow creada exitosamente!
                    </div>
                <?php elseif ($flow_status === 'post_error'): ?>
                    <div class="bg-accent-red text-white p-4 rounded-lg shadow-md text-center font-semibold mb-6">
                        ❌ Error: No se pudo guardar la publicación Flow.
                    </div>
                <?php endif; ?>

                <!-- Admin Post Creation Form -->
                <div id="flow-post-creation-form" class="form-hidden mb-8">
                    <div class="post-card bg-card-bg rounded-xl border border-primary-blue overflow-hidden p-6 md:p-8">
                        <h2 class="text-xl font-bold text-primary-blue mb-6 border-b border-border-light pb-4">Nueva
                            Publicación Flow
                        </h2>
                        <form id="flow-post-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"
                            enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="action" value="create_flow_post">
                            <?php wp_nonce_field('create_flow_post_action', 'flow_post_nonce'); ?>

                            <div>
                                <label for="post-title" class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                                <input type="text" id="post-title" name="post-title" placeholder="Un título atractivo..."
                                    class="comment-input w-full p-3 border border-gray-300 rounded-lg transition-shadow text-base outline-none shadow-inner"
                                    maxlength="100" required>
                            </div>

                            <div>
                                <label for="post-text" class="block text-sm font-medium text-gray-700 mb-1">Texto del
                                    Cuerpo</label>
                                <textarea id="post-text" name="post-text" rows="3"
                                    placeholder="Comparte tus pensamientos..."
                                    class="comment-input w-full p-3 border border-gray-300 rounded-lg transition-shadow text-sm outline-none shadow-inner resize-none"
                                    required></textarea>
                            </div>

                            <div>
                                <label for="video-link" class="block text-sm font-medium text-gray-700 mb-1">Enlace de Video
                                    de
                                    YouTube (Opcional)</label>
                                <input type="url" id="video-link" name="video-link" placeholder="https://youtu.be/..."
                                    class="comment-input w-full p-3 border border-gray-300 rounded-lg transition-shadow text-sm outline-none shadow-inner">
                            </div>

                            <div>
                                <label for="photo-upload" class="block text-sm font-medium text-gray-700 mb-2">Subir Fotos
                                    (Opcional)</label>
                                <div id="photo-upload-area"
                                    class="flex flex-col justify-center items-center h-32 border-2 border-dashed border-border-light rounded-lg bg-light-gray transition-colors cursor-pointer p-4 hover:bg-gray-100">
                                    <input type="file" id="photo-upload" name="photo-upload[]" accept="image/*" multiple
                                        class="hidden">
                                    <svg class="mx-auto h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-12 5h8a2 2 0 002-2v-7a2 2 0 00-2-2H8a2 2 0 00-2 2v7a2 2 0 002 2z" />
                                    </svg>
                                    <p class="mt-1 text-sm text-gray-600 font-medium">Haz clic para buscar o arrastra
                                        imágenes</p>
                                </div>
                                <div id="file-list" class="mt-2 text-sm text-gray-600 space-y-1"></div>
                            </div>

                            <div class="pt-4 flex justify-end">
                                <button type="submit"
                                    class="bg-primary-blue text-white p-3 px-6 rounded-xl font-bold uppercase tracking-wider hover:bg-opacity-90 transition-opacity shadow-lg">Publicar
                                    Flow</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (have_posts()): ?>
                <?php while (have_posts()):
                    the_post();
                    $post_id = get_the_ID();
                    $video_url = get_post_meta($post_id, 'flow_post_video_url', true);
                    $gallery_ids_string = get_post_meta($post_id, 'flow_post_gallery_ids', true);
                    $gallery_ids = array_filter(explode(',', $gallery_ids_string));

                    // Determine Post Type
                    $is_video_post = !empty($video_url);
                    $is_photo_post = !empty($gallery_ids);

                    // Author Info
                    $author_id = get_the_author_meta('ID');
                    $author_name = get_the_author_meta('display_name');
                    $avatar_url = get_avatar_url($author_id, ['size' => 80]);
                    $time_ago = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago';
                    $comment_count = get_comments_number();

                    // Check if user is a subscriber (soft paywall logic)
                    $current_user = wp_get_current_user();
                    $is_subscriber = in_array('flow_subscriber', (array) $current_user->roles) || in_array('administrator', (array) $current_user->roles);
                    ?>

                    <!-- POST CARD -->
                    <div class="post-card bg-card-bg rounded-xl border border-border-light overflow-hidden">

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
                            <h2 class="text-2xl font-extrabold text-gray-900 mb-2">
                                <?php the_title(); ?>
                            </h2>

                            <!-- Author & Date -->
                            <div class="flex items-center text-sm text-gray-500 mb-4">
                                <img class="w-7 h-7 rounded-full object-cover mr-2" src="<?php echo esc_url($avatar_url); ?>"
                                    alt="Avatar">
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
                                    <span
                                        class="text-sm text-gray-500 cursor-pointer hover:text-primary-blue"><?php echo $comment_count; ?>
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
                                        $c_avatar = get_avatar_url($comment->comment_author_email, ['size' => 40]);
                                        ?>
                                        <div class="flex space-x-3">
                                            <img class="w-8 h-8 rounded-full object-cover shrink-0"
                                                src="<?php echo esc_url($c_avatar); ?>" alt="Commenter Avatar">
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
                                    <div class="flex items-start space-x-3">
                                        <?php $current_user_avatar = get_avatar_url(get_current_user_id(), ['size' => 40]); ?>
                                        <img class="w-10 h-10 rounded-full object-cover shrink-0"
                                            src="<?php echo esc_url($current_user_avatar); ?>" alt="My Avatar">
                                        <form action="<?php echo esc_url(site_url('/wp-comments-post.php')); ?>" method="post"
                                            class="flex-grow flex space-x-2">
                                            <input type="hidden" name="comment_post_ID" value="<?php echo $post_id; ?>" />
                                            <input type="text" name="comment" placeholder="Únete a la discusión..."
                                                class="comment-input flex-grow p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-blue focus:border-primary-blue transition-shadow shadow-inner text-sm outline-none"
                                                required>
                                            <button type="submit"
                                                class="bg-primary-blue text-white p-3 rounded-xl font-semibold hover:bg-opacity-90 transition-opacity">Publicar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Non-subscribers see a message -->
                                <div class="mt-6 pt-4 border-t border-border-light">
                                    <p class="text-center text-gray-500 italic py-4">
                                        <a href="<?php echo home_url('/membership-signup/'); ?>" class="text-primary-blue hover:underline font-semibold">Suscríbete</a> para unirte a la discusión
                                    </p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <!-- End Post Card -->

                    <div class="h-8"></div> <!-- Spacer -->

                <?php endwhile; ?>

                <!-- Pagination -->
                <div class="flex justify-center mt-8">
                    <?php the_posts_pagination(['prev_text' => '« Anterior', 'next_text' => 'Siguiente »', 'class' => 'pagination']); ?>
                </div>

            <?php else: ?>
                <p class="text-center text-gray-500">No Flow Posts published yet!</p>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // Simple function for the "alert" box instead of window.alert()
        function alert(message) {
            const container = document.querySelector('.w-full.max-w-xl');
            let alertBox = document.getElementById('custom-alert');

            if (!alertBox) {
                alertBox = document.createElement('div');
                alertBox.id = 'custom-alert';
                alertBox.className = 'fixed inset-x-0 bottom-0 mx-auto max-w-sm bg-primary-blue text-white p-4 mb-4 rounded-lg shadow-2xl text-center z-50 transition-transform duration-300 transform translate-y-full';
                container.appendChild(alertBox);
            }

            alertBox.textContent = message;
            // Show the box
            alertBox.style.transform = 'translateY(0)';

            // Hide the box after 3 seconds
            setTimeout(() => {
                alertBox.style.transform = 'translateY(150%)';
            }, 3000);
        }

        // We override the native window.alert to use our custom function
        window.alert = alert;

        // Admin Form Toggle Logic
        document.addEventListener('DOMContentLoaded', () => {
            const toggleButton = document.getElementById('toggle-post-form');
            const formContainer = document.getElementById('flow-post-creation-form');

            if (toggleButton && formContainer) {
                toggleButton.addEventListener('click', () => {
                    formContainer.classList.toggle('form-hidden');
                    const isHidden = formContainer.classList.contains('form-hidden');
                    toggleButton.innerHTML = isHidden
                        ? '<svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>Create Post'
                        : '<svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>Hide Form';
                });
            }

            // Photo Upload Logic (Simplified)
            const photoUploadArea = document.getElementById('photo-upload-area');
            const photoUploadInput = document.getElementById('photo-upload');
            const fileList = document.getElementById('file-list');

            if (photoUploadArea && photoUploadInput) {
                photoUploadArea.addEventListener('click', () => photoUploadInput.click());

                photoUploadInput.addEventListener('change', (e) => {
                    fileList.innerHTML = '';
                    Array.from(e.target.files).slice(0, 4).forEach(file => {
                        const item = document.createElement('p');
                        item.className = 'text-sm text-gray-700';
                        item.textContent = `📷 ${file.name}`;
                        fileList.appendChild(item);
                    });
                });
            }
        });
    </script>

    <?php
    // Load the WordPress theme footer template part
    echo do_blocks('<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->');

    wp_footer();
    ?>
</body>

</html>