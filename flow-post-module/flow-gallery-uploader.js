jQuery(document).ready(function($) {
    'use strict';

    var flowMediaFrame;
    var $galleryIdsInput = $('#flow_post_gallery_ids');
    var $galleryList = $('.flow-gallery-list');
    var $clearButton = $('#flow-gallery-clear-button');
    var maxImages = 4;

    // --- 1. Handle Gallery Upload Button Click ---
    $('#flow-gallery-upload-button').on('click', function(e) {
        e.preventDefault();

        // If the frame exists, reopen it
        if (flowMediaFrame) {
            flowMediaFrame.open();
            return;
        }

        // Create a new media frame instance
        flowMediaFrame = wp.media({
            title: 'Select/Add Flow Post Gallery Images',
            button: {
                text: 'Use Selected Images'
            },
            library: {
                type: 'image'
            },
            multiple: true // Allow selection of multiple images
        });

        // Runs when an image is selected
        flowMediaFrame.on('select', function() {
            var selection = flowMediaFrame.state().get('selection');
            var ids = [];
            
            selection.map(function(attachment) {
                attachment = attachment.toJSON();
                if (ids.length < maxImages) {
                    ids.push(attachment.id);
                }
            });
            
            // Merge with existing IDs if not replacing entirely
            var existingIds = $galleryIdsInput.val().split(',').filter(Boolean).map(Number);
            var uniqueIds = [...new Set([...existingIds, ...ids])]; // Get unique IDs
            
            // Limit to maxImages
            uniqueIds = uniqueIds.slice(0, maxImages);

            // Update the hidden input and display list
            updateGallery(uniqueIds);
        });

        // Open the media library frame
        flowMediaFrame.open();
    });

    // --- 2. Handle Remove Image Click ---
    $galleryList.on('click', '.flow-gallery-remove', function(e) {
        e.preventDefault();
        var $li = $(this).closest('li');
        var removedId = $li.data('id');
        
        var currentIds = $galleryIdsInput.val().split(',').filter(Boolean).map(Number);
        
        // Remove the ID of the clicked image
        var updatedIds = currentIds.filter(id => id !== removedId);
        
        updateGallery(updatedIds);
    });
    
    // --- 3. Handle Clear Gallery Click ---
    $clearButton.on('click', function(e) {
        e.preventDefault();
        updateGallery([]); // Clear all IDs
    });

    // --- 4. Function to Update Hidden Input and Visual List ---
    function updateGallery(ids) {
        // Update hidden input
        $galleryIdsInput.val(ids.join(','));

        // Update visual list
        $galleryList.empty();
        
        if (ids.length > 0) {
            ids.forEach(function(id) {
                // Fetch image details via AJAX to display thumbnail (optional, but good UX)
                // For simplicity, we'll rely on the existing image structure or reload on save.
                // A better approach would be to use AJAX to fetch the thumbnails instantly.
                // For now, we'll use a placeholder and rely on the full thumbnail being available after a save/reload.
                $galleryList.append(
                    '<li data-id="' + id + '" style="position: relative;">' +
                        '<img src="' + $('#post-thumbnail-' + id + ' img').attr('src') + '" onerror="this.src=\'https://placehold.co/80x80/EEEEEE/333333?text=ID:' + id + '\';" style="width: 80px; height: 80px; object-fit: cover; border: 1px solid #ddd;"/>' +
                        '<a href="#" class="flow-gallery-remove" style="position: absolute; top: -5px; right: -5px; background: #dc3232; color: #fff; border-radius: 50%; width: 18px; height: 18px; text-align: center; line-height: 16px; font-size: 10px; text-decoration: none;">&times;</a>' +
                    '</li>'
                );
            });
            $clearButton.show();
        } else {
            $clearButton.hide();
        }
    }
});
