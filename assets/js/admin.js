jQuery(document).ready(function ($) {
    var mediaUploader;

    // Upload image button click
    $('#upload_image_button').on('click', function (e) {
        e.preventDefault();

        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Seleccionar Imagen de Fondo',
            button: {
                text: 'Usar esta imagen'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#flow_sub_background_image').val(attachment.id);
            $('#image-preview').attr('src', attachment.url).show();
            $('#remove_image_button').show();
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    // Remove image button click
    $('#remove_image_button').on('click', function (e) {
        e.preventDefault();
        $('#flow_sub_background_image').val('');
        $('#image-preview').attr('src', '').hide();
        $(this).hide();
    });
});
