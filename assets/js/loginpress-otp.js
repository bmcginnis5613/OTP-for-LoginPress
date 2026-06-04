jQuery(document).ready(function($) {
    console.log('LoginPress OTP script loaded');

    moveOtpToggleAfterSubmit();
    setTimeout(moveOtpToggleAfterSubmit, 100);
    setTimeout(moveOtpToggleAfterSubmit, 500);
    
    $('#toggle-otp-login').click(function(e) {
        e.preventDefault();
        $('body').addClass('otp-login-active');
        getRegularLoginFields().hide();
        $('#otp-auth-container').show();
        $('#otp-login-toggle-wrap').hide();
        $('#regular-login-toggle-wrap').show();

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
        getRegularLoginFields().show();
        $('#regular-login-toggle-wrap').hide();
        $('#otp-login-toggle-wrap').show();
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

    function getRegularLoginFields() {
        return $('#loginform').children('p').not('#otp-auth-container').not('#otp-login-toggle-wrap').not('#regular-login-toggle-wrap');
    }

    function moveOtpToggleAfterSubmit() {
        var submitButton = getLoginSubmitButton();
        var otpWrap = $('#otp-login-toggle-wrap');

        if (!submitButton.length || !otpWrap.length) {
            return;
        }

        var submitRow = submitButton.closest('p.submit, p, .submit, .loginpress-submit');
        otpWrap.insertAfter(submitRow.length ? submitRow : submitButton);
        matchLoginButtonStyles(submitButton);

        if ($('body').hasClass('otp-login-active')) {
            otpWrap.hide();
        }
    }

    function getLoginSubmitButton() {
        var selectors = [
            '#wp-submit',
            '#loginform input[type="submit"]',
            '#loginform button[type="submit"]',
            '#loginform .submit .button-primary',
            '#loginform .button-primary[type="submit"]'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var button = $(selectors[i]).first();

            if (button.length) {
                return button;
            }
        }

        return $();
    }

    function matchLoginButtonStyles(submitButton) {
        var loginButton = submitButton[0];
        var otpButton = $('#toggle-otp-login');

        if (!loginButton || !otpButton.length || !window.getComputedStyle) {
            return;
        }

        var loginButtonStyles = window.getComputedStyle(loginButton);
        var matchedStyles = [
            'background-color',
            'border-color',
            'border-radius',
            'color',
            'font-size',
            'font-weight',
            'height',
            'line-height',
            'padding-bottom',
            'padding-left',
            'padding-right',
            'padding-top'
        ];

        $.each(matchedStyles, function(index, property) {
            otpButton.css(property, loginButtonStyles.getPropertyValue(property));
        });

        otpButton.css({
            'align-items': 'center',
            'justify-content': 'center',
            'text-align': 'center'
        });

        if (!$('body').hasClass('otp-login-active') && otpButton.is(':visible')) {
            otpButton.css('display', 'flex');
        }
    }
});
