jQuery(document).ready(function($) {

    // Open Add modal
    $('#add-new-tour-btn').click(function() {
        $('#add-tour-modal').show();
        $('#tour-title').val('').focus();
        $('body').css('overflow', 'hidden');
    });

    // Open Edit modal
    $(document).on('click', '.edit-tour', function(e) {
        e.preventDefault();
        $('#edit-tour-id').val($(this).data('id'));
        $('#edit-tour-title').val($(this).data('title'));
        $('#edit-tour-modal').show();
        $('#edit-tour-title').focus();
        $('body').css('overflow', 'hidden');
    });

    // Close modals (Cancel buttons)
    $(document).on('click', '.close-modal, .button.close-modal', function() {
        $('.virtual-tour-modal').hide();
        $('body').css('overflow', 'auto');
    });

    // Click overlay to close
    $(window).on('click', function(event) {
        if ($(event.target).is('.virtual-tour-modal')) {
            $('.virtual-tour-modal').hide();
            $('body').css('overflow', 'auto');
        }
    });

    // Add tour submit
    $('#add-tour-form').on('submit', function(e) {
        e.preventDefault();
        var tourTitle = $('#tour-title').val().trim();
        if (!tourTitle) { 
            alert('Please enter a tour title'); 
            return; 
        }

        var submitBtn = $('.vtt-btn--add');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Adding...');

        $.ajax({
            url: webronic_virtual_tour_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'webronic_save_tour',
                tour_title: tourTitle,
                security: webronic_virtual_tour_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#add-tour-modal').hide();
                    $('body').css('overflow', 'auto');
                    $('#add-tour-form')[0].reset();
                    $('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'An error occurred: ' + error;
                alert(errorMessage);
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Edit tour submit
    $('#edit-tour-form').on('submit', function(e) {
        e.preventDefault();
        var tourId = $('#edit-tour-id').val();
        var tourTitle = $('#edit-tour-title').val().trim();
        if (!tourTitle) { 
            alert('Please enter a tour title'); 
            return; 
        }

        var submitBtn = $('.vtt-btn--save').not('.vtt-btn--add');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: webronic_virtual_tour_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'webronic_update_tour',
                tour_id: tourId,
                tour_title: tourTitle,
                security: webronic_virtual_tour_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred: ' + error);
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Copy shortcode functionality
    $(document).on('click', '.copy-shortcode', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $container = $button.closest('.shortcode-container');
        var $input = $button.siblings('.shortcode-input');
        var shortcode = $input.val();
        
        // Create a temporary input element
        var $tempInput = $('<textarea>');
        $('body').append($tempInput);
        $tempInput.val(shortcode).select();
        
        try {
            // Execute copy command
            var successful = document.execCommand('copy');
            if (successful) {
                // Show success feedback
                $button.addClass('copied');
                $button.find('.copy-icon').css('filter', 'brightness(0) invert(1)');
                
                // Remove any existing success message
                $container.find('.copy-success-message').remove();
                
                // Show success message under the embed code
                var $successMessage = $('<div class="copy-success-message">Shortcode copied to clipboard!</div>');
                $container.append($successMessage);
                
                // Remove message after 2 seconds
                setTimeout(function() {
                    $successMessage.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 2000);
                
                // Reset button after 2 seconds
                setTimeout(function() {
                    $button.removeClass('copied');
                    $button.find('.copy-icon').css('filter', '');
                }, 2000);
            } else {
                // Fallback: select the input and show message
                $input.select();
                showNotification('Press Ctrl+C to copy the shortcode');
            }
        } catch (err) {
            // Fallback for older browsers
            $input.select();
            showNotification('Press Ctrl+C to copy the shortcode');
        }
        
        // Remove temporary input
        $tempInput.remove();
    });

    // Delete confirm - Fixed to work with both text links and icon buttons
    $(document).on('click', '.submitdelete', function(e) {
        if (!confirm('Are you sure you want to delete this tour? All scenes and hotspots will also be deleted.')) {
            e.preventDefault();
            return false;
        }
    });

    // Prevent modal content click from closing modal
    $(document).on('click', '.virtual-tour-modal-content', function(e) {
        e.stopPropagation();
    });

    // Function to show notification
    function showNotification(message) {
        // Remove existing notifications
        $('.webronic-notice').remove();
        
        // Create new notification
        var $notice = $('<div class="webronic-notice">' + message + '</div>');
        $('body').append($notice);
        
        // Remove notification after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Handle window resize for responsive table
    $(window).on('resize', function() {
        adjustTableLayout();
    });

    // Initial table layout adjustment
    adjustTableLayout();

    function adjustTableLayout() {
        var windowWidth = $(window).width();
        var $table = $('.webronic-tours-table');
        
        if (windowWidth < 1200) {
            $table.addClass('responsive-mode');
        } else {
            $table.removeClass('responsive-mode');
        }
    }
});