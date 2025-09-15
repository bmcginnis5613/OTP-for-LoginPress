jQuery(document).ready(function($) {
    console.log('LoginPress OTP script loaded');
    
    var regularLogin = $('#loginform').children('p').not('#otp-auth-container').not(':has(#toggle-otp-login)');
    
    $('#toggle-otp-login').click(function(e) {
        e.preventDefault();
        $('body').addClass('otp-login-active');
        regularLogin.hide();
        $('#otp-auth-container').show();
        $('#back-to-regular').show();
        $(this).hide();

        // Reset OTP UI state
        $('#otp_email').val('').prop('disabled', false);
        $('#send-otp-btn').show();
        $('#resend-otp-btn').hide().removeClass('button-primary').addClass('button');
        $('#otp-code-section').hide();
        $('#otp-messages').empty();
    });
    
    $('#toggle-regular-login').click(function(e) {
        e.preventDefault();
        $('body').removeClass('otp-login-active');
        $('#otp-auth-container').hide();
        $('#otp-code-section').hide();
        regularLogin.show();
        $('#back-to-regular').hide();
        $('#toggle-otp-login').show();
        $('#otp-messages').empty();

        // Reset OTP UI state
        $('#otp_email').val('');
        $('#otp_email').val('').prop('disabled', false);
        $('#resend-otp-btn').hide().removeClass('button-primary').addClass('button');
        $('#send-otp-btn').parent('p').show();
    });
    
    $('#send-otp-btn').click(function() {
        var email = $('#otp_email').val();
        var button = $(this);
        
        if (!email) {
            showMessage('Please enter your email address.', 'error');
            return;
        }
        
        button.prop('disabled', true).val('Sending...');
        
        $.post(loginpress_otp_ajax.ajax_url, {
            action: 'send_otp_code',
            nonce: loginpress_otp_ajax.nonce,
            email: email
        }, function(response) {
            if (response.success) {
                showMessage(response.data.message, 'success');
                $('#otp_email').prop('disabled', true);
                $('#otp-code-section').show();
                $('#otp_code').focus();

                // Hide Send Code button
                $('#send-otp-btn').parent('p').hide();
                $('#resend-otp-btn')
                    .removeClass('button')
                    .addClass('button-primary')
                    .show();
            } else {
                showMessage(response.data.message, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).val('Send Code');
        });
    });
    
    $('#resend-otp-btn').click(function() {
        $('#send-otp-btn').click();
    });
    
    $('#verify-otp-btn').click(function() {
        var email = $('#otp_email').val();
        var code = $('#otp_code').val();
        var button = $(this);
        
        if (!code) {
            showMessage('Please enter the 6-digit passcode that was sent to your email.', 'error');
            return;
        }
        
        button.prop('disabled', true).val('Verifying...');
        
        $.post(loginpress_otp_ajax.ajax_url, {
            action: 'verify_otp_code',
            nonce: loginpress_otp_ajax.nonce,
            email: email,
            code: code
        }, function(response) {
            if (response.success) {
                showMessage(response.data.message, 'success');
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            } else {
                showMessage(response.data.message, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).val('Verify & Login');
        });
    });
    
    $('#otp_email').keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#send-otp-btn').click();
        }
    });

    $('#otp_code').keypress(function(e) {
        if (e.which == 13) {
            $('#verify-otp-btn').click();
        }
    });
    
    function showMessage(message, type) {
        var className = type === 'error' ? 'error' : 'updated';
        $('#otp-messages').html('<div class="' + className + '"><p>' + message + '</p></div>');
    }
});