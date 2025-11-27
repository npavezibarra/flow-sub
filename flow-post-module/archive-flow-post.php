<?php
/**
 * Template Name: Flow Posts Archive Feed
 * Template Post Type: flow-post
 */

// Check if the current user is an administrator (can manage options)
$is_admin = current_user_can('manage_options');

// AJAX Handler for Filter Posts
add_action('wp_ajax_flow_filter_posts', 'flow_filter_posts_ajax');
add_action('wp_ajax_nopriv_flow_filter_posts', 'flow_filter_posts_ajax');

function flow_filter_posts_ajax()
{
    $post_types = isset($_POST['post_types']) ? (array) $_POST['post_types'] : [];
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

    // Build query args
    $args = [
        'post_type' => 'flow-post',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    // Add meta query for post types if specified
    if (!empty($post_types)) {
        $meta_query = ['relation' => 'OR'];

        if (in_array('video', $post_types)) {
            $meta_query[] = [
                'key' => 'flow_post_video_url',
                'compare' => '!=',
                'value' => ''
            ];
        }

        if (in_array('photo', $post_types)) {
            $meta_query[] = [
                'key' => 'flow_post_gallery_ids',
                'compare' => '!=',
                'value' => ''
            ];
        }

        if (in_array('text', $post_types)) {
            $meta_query[] = [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key' => 'flow_post_video_url',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'flow_post_video_url',
                        'value' => '',
                        'compare' => '='
                    ]
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'flow_post_gallery_ids',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'flow_post_gallery_ids',
                        'value' => '',
                        'compare' => '='
                    ]
                ]
            ];
        }

        $args['meta_query'] = $meta_query;
    }

    // Add date query if specified
    if ($date_from || $date_to) {
        $date_query = [];
        if ($date_from) {
            $date_query['after'] = $date_from;
        }
        if ($date_to) {
            $date_query['before'] = $date_to;
            $date_query['inclusive'] = true;
        }
        $args['date_query'] = [$date_query];
    }

    $query = new WP_Query($args);

    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            // Get post data (same as main loop)
            $post_id = get_the_ID();
            $video_url = get_post_meta($post_id, 'flow_post_video_url', true);
            $gallery_ids_string = get_post_meta($post_id, 'flow_post_gallery_ids', true);
            $gallery_ids = array_filter(explode(',', $gallery_ids_string));

            // Determine Post Type
            $is_video_post = !empty($video_url);
            $is_photo_post = !empty($gallery_ids);

            // Build post type array for filtering
            $post_types_attr = [];
            if ($is_video_post)
                $post_types_attr[] = 'video';
            if ($is_photo_post)
                $post_types_attr[] = 'photo';
            if (empty($post_types_attr))
                $post_types_attr[] = 'text';
            $post_type_attr = implode(',', $post_types_attr);
            $post_date = get_the_date('Y-m-d');

            // Author Info
            $author_id = get_the_author_meta('ID');
            $author_name = get_the_author_meta('display_name');
            $avatar_url = get_user_meta($author_id, 'profile_picture', true);
            if (empty($avatar_url)) {
                $avatar_url = get_avatar_url($author_id, ['size' => 80]);
            }
            $time_ago = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago';
            $comment_count = get_comments_number();

            // Check if user is a subscriber
            $current_user = wp_get_current_user();
            $is_subscriber = in_array('flow_subscriber', (array) $current_user->roles) || in_array('administrator', (array) $current_user->roles);

            // Render post card using template
            include(plugin_dir_path(__FILE__) . 'template-parts/post-card.php');
        }
    } else {
        echo '<div class="text-center py-12 text-gray-500">No se encontraron publicaciones que coincidan con los filtros.</div>';
    }

    wp_reset_postdata();
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
    wp_die(); // Required to terminate AJAX request properly
}

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

        /* Admin Form Modal Styles */
        #flow-post-creation-form {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }

        #flow-post-creation-form.form-hidden {
            display: none;
        }

        #flow-post-creation-form .post-card {
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            margin: 0;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        #photo-upload-area.drag-over {
            border-color: #1DA1F2;
            background-color: #E0F2FE;
        }

        /* Sticky header wrapper */
        #global-header-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Reset header positioning so it stacks */
        header {
            position: relative !important;
            width: 100%;
            z-index: 1001;
        }

        /* Add padding to body to prevent content from going under fixed header */
        body {
            padding-top: 144px;
            /* Adjust this value based on your header height */
        }

        /* Responsive styles for sub-header */
        @media (max-width: 1490px) {
            #sub-header>div {
                padding-left: 100px;
                padding-right: 100px;
            }
        }

        @media (max-width: 1080px) {
            #sub-header>div {
                justify-content: center;
            }

            header>div>div {
                justify-content: center;
            }
        }

        /* Force Hamburger Menu at 840px */
        @media (max-width: 840px) {
            .wp-block-navigation__responsive-container-open {
                display: flex !important;
            }

            .wp-block-navigation__responsive-container:not(.is-menu-open) {
                display: none !important;
            }

            .wp-block-navigation__responsive-container.is-menu-open {
                display: block !important;
                width: 100%;
                position: absolute;
                top: 100%;
                left: 0;
                background-color: white;
                padding: 20px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
        }
    </style>
</head>

<?php
// Get background image from settings
$background_image_id = get_option('flow_sub_background_image');
$background_image_url = $background_image_id ? wp_get_attachment_image_url($background_image_id, 'full') : '';
?>

<body <?php body_class('font-sans min-h-screen'); ?> <?php if ($background_image_url): ?>style="background-image: url('<?php echo esc_url($background_image_url); ?>'); background-attachment: fixed; background-size: cover; background-repeat: no-repeat; background-position: center center;"
    <?php endif; ?>>

    <div id="global-header-wrapper">
        <?php
        // Load the WordPress theme header template part
        echo do_blocks('<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->');
        ?>

        <!-- Sub-Header Bar -->
        <div id="sub-header" class="w-full bg-white border-b border-black z-40 shadow-sm h-16">
            <div class="max-w-[1280px] mx-auto h-full flex items-center gap-4">
                <!-- Filter Button -->
                <button id="filter-button"
                    class="bg-black border-2 border-black p-2 rounded hover:bg-gray-800 transition-all duration-300 flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </button>

                <?php if ($is_admin): ?>
                    <!-- Create Post Button -->
                    <button id="toggle-post-form"
                        class="bg-black border-2 border-black p-2 rounded hover:bg-gray-800 transition-all duration-300 flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                <?php endif; ?>

                <!-- Page Title -->
                <h1 class="text-xl font-bold text-gray-900 ml-2">Feed Miembros</h1>
            </div>
        </div>
    </div>

    <!-- Filter Panel (Sliding from Left) -->
    <!-- Adjusted top to 164px (80px header + 20px margin + 64px sub-header) -->
    <div id="filter-panel"
        class="fixed left-0 top-[164px] h-[calc(100vh-10.25rem)] w-80 bg-white shadow-2xl z-30 transform -translate-x-full transition-transform duration-300 ease-in-out border-r border-gray-200">
        <div class="h-full overflow-y-auto" style="padding: 2rem 1.5rem;">
            <!-- Panel Header -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 text-left">Filtros</h2>
            </div>

            <!-- Post Type Filter -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tipo de post</h3>
                <div class="space-y-3">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filter-video"
                            class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                        <span class="ml-3 text-gray-700">Video</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filter-photo"
                            class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                        <span class="ml-3 text-gray-700">Foto</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filter-text"
                            class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                        <span class="ml-3 text-gray-700">Texto</span>
                    </label>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Fecha</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                        <input type="date" id="filter-date-from"
                            class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                        <input type="date" id="filter-date-to"
                            class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-black">
                    </div>
                </div>
            </div>

            <!-- Apply/Clear Buttons -->
            <div class="flex gap-3 mt-6">
                <button id="apply-filters"
                    class="flex-1 bg-black text-white py-2 px-4 rounded-lg font-semibold hover:bg-gray-800 transition-colors">Aplicar</button>
                <button id="clear-filters"
                    class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-semibold hover:bg-gray-300 transition-colors">Limpiar</button>
            </div>
        </div>
    </div>

    <!-- Overlay for filter panel -->
    <div id="filter-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden transition-opacity duration-300"
        style="top: 164px;">
    </div>

    <div class="flex justify-center pb-8 pt-0" style="margin-top: 0px;"> <!-- Added margin-top for sub-header -->
        <div id="main-content-container"
            class="w-full max-w-[592px] min-[1080px]:max-w-[1280px] space-y-8 px-5 min-[1448px]:px-0">
            <!-- Removed H1 Title from here -->

            <?php if ($is_admin): ?>
                <!-- Status Messages -->
                <?php if ($flow_status === 'success'): ?>
                    <div id="success-message"
                        class="max-w-xl mx-auto bg-accent-green text-white p-4 rounded-lg shadow-md text-center font-semibold mb-6 transition-all duration-500">
                        ✅ ¡Publicación Flow creada exitosamente!
                    </div>
                <?php elseif ($flow_status === 'post_error'): ?>
                    <div
                        class="max-w-xl mx-auto bg-accent-red text-white p-4 rounded-lg shadow-md text-center font-semibold mb-6">
                        ❌ Error: No se pudo guardar la publicación Flow.
                    </div>
                <?php endif; ?>

                <!-- Admin Post Creation Form -->
                <div id="flow-post-creation-form" class="form-hidden">
                    <div class="post-card bg-card-bg rounded-xl border border-black overflow-hidden p-6 md:p-8 relative">
                        <div class="flex justify-between items-center mb-6 border-b border-border-light pb-4">
                            <h2 class="text-xl font-bold text-black">Nueva Publicación Flow</h2>
                            <button type="button" id="close-modal-btn" class="text-gray-500 hover:text-black transition-colors">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="flow-post-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"
                            enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="action" value="create_flow_post">
                            <input type="hidden" name="post_id" id="post_id" value="">
                            <?php wp_nonce_field('create_flow_post_action', 'flow_post_nonce'); ?>

                            <div>
                                <label for="post-title" class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                                <input type="text" id="post-title" name="post-title" placeholder="Un título atractivo..."
                                    class="comment-input w-full p-3 border border-gray-300 rounded-lg transition-shadow text-base outline-none shadow-inner"
                                    maxlength="100" required
                                    oninvalid="this.setCustomValidity('Por favor, completa este campo.')"
                                    oninput="this.setCustomValidity('')">
                            </div>

                            <div>
                                <label for="post-text" class="block text-sm font-medium text-gray-700 mb-1">Texto del
                                    Cuerpo (Opcional)</label>
                                <textarea id="post-text" name="post-text" rows="3"
                                    placeholder="Comparte tus pensamientos..."
                                    class="comment-input w-full p-3 border border-gray-300 rounded-lg transition-shadow text-sm outline-none shadow-inner resize-none"></textarea>
                            </div>

                            <div>
                                <label for="video-link" class="block text-sm font-medium text-gray-700 mb-1">Enlace de
                                    Video
                                    de
                                    YouTube (Opcional)</label>
                                <input type="url" id="video-link" name="video-link" placeholder="https://youtu.be/..."
                                    class="comment-input w-full p-3 border border-gray-300 rounded-lg transition-shadow text-sm outline-none shadow-inner">
                            </div>

                            <div>
                                <label for="photo-upload" class="block text-sm font-medium text-gray-700 mb-2">Subir
                                    Fotos
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
                                    class="bg-black text-white py-2 px-5 rounded-md font-semibold text-sm tracking-wider hover:bg-gray-800 transition-colors shadow-lg">Publicar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div id="flow-posts-container" class="grid grid-cols-1 min-[1080px]:grid-cols-2 gap-8 pt-[20px]">
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

                        // Build post type array for filtering
                        $post_types = [];
                        if ($is_video_post)
                            $post_types[] = 'video';
                        if ($is_photo_post)
                            $post_types[] = 'photo';
                        if (empty($post_types))
                            $post_types[] = 'text';
                        $post_type_attr = implode(',', $post_types);
                        $post_date = get_the_date('Y-m-d');

                        // Author Info
                        $author_id = get_the_author_meta('ID');
                        $author_name = get_the_author_meta('display_name');
                        // Get custom profile picture or fallback to WordPress avatar
                        $avatar_url = get_user_meta($author_id, 'profile_picture', true);
                        if (empty($avatar_url)) {
                            $avatar_url = get_avatar_url($author_id, ['size' => 80]);
                        }
                        $time_ago = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago';
                        $comment_count = get_comments_number();

                        // Check if user is a subscriber (soft paywall logic)
                        $current_user = wp_get_current_user();
                        $is_subscriber = in_array('flow_subscriber', (array) $current_user->roles) || in_array('administrator', (array) $current_user->roles);
                        ?>

                        <?php
                        // Include post card template
                        include(plugin_dir_path(__FILE__) . 'template-parts/post-card.php');
                        ?>



                    <?php endwhile; ?>

                    <!-- Pagination -->
                    <div class="flex justify-center mt-8">
                        <?php the_posts_pagination(['prev_text' => '« Anterior', 'next_text' => 'Siguiente »', 'class' => 'pagination']); ?>
                    </div>

                <?php else: ?>
                    <p class="text-center text-gray-500 py-12">No hay publicaciones disponibles.</p>
                <?php endif; ?>
            </div> <!-- End #flow-posts-container -->
        </div>
    </div>

    <script>
        // Simple function for the "alert" box instead of window.alert                   ()
        function alert(message) {
            const container = document.getElementById('main-content-container');
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
            const closeModalBtn = document.getElementById('close-modal-btn');

            if (toggleButton && formContainer) {
                // Open Modal
                toggleButton.addEventListener('click', () => {
                    formContainer.classList.remove('form-hidden');
                    
                    // Reset form for new post
                    const form = document.getElementById('flow-post-form');
                    const formTitle = formContainer.querySelector('h2');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const postIdInput = document.getElementById('post_id');
                    
                    if (form) form.reset();
                    if (formTitle) formTitle.textContent = 'Nueva Publicación Flow';
                    if (submitBtn) submitBtn.textContent = 'Publicar';
                    if (postIdInput) postIdInput.value = '';
                    document.getElementById('file-list').innerHTML = '';
                });

                // Close Modal Function
                const closeFlowModal = () => {
                    formContainer.classList.add('form-hidden');
                };

                // Close on X button
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeFlowModal);
                }

                // Close on backdrop click
                formContainer.addEventListener('click', (e) => {
                    if (e.target === formContainer) {
                        closeFlowModal();
                    }
                });
                
                // Close on Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && !formContainer.classList.contains('form-hidden')) {
                        closeFlowModal();
                    }
                });
            }

                // Edit Post Logic
                document.addEventListener('click', function (e) {
                    const editBtn = e.target.closest('.edit-flow-post-btn');
                    if (editBtn) {
                        e.preventDefault();

                        const postId = editBtn.dataset.postId;
                        const title = editBtn.dataset.title;
                        const content = editBtn.dataset.content;
                        const videoUrl = editBtn.dataset.videoUrl;

                        // Populate form
                        const form = document.getElementById('flow-post-form');
                        const titleInput = document.getElementById('post-title');
                        const textInput = document.getElementById('post-text');
                        const videoInput = document.getElementById('video-link');
                        const postIdInput = document.getElementById('post_id');
                        const formTitle = formContainer.querySelector('h2');
                        const submitBtn = form.querySelector('button[type="submit"]');

                        if (formContainer && form) {
                            // Open form if hidden
                            if (formContainer.classList.contains('form-hidden')) {
                                formContainer.classList.remove('form-hidden');
                            }
                            
                            // Set values
                            if(postIdInput) postIdInput.value = postId;
                            if(titleInput) titleInput.value = title;
                            if(textInput) textInput.value = content;
                            if(videoInput) videoInput.value = videoUrl;
                            
                            // Update UI
                            if(formTitle) formTitle.textContent = 'Editar Publicación Flow';
                            if(submitBtn) submitBtn.textContent = 'Actualizar';
                            
                            // No need to scroll, it's a modal now
                        }
                    }
                });
            

            // Filter Panel Toggle Logic
            const filterButton = document.getElementById('filter-button');
            const filterPanel = document.getElementById('filter-panel');
            const filterOverlay = document.getElementById('filter-overlay');

            if (filterButton && filterPanel && filterOverlay) {
                const toggleFilterPanel = () => {
                    const isOpen = !filterPanel.classList.contains('-translate-x-full');

                    if (isOpen) {
                        // Close panel
                        filterPanel.classList.add('-translate-x-full');
                        filterOverlay.classList.add('hidden');
                        // No need to move buttons anymore
                    } else {
                        // Open panel
                        filterPanel.classList.remove('-translate-x-full');
                        filterOverlay.classList.remove('hidden');
                        // No need to move buttons anymore
                    }
                };

                filterButton.addEventListener('click', toggleFilterPanel);
                filterOverlay.addEventListener('click', toggleFilterPanel);
            }

            // Filter Logic
            const applyFiltersBtn = document.getElementById('apply-filters');
            const clearFiltersBtn = document.getElementById('clear-filters');
            const videoCheckbox = document.getElementById('filter-video');
            const photoCheckbox = document.getElementById('filter-photo');
            const textCheckbox = document.getElementById('filter-text');
            const dateFromInput = document.getElementById('filter-date-from');
            const dateToInput = document.getElementById('filter-date-to');

            console.log('Filter buttons found:', {
                applyBtn: !!applyFiltersBtn,
                clearBtn: !!clearFiltersBtn,
                videoCheck: !!videoCheckbox,
                photoCheck: !!photoCheckbox,
                textCheck: !!textCheckbox
            });

            async function applyFilters() {
                // Collect filter values
                const postTypes = [];
                if (videoCheckbox?.checked) postTypes.push('video');
                if (photoCheckbox?.checked) postTypes.push('photo');
                if (textCheckbox?.checked) postTypes.push('text');

                const dateFrom = dateFromInput?.value || '';
                const dateTo = dateToInput?.value || '';

                // Get posts container
                const postsContainer = document.getElementById('flow-posts-container');
                if (!postsContainer) return;

                // Show loading indicator
                postsContainer.innerHTML = '<div class="text-center py-12"><div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-blue"></div><p class="mt-4 text-gray-600">Cargando...</p></div>';

                try {
                    // Make AJAX request
                    console.log('Sending filter request...');

                    // Build form data properly for arrays
                    const formData = new URLSearchParams();
                    formData.append('action', 'flow_filter_posts');

                    // Append each post type individually
                    postTypes.forEach(type => {
                        formData.append('post_types[]', type);
                    });

                    formData.append('date_from', dateFrom);
                    formData.append('date_to', dateTo);

                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });

                    console.log('Response status:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success) {
                        postsContainer.innerHTML = data.data.html;
                    } else {
                        console.error('AJAX error:', data);
                        postsContainer.innerHTML = '<div class="text-center py-12 text-red-500">Error al cargar los posts.</div>';
                    }
                } catch (error) {
                    console.error('Filter error:', error);
                    postsContainer.innerHTML = '<div class="text-center py-12 text-red-500">Error: ' + error.message + '</div>';
                }
            }

            async function clearFilters() {
                // Reset checkboxes and inputs
                if (videoCheckbox) videoCheckbox.checked = false;
                if (photoCheckbox) photoCheckbox.checked = false;
                if (textCheckbox) textCheckbox.checked = false;
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';

                // Reload all posts (no filters)
                await applyFilters();
            }

            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', applyFilters);
            }

            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', clearFilters);
            }

            // Photo Upload Logic (Simplified)
            const photoUploadArea = document.getElementById('photo-upload-area');
            const photoUploadInput = document.getElementById('photo-upload');
            const fileList = document.getElementById('file-list');

            if (photoUploadArea && photoUploadInput) {
                // Click to upload
                photoUploadArea.addEventListener('click', () => photoUploadInput.click());

                // Handle file selection
                photoUploadInput.addEventListener('change', (e) => {
                    fileList.innerHTML = '';
                    Array.from(e.target.files).slice(0, 4).forEach(file => {
                        const item = document.createElement('p');
                        item.className = 'text-sm text-gray-700';
                        item.textContent = `📷 ${file.name}`;
                        fileList.appendChild(item);
                    });
                });

                // Drag and drop handlers
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    photoUploadArea.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                // Highlight drop area when dragging over it
                ['dragenter', 'dragover'].forEach(eventName => {
                    photoUploadArea.addEventListener(eventName, () => {
                        photoUploadArea.classList.add('drag-over');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    photoUploadArea.addEventListener(eventName, () => {
                        photoUploadArea.classList.remove('drag-over');
                    }, false);
                });

                // Handle dropped files
                photoUploadArea.addEventListener('drop', (e) => {
                    const dt = e.dataTransfer;
                    const files = dt.files;

                    // Update the file input with dropped files
                    photoUploadInput.files = files;

                    // Display file names
                    fileList.innerHTML = '';
                    Array.from(files).slice(0, 4).forEach(file => {
                        const item = document.createElement('p');
                        item.className = 'text-sm text-gray-700';
                        item.textContent = `📷 ${file.name}`;
                        fileList.appendChild(item);
                    });
                }, false);
            }

            // AJAX Comment Submission
            document.querySelectorAll('.flow-comment-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const commentInput = form.querySelector('input[name="comment"]');
                    const submitButton = form.querySelector('button[type="submit"]');
                    const postId = formData.get('comment_post_ID');
                    const commentText = formData.get('comment');

                    // Disable form during submission
                    submitButton.disabled = true;
                    submitButton.textContent = 'Publicando...';

                    try {
                        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'submit_flow_comment',
                                comment_post_ID: postId,
                                comment: commentText,
                                nonce: '<?php echo wp_create_nonce('flow_comment_nonce'); ?>'
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            // Clear the input
                            commentInput.value = '';

                            // Find the discussion section for this post
                            const discussionSection = form.closest('.post-card').querySelector('.space-y-4');
                            const noCommentsMsg = discussionSection.querySelector('.italic');

                            // Remove "no comments" message if it exists
                            if (noCommentsMsg) {
                                noCommentsMsg.remove();
                            }

                            // Add the new comment to the top
                            const commentHTML = `
                                <div class="flex space-x-3">
                                    <img class="w-8 h-8 rounded-full object-cover shrink-0"
                                        src="${result.data.avatar}" alt="Commenter Avatar">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            ${result.data.author} <span class="text-xs font-normal text-gray-500 ml-1">· hace unos segundos</span>
                                        </p>
                                        <p class="text-sm text-gray-700">${result.data.comment_text}</p>
                                    </div>
                                </div>
                            `;

                            // Insert after the h3
                            const h3 = discussionSection.querySelector('h3');
                            h3.insertAdjacentHTML('afterend', commentHTML);

                            // Update comment count
                            const commentCountSpan = form.closest('.post-card').querySelector('.text-sm.text-gray-500.cursor-pointer');
                            if (commentCountSpan) {
                                const currentCount = parseInt(commentCountSpan.textContent.match(/\d+/)[0]);
                                commentCountSpan.innerHTML = `${currentCount + 1} Comentarios`;
                            }

                        } else {
                            alert('Error al publicar el comentario: ' + (result.data || 'Error desconocido'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error al publicar el comentario. Por favor, intenta de nuevo.');
                    } finally {
                        // Re-enable form
                        submitButton.disabled = false;
                        submitButton.textContent = 'Publicar';
                    }
                });
            });

            // Auto-fade success message after 5 seconds
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                setTimeout(() => {
                    // Start fade out
                    successMessage.style.opacity = '0';
                    successMessage.style.maxHeight = successMessage.offsetHeight + 'px';

                    // After fade completes, collapse the element
                    setTimeout(() => {
                        successMessage.style.maxHeight = '0';
                        successMessage.style.marginBottom = '0';
                        successMessage.style.paddingTop = '0';
                        successMessage.style.paddingBottom = '0';

                        // Remove from DOM after collapse animation
                        setTimeout(() => {
                            successMessage.remove();
                        }, 500); // Match transition duration
                    }, 500); // Match transition duration
                }, 5000); // Wait 5 seconds before starting fade
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