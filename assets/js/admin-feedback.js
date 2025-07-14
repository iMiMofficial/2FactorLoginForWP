jQuery(document).ready(function($) {
    $('#tflwp-feedback-submit').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $resp = $('#tflwp-feedback-response');
        var name = $('#tflwp_fb_name').val().trim();
        var email = $('#tflwp_fb_email').val().trim();
        var message = $('#tflwp_fb_message').val().trim();
        $resp.html('');
        if (!name || !email || !message) {
            $resp.html('<div class="notice notice-error"><p>All fields are required.</p></div>');
            return;
        }
        var emailPattern = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
        if (!emailPattern.test(email)) {
            $resp.html('<div class="notice notice-error"><p>Please enter a valid email address.</p></div>');
            return;
        }
        $btn.prop('disabled', true).text('Sending...');
        $.post(TwoFactorLoginWPAdmin.ajax_url, {
            action: 'twofactor_feedback',
            nonce: TwoFactorLoginWPAdmin.nonce,
            name: name,
            email: email,
            message: message
        }, function(resp) {
            $btn.prop('disabled', false).text('Send Feedback');
            if (resp.success) {
                $resp.html('<div class="notice notice-success"><p>' + resp.data.message + '</p></div>');
                $('#tflwp_fb_name, #tflwp_fb_email, #tflwp_fb_message').val('');
            } else {
                $resp.html('<div class="notice notice-error"><p>' + (resp.data && resp.data.message ? resp.data.message : 'Submission failed.') + '</p></div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Send Feedback');
            $resp.html('<div class="notice notice-error"><p>Network error. Please try again.</p></div>');
        });
    });
}); 
