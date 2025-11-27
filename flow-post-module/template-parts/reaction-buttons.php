<?php
/**
 * Reaction Buttons Template
 * 
 * Displays like/dislike buttons with counts for a Flow post.
 * 
 * Expected variables:
 * @var int $post_id Flow post ID
 * @var int $like_count Number of likes
 * @var int $dislike_count Number of dislikes
 * @var int|null $user_reaction User's current reaction (1=like, 0=dislike, null=none)
 *
 * @package Flow_Sub
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Determine active states
$like_active = ($user_reaction === 1) ? 'active' : '';
$dislike_active = ($user_reaction === 0) ? 'active' : '';
?>

<div class="flow-reactions" data-post-id="<?php echo esc_attr($post_id); ?>">
    <!-- Like Button -->
    <button class="reaction-btn like-btn <?php echo esc_attr($like_active); ?>" data-reaction="1"
        aria-label="<?php esc_attr_e('Like this post', 'flow-sub'); ?>"
        title="<?php esc_attr_e('Like', 'flow-sub'); ?>">
        <!-- Thumbs Up SVG Icon -->
        <svg class="reaction-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path
                d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3">
            </path>
        </svg>
        <span class="reaction-count like-count"><?php echo esc_html($like_count); ?></span>
    </button>

    <!-- Dislike Button -->
    <button class="reaction-btn dislike-btn <?php echo esc_attr($dislike_active); ?>" data-reaction="0"
        aria-label="<?php esc_attr_e('Dislike this post', 'flow-sub'); ?>"
        title="<?php esc_attr_e('Dislike', 'flow-sub'); ?>">
        <!-- Thumbs Down SVG Icon -->
        <svg class="reaction-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path
                d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17">
            </path>
        </svg>
        <span class="reaction-count dislike-count"><?php echo esc_html($dislike_count); ?></span>
    </button>

    <!-- Loading Spinner (hidden by default) -->
    <div class="reaction-loading" style="display: none;">
        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2" stroke-dasharray="32" stroke-dashoffset="0">
                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s"
                    repeatCount="indefinite" />
            </circle>
        </svg>
    </div>
</div>

<style>
    /* Reaction Buttons Styling */
    .flow-reactions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 0;
        margin-right: 10px;
        position: relative;
    }

    .reaction-btn {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #ffffff;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 11px;
        font-weight: 500;
    }

    .reaction-btn:hover:not(.disabled) {
        background: #f9fafb;
        border-color: #d1d5db;
        transform: translateY(-1px);
    }

    .reaction-btn:active:not(.disabled) {
        transform: translateY(0);
    }

    .reaction-btn.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Active States */
    .like-btn.active {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }

    .like-btn.active .reaction-icon {
        fill: #3b82f6;
        stroke: #1e40af;
    }

    .dislike-btn.active {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }

    .dislike-btn.active .reaction-icon {
        fill: #ef4444;
        stroke: #991b1b;
    }

    /* Icon Styling */
    .reaction-icon {
        width: 14px;
        height: 14px;
        transition: all 0.2s ease;
    }

    /* Count Styling */
    .reaction-count {
        min-width: 16px;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    /* Loading State */
    .reaction-loading {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 8px;
        border-radius: 50%;
    }

    .spinner circle {
        stroke: #3b82f6;
    }

    /* Responsive */
    @media (max-width: 640px) {
        .reaction-btn {
            padding: 6px 10px;
            font-size: 13px;
        }

        .reaction-icon {
            width: 16px;
            height: 16px;
        }
    }
</style>