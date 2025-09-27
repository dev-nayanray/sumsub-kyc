jQuery(document).ready(function($) {
    // Add tooltips to settings
    $('.sumsub-settings input, .sumsub-settings textarea').on('focus', function() {
        $(this).attr('title', 'Enter your custom value here');
    });

    // Confirm before saving settings
    $('.sumsub-settings form').on('submit', function(e) {
        // Optional: Add validation or confirmation
    });

    // Dashboard enhancements
    $('.sumsub-dashboard table').on('click', 'tbody tr', function() {
        $(this).toggleClass('selected');
    });

    // Manual actions AJAX
    $('.sumsub-send-reminder, .sumsub-initiate-kyc, .sumsub-block-user, .sumsub-unblock-user').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var userId = $btn.data('user-id');
        var nonce = $btn.data('nonce');
        var action = '';
        var originalText = '';

        if ($btn.hasClass('sumsub-send-reminder')) {
            action = 'sumsub_send_reminder';
            originalText = 'Send Reminder';
        } else if ($btn.hasClass('sumsub-initiate-kyc')) {
            action = 'sumsub_initiate_kyc';
            originalText = 'Initiate KYC';
        } else if ($btn.hasClass('sumsub-block-user')) {
            action = 'sumsub_block_user';
            originalText = 'Block';
        } else if ($btn.hasClass('sumsub-unblock-user')) {
            action = 'sumsub_unblock_user';
            originalText = 'Unblock';
        }

        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                user_id: userId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    // Optionally reload the page to update the table
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Scan temp emails
    $('.sumsub-scan-temp-emails').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var nonce = $btn.data('nonce');
        var originalText = 'Scan for Temp Emails';

        $btn.prop('disabled', true).text('Scanning...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sumsub_scan_temp_emails',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});
