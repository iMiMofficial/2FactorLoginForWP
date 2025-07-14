/**
 * WordPress OTP Login JavaScript - Enhanced Version
 * 
 * Modern and customizable OTP login functionality for WordPress
 */

(function($) {
    'use strict';
    
    // Settings from PHP
    const settings = window.TwoFactorLoginWP ? window.TwoFactorLoginWP.settings : {};
    const ajaxUrl = window.TwoFactorLoginWP ? window.TwoFactorLoginWP.ajax_url : '';
    const nonce = window.TwoFactorLoginWP ? window.TwoFactorLoginWP.nonce : '';
    
    // Utility functions
    const utils = {
        // Apply custom CSS variables
        applyCustomStyles() {
            const primaryColor = settings.ui_primary_color || '#0073aa';
            const secondaryColor = this.adjustBrightness(primaryColor, -20);
            
            document.documentElement.style.setProperty('--tflwp-primary-color', primaryColor);
            document.documentElement.style.setProperty('--tflwp-primary-hover', secondaryColor);
            document.documentElement.style.setProperty('--tflwp-secondary-color', secondaryColor);
        },
        
        // Adjust color brightness
        adjustBrightness(hex, percent) {
            const num = parseInt(hex.replace("#", ""), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) + amt;
            const G = (num >> 8 & 0x00FF) + amt;
            const B = (num & 0x0000FF) + amt;
            return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
        },
        
        // Show message with animation
        showMessage($container, message, type = 'info') {
            const $msg = $container.find('.tflwp-message');
            $msg.removeClass('success error info').addClass(type).text(message);
            $msg.addClass('tflwp-bounce');
            $msg.show(); // Always show the message container
            setTimeout(() => $msg.removeClass('tflwp-bounce'), 600);
        },
        
        // Validate phone number
        validatePhone(phone) {
            return /^\+\d{1,4}[0-9]{10}$/.test(phone);
        },
        
        // Format phone number for display
        formatPhone(phone) {
            return phone.replace(/(\+\d{1,4})(\d{3})(\d{3})(\d{4})/, '$1 $2 $3 $4');
        },
        
        // Add loading state to button
        setButtonLoading($btn, loading = true) {
            if (loading) {
                $btn.prop('disabled', true).addClass('loading');
            } else {
                $btn.prop('disabled', false).removeClass('loading');
            }
        },
        
        // Animate form sections
        animateSection($section, animation = 'fadeIn') {
            $section.addClass(`tflwp-${animation}`);
            setTimeout(() => $section.removeClass(`tflwp-${animation}`), 300);
        }
    };

    // Form state management
    const formState = {
        currentStep: 'phone',
        userExists: false,
        otpSent: false,
        resendTimer: null,
        
        setStep(step) {
            this.currentStep = step;
            this.updateFormVisibility();
        },
        
        updateFormVisibility() {
            const $form = $('#tflwp-otp-form');
            const $phoneSection = $form.find('.tflwp-phone-section');
            const $otpSection = $form.find('.tflwp-otp-section');
            const $beforeFields = $form.find('.tflwp-before-fields');
            const $afterFields = $form.find('.tflwp-after-fields');
            
            switch(this.currentStep) {
                case 'phone':
                    $phoneSection.show();
                    $otpSection.hide();
                    $beforeFields.hide();
                    $afterFields.hide();
                    break;
                case 'before-fields':
                    $phoneSection.hide();
                    $otpSection.hide();
                    $beforeFields.show();
                    $afterFields.hide();
                    break;
                case 'otp':
                    $phoneSection.hide();
                    $otpSection.show();
                    $beforeFields.hide();
                    $afterFields.hide();
                    break;
                case 'after-fields':
                    $phoneSection.hide();
                    $otpSection.show();
                    $beforeFields.hide();
                    $afterFields.show();
                    break;
            }
        }
    };

    // Add required attributes when fields become visible
    function updateRequiredAttributes() {
        // Remove required from all onboarding fields first
        $('#tflwp_email, #tflwp_name, #tflwp_email2, #tflwp_name2').prop('required', false);

        const timing = settings.onboarding_timing || 'after';
        const requireEmail = settings.require_email == 1;
        const requireName = settings.require_name == 1;

        // Before fields
        if ((timing === 'before' || timing === 'both')) {
            if (requireEmail && $('#tflwp_email').is(':visible')) {
                $('#tflwp_email').prop('required', true);
            }
            if (requireName && $('#tflwp_name').is(':visible')) {
                $('#tflwp_name').prop('required', true);
            }
        }
        // After fields
        if ((timing === 'after' || timing === 'both')) {
            if (requireEmail && $('#tflwp_email2').is(':visible')) {
                $('#tflwp_email2').prop('required', true);
            }
            if (requireName && $('#tflwp_name2').is(':visible')) {
                $('#tflwp_name2').prop('required', true);
            }
        }
        // OTP field is required when visible
        if ($('#tflwp_otp').is(':visible')) {
            $('#tflwp_otp').prop('required', true);
        } else {
            $('#tflwp_otp').prop('required', false);
        }
    }

    // Enhanced form rendering with better structure
    function renderForm() {
        const timing = settings.onboarding_timing || 'after';
        const requireEmail = settings.require_email == 1;
        const requireName = settings.require_name == 1;
        const countryCode = settings.country_code || '+91';
        const allowCountrySelection = settings.allow_country_selection == 1;

        // Generate country options
        const countryOptions = generateCountryOptions(countryCode);
        
        // Generate field sections
        const beforeFields = generateFieldSection('before', requireEmail, requireName);
        const afterFields = generateFieldSection('after', requireEmail, requireName);

        const formHtml = `
        <form id="tflwp-otp-form" autocomplete="off">
            <div class="tflwp-phone-section">
                <div class="form-group">
                    <label for="tflwp_phone">Phone Number</label>
                    <div class="input-group">
                        ${generateCountryCodeHtml(allowCountrySelection, countryOptions, countryCode)}
                        <input type="tel" id="tflwp_phone" name="phone" maxlength="10" pattern="[0-9]{10}" required placeholder="Enter your phone number">
                    </div>
                </div>
                <button type="submit" class="btn tflwp-send-otp">Send OTP</button>
            </div>
            
            <div class="tflwp-before-fields" style="display:none;">
                ${beforeFields}
                <button type="submit" class="btn tflwp-send-otp-after-fields">Continue</button>
            </div>
            
            <div class="tflwp-otp-section" style="display:none;">
                <div class="form-group">
                    <label for="tflwp_otp">Enter OTP</label>
                    <input type="text" id="tflwp_otp" name="otp" maxlength="6" pattern="[0-9]{4,8}" placeholder="Enter the OTP sent to your phone">
                </div>
                
                <div class="tflwp-after-fields" style="display:none;">
                    ${afterFields}
                </div>
                
                <button type="submit" class="btn tflwp-verify-otp">Verify OTP</button>
                
                <div class="resend-otp">
                    <button type="button" class="tflwp-resend-btn">Resend OTP</button>
                    <span class="tflwp-timer" style="display:none;"></span>
                </div>
            </div>
            
            <div class="tflwp-message" role="alert" style="display:none;"></div>
        </form>`;
        
        $('#twofactor-login-form-root').html(formHtml);
        
        // Initialize form
        initializeForm();
        
        // Apply custom styles
        utils.applyCustomStyles();
        
        // Set initial state
        formState.setStep('phone');
        // Ensure required attributes are set correctly
        updateRequiredAttributes();
    }

    // Generate country options
    function generateCountryOptions(defaultCode) {
        const countries = [
            { code: '+91', flag: '🇮🇳', name: 'India' },
            { code: '+1', flag: '🇺🇸', name: 'USA' },
            { code: '+44', flag: '🇬🇧', name: 'UK' },
            { code: '+61', flag: '🇦🇺', name: 'Australia' },
            { code: '+86', flag: '🇨🇳', name: 'China' },
            { code: '+81', flag: '🇯🇵', name: 'Japan' },
            { code: '+49', flag: '🇩🇪', name: 'Germany' },
            { code: '+33', flag: '🇫🇷', name: 'France' },
            { code: '+39', flag: '🇮🇹', name: 'Italy' },
            { code: '+34', flag: '🇪🇸', name: 'Spain' },
            { code: '+971', flag: '🇦🇪', name: 'UAE' },
            { code: '+966', flag: '🇸🇦', name: 'Saudi Arabia' },
            { code: '+65', flag: '🇸🇬', name: 'Singapore' },
            { code: '+60', flag: '🇲🇾', name: 'Malaysia' },
            { code: '+66', flag: '🇹🇭', name: 'Thailand' }
        ];
        
        return countries.map(country => {
            const selected = country.code === defaultCode ? 'selected' : '';
            return `<option value="${country.code}" ${selected}>${country.flag} ${country.code} (${country.name})</option>`;
        }).join('');
    }

    // Generate country code HTML
    function generateCountryCodeHtml(allowSelection, options, defaultCode) {
        if (allowSelection) {
            return `<select id="tflwp_country_code" name="country_code" class="country-code-select">${options}</select>`;
        } else {
            return `<span class="country-code">${defaultCode}</span>`;
        }
    }

    // Generate field sections
    function generateFieldSection(type, requireEmail, requireName) {
        let fields = '';
        const suffix = type === 'before' ? '' : '2';
        
        if (requireEmail) {
            fields += `
                <div class="form-group">
                    <label for="tflwp_email${suffix}">Email Address</label>
                    <input type="email" id="tflwp_email${suffix}" name="email" required placeholder="your.email@example.com">
                </div>`;
        }
        
        if (requireName) {
            fields += `
                <div class="form-group">
                    <label for="tflwp_name${suffix}">Full Name</label>
                    <input type="text" id="tflwp_name${suffix}" name="name" required placeholder="Enter your full name">
                </div>`;
        }
        
        return fields;
    }

    // Initialize form functionality
    function initializeForm() {
        // Phone number input handling
        $('#tflwp_phone').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // OTP input handling
        $('#tflwp_otp').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Auto-focus phone field
        $('#tflwp_phone').focus();
        
        // Update required attributes
        updateRequiredAttributes();
    }

    // Enhanced form submission handling
    $(document).on('submit', '#tflwp-otp-form', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $msg = $form.find('.tflwp-message');
        const $sendBtn = $form.find('.tflwp-send-otp');
        const $verifyBtn = $form.find('.tflwp-verify-otp');
        
        // Remove any previous test OTP display
        $form.find('.tflwp-test-otp').remove();
        
        // Get country code and phone
        const countryCode = $('#tflwp_country_code').length ? $('#tflwp_country_code').val() : (settings.country_code || '+91');
        const phone = countryCode + $('#tflwp_phone').val().replace(/[^0-9]/g, '');
        
        if (formState.currentStep === 'otp' || formState.currentStep === 'after-fields') {
            handleOtpVerification($form, phone);
        } else if (formState.currentStep === 'before-fields') {
            handleBeforeFieldsSubmission($form, phone);
        } else {
            handlePhoneSubmission($form, phone);
        }
    });

    // Handle phone number submission
    function handlePhoneSubmission($form, phone) {
        const $msg = $form.find('.tflwp-message');
        const $sendBtn = $form.find('.tflwp-send-otp');
        
        if (!utils.validatePhone(phone)) {
            utils.showMessage($form, 'Please enter a valid phone number.', 'error');
            return;
        }
        
        utils.setButtonLoading($sendBtn, true);
        utils.showMessage($form, 'Checking user account...', 'info');
        
        $.post(ajaxUrl, {
            action: 'twofactor_check_user',
            nonce,
            phone
        }, function(resp) {
            handlePhoneCheckResponse(resp); // <-- new logic
            if (resp.success) {
                if (resp.data.user_exists) {
                    // User exists - send OTP directly
                    formState.userExists = true;
                    sendOtp($form, phone, {});
                } else {
                    // New user - check onboarding requirements
                    const requireEmail = resp.data.require_email;
                    const requireName = resp.data.require_name;
                    const onboardingTiming = resp.data.onboarding_timing;
                    
                    if ((onboardingTiming === 'before' || onboardingTiming === 'both')) {
                        // Show before fields
                        formState.setStep('before-fields');
                        utils.showMessage($form, 'Please provide your information to continue.', 'info');
                    } else {
                        // Send OTP directly
                        sendOtp($form, phone, {});
                    }
                }
            } else {
                utils.showMessage($form, resp.data?.message || 'Error checking user account.', 'error');
            }
            utils.setButtonLoading($sendBtn, false);
        }).fail(function() {
            utils.setButtonLoading($sendBtn, false);
            utils.showMessage($form, 'Network error. Please try again.', 'error');
        });
    }

    // Handle before fields submission
    function handleBeforeFieldsSubmission($form, phone) {
        const $msg = $form.find('.tflwp-message');
        const $sendBtn = $form.find('.tflwp-send-otp-after-fields');
        
        // Collect before fields data
        let beforeData = {};
        if ($('#tflwp_email').length) {
            const emailVal = $('#tflwp_email').val();
            if (!emailVal) {
                utils.showMessage($form, 'Please enter your email address.', 'error');
                return;
            }
            beforeData.email = emailVal;
        }
        if ($('#tflwp_name').length) {
            const nameVal = $('#tflwp_name').val();
            if (!nameVal) {
                utils.showMessage($form, 'Please enter your name.', 'error');
                return;
            }
            beforeData.name = nameVal;
        }
        
        utils.setButtonLoading($sendBtn, true);
        utils.showMessage($form, 'Sending OTP...', 'info');
        
        sendOtp($form, phone, beforeData);
    }

    // Send OTP
    function sendOtp($form, phone, beforeData) {
        const $msg = $form.find('.tflwp-message');
        const $sendBtn = $form.find('.tflwp-send-otp, .tflwp-send-otp-after-fields');
        
        $.post(ajaxUrl, {
            action: 'twofactor_send_otp',
            nonce,
            phone,
            before: beforeData
        }, function(resp) {
            utils.setButtonLoading($sendBtn, false);
            
            if (resp.success) {
                utils.showMessage($form, resp.data.message || 'OTP sent successfully!', 'success');
                formState.setStep('otp');
                formState.otpSent = true;
                startResendTimer($form);
                
                // Focus on OTP field
                setTimeout(() => $('#tflwp_otp').focus(), 500);
            } else {
                utils.showMessage($form, resp.data?.message || 'Failed to send OTP.', 'error');
            }
        }).fail(function() {
            utils.setButtonLoading($sendBtn, false);
            utils.showMessage($form, 'Network error. Please try again.', 'error');
        });
    }

    // Handle OTP verification
    function handleOtpVerification($form, phone) {
        const $msg = $form.find('.tflwp-message');
        const $verifyBtn = $form.find('.tflwp-verify-otp');
        const otp = $('#tflwp_otp').val();
        
        if (!otp) {
            utils.showMessage($form, 'Please enter the OTP.', 'error');
            return;
        }
        
        // Collect after fields data
        let afterData = {};
        if ($('#tflwp_email2').length) afterData.email = $('#tflwp_email2').val();
        if ($('#tflwp_name2').length) afterData.name = $('#tflwp_name2').val();
        
        utils.setButtonLoading($verifyBtn, true);
        utils.showMessage($form, 'Verifying OTP...', 'info');
        
        $.post(ajaxUrl, {
            action: 'twofactor_verify_otp',
            nonce,
            phone,
            otp,
            after: afterData
        }, function(resp) {
            utils.setButtonLoading($verifyBtn, false);
            
            if (resp.success) {
                utils.showMessage($form, resp.data.message || 'Login successful!', 'success');
                
                // Add success animation
                $form.addClass('tflwp-bounce');
                
                setTimeout(function() { 
                    window.location.href = resp.data.redirect_url || '/'; 
                }, 1500);
            } else {
                utils.showMessage($form, resp.data?.message || 'Invalid OTP.', 'error');
                
                // Clear OTP field on error
                $('#tflwp_otp').val('').focus();
            }
        }).fail(function() {
            utils.setButtonLoading($verifyBtn, false);
            utils.showMessage($form, 'Network error. Please try again.', 'error');
        });
    }

    // Enhanced resend timer
    function startResendTimer($form) {
        const $resendBtn = $form.find('.tflwp-resend-btn');
        const $timer = $form.find('.tflwp-timer');
        let timeLeft = 60;
        
        $resendBtn.prop('disabled', true);
        $timer.show().text(`Resend in ${timeLeft}s`);
        
        formState.resendTimer = setInterval(function() {
            timeLeft--;
            $timer.text(`Resend in ${timeLeft}s`);
            
            if (timeLeft <= 0) {
                clearInterval(formState.resendTimer);
                $resendBtn.prop('disabled', false);
                $timer.hide();
            }
        }, 1000);
    }

    // Resend OTP functionality
    $(document).on('click', '.tflwp-resend-btn', function() {
        const $form = $('#tflwp-otp-form');
        const countryCode = $('#tflwp_country_code').length ? $('#tflwp_country_code').val() : (settings.country_code || '+91');
        const phone = countryCode + $('#tflwp_phone').val().replace(/[^0-9]/g, '');
        
        if (formState.resendTimer) {
            clearInterval(formState.resendTimer);
        }
        
        sendOtp($form, phone, {});
    });

    // After phone check, show/hide onboarding fields as needed
    function handlePhoneCheckResponse(resp) {
        const $form = $('#tflwp-otp-form');
        const $otpSection = $form.find('.tflwp-otp-section');
        const $beforeFields = $form.find('.tflwp-phone-section .form-group').not(':first'); // onboarding fields in phone section
        const $afterFields = $form.find('.tflwp-otp-section .form-group').not(':first'); // onboarding fields in OTP section

        if (resp.success && resp.data.user_exists) {
            // Existing user: hide onboarding fields
            $beforeFields.hide();
            $afterFields.hide();
            $beforeFields.find('input,select,textarea').prop('required', false);
            $afterFields.find('input,select,textarea').prop('required', false);
        } else {
            // New user: show onboarding fields as per admin settings and timing
            if (settings.onboarding_timing === 'before' || settings.onboarding_timing === 'both') {
                $beforeFields.show();
                $beforeFields.find('input,select,textarea').each(function() {
                    if ($(this).attr('type') !== 'hidden') $(this).prop('required', true);
                });
            }
            if (settings.onboarding_timing === 'after' || settings.onboarding_timing === 'both') {
                $afterFields.show();
                $afterFields.find('input,select,textarea').each(function() {
                    if ($(this).attr('type') !== 'hidden') $(this).prop('required', true);
                });
            }
        }
        updateRequiredAttributes();
    }

    // Call updateRequiredAttributes after any form state change
    function showSection(section) {
        $('.tflwp-phone-section, .tflwp-before-fields, .tflwp-otp-section, .tflwp-after-fields').hide();
        $(section).show();
        updateRequiredAttributes();
    }

    // Initialize on document ready
    $(document).ready(function() {
        renderForm();
    });

})(jQuery); 