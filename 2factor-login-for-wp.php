<?php
/**
 * Plugin Name: 2Factor Login for WP
 * Plugin URI: https://github.com/iMiMofficial/2factor-login-for-wp
 * Description: Enable secure OTP-based login/signup for WordPress using 2Factor.in. Highly customizable, with onboarding fields and admin options. Unofficial plugin developed by Md Mim Akhtar.
 * Version: 1.0.0
 * Author: Md Mim Akhtar
 * Author URI: https://github.com/iMiMofficial
 * License: GPL v2 or later
 * Text Domain: 2factor-login-for-wp
 */

if (!defined('ABSPATH')) exit;

// Define constants
if (!defined('TWOFACTOR_LOGIN_WP_VERSION'))
    define('TWOFACTOR_LOGIN_WP_VERSION', '1.0.0');
if (!defined('TWOFACTOR_LOGIN_WP_PATH'))
    define('TWOFACTOR_LOGIN_WP_PATH', plugin_dir_path(__FILE__));
if (!defined('TWOFACTOR_LOGIN_WP_URL'))
    define('TWOFACTOR_LOGIN_WP_URL', plugin_dir_url(__FILE__));

class TwoFactor_Login_WP {
    public function __construct() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_nopriv_twofactor_check_user', [$this, 'ajax_check_user']);
        add_action('wp_ajax_twofactor_check_user', [$this, 'ajax_check_user']);
        add_action('wp_ajax_nopriv_twofactor_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_twofactor_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_twofactor_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_twofactor_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_twofactor_test_data', [$this, 'ajax_test_data']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('show_user_profile', [$this, 'show_user_onboarding_fields']);
        add_action('edit_user_profile', [$this, 'show_user_onboarding_fields']);
        add_action('personal_options_update', [$this, 'save_user_onboarding_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_onboarding_fields']);
        add_shortcode('twofactor_login', [$this, 'render_login_form']);
        
        // Delete phone records when user is deleted
        add_action('delete_user', [$this, 'delete_user_phone_records']);
        
        // Create tables on plugin activation
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
    }
    
    public function activate_plugin() {
        $this->create_phone_mapping_table();
    }

    public function load_textdomain() {
        load_plugin_textdomain('2factor-login-for-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_assets() {
        wp_enqueue_style('twofactor-login-style', TWOFACTOR_LOGIN_WP_URL . 'assets/css/otp-login.css', [], time());
        wp_enqueue_script('twofactor-login-script', TWOFACTOR_LOGIN_WP_URL . 'assets/js/otp-login.js', ['jquery'], TWOFACTOR_LOGIN_WP_VERSION, true);
        wp_localize_script('twofactor-login-script', 'TwoFactorLoginWP', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'settings' => $this->get_plugin_settings(),
            'nonce' => wp_create_nonce('twofactor_login_wp_nonce'),
        ]);
        
        // Apply dynamic custom styles
        $settings = $this->get_plugin_settings();
            add_action('wp_head', function() use ($settings) {
            $this->output_custom_styles($settings);
        });
    }
    
    private function output_custom_styles($settings) {
        $custom_css = '';
        
        // Apply custom CSS variables
        if (!empty($settings['ui_primary_color'])) {
            $custom_css .= "--tflwp-primary-color: {$settings['ui_primary_color']};\n";
        }
        if (!empty($settings['ui_secondary_color'])) {
            $custom_css .= "--tflwp-secondary-color: {$settings['ui_secondary_color']};\n";
        }
        if (!empty($settings['ui_border_radius'])) {
            $custom_css .= "--tflwp-border-radius: {$settings['ui_border_radius']};\n";
        }
        if (!empty($settings['ui_form_width'])) {
            $custom_css .= ".tflwp-form-container { max-width: {$settings['ui_form_width']}; }\n";
        }
        if (!empty($settings['ui_form_padding'])) {
            $custom_css .= "#tflwp-otp-form { padding: {$settings['ui_form_padding']}; }\n";
        }
        if (!empty($settings['ui_form_background'])) {
            $custom_css .= "#tflwp-otp-form { background: {$settings['ui_form_background']}; }\n";
        }
        if (!empty($settings['ui_input_background'])) {
            $custom_css .= ".form-group input, .input-group { background: {$settings['ui_input_background']}; }\n";
        }
        if (!empty($settings['ui_text_color'])) {
            $custom_css .= ".form-group label, .form-group input { color: {$settings['ui_text_color']}; }\n";
        }
        if (!empty($settings['ui_placeholder_color'])) {
            $custom_css .= ".form-group input::placeholder { color: {$settings['ui_placeholder_color']}; }\n";
        }
        if (!empty($settings['ui_border_color'])) {
            $custom_css .= ".form-group input, .input-group { border-color: {$settings['ui_border_color']}; }\n";
        }
        if (!empty($settings['ui_custom_font'])) {
            $custom_css .= ".tflwp-form-container { font-family: {$settings['ui_custom_font']}; }\n";
        }
        
        // Apply shadow styles
        if (!empty($settings['ui_shadow_style'])) {
            switch ($settings['ui_shadow_style']) {
                case 'medium':
                    $custom_css .= "#tflwp-otp-form { box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); }\n";
                    break;
                case 'strong':
                    $custom_css .= "#tflwp-otp-form { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15); }\n";
                    break;
                case 'none':
                    $custom_css .= "#tflwp-otp-form { box-shadow: none; }\n";
                    break;
            }
        }
        
        // Apply button hover effects
        if (!empty($settings['ui_button_hover_effect'])) {
            switch ($settings['ui_button_hover_effect']) {
                case 'glow':
                    $custom_css .= ".btn:hover { transform: none; box-shadow: 0 0 20px rgba(0, 115, 170, 0.4); }\n";
                    break;
                case 'scale':
                    $custom_css .= ".btn:hover { transform: scale(1.05); }\n";
                    break;
                case 'none':
                    $custom_css .= ".btn:hover { transform: none; box-shadow: none; }\n";
                    break;
            }
        }
        
        // Apply message styles
        if (!empty($settings['ui_message_style'])) {
            switch ($settings['ui_message_style']) {
                case 'square':
                    $custom_css .= ".tflwp-message { border-radius: 0; }\n";
                    break;
                case 'pill':
                    $custom_css .= ".tflwp-message { border-radius: 50px; }\n";
                    break;
            }
        }
        
        // Disable animations if requested
        if (empty($settings['ui_animation_effects'])) {
            $custom_css .= "* { transition: none !important; animation: none !important; }\n";
        }
        
        // Disable dark mode if requested
        if (empty($settings['ui_dark_mode_support'])) {
            $custom_css .= "@media (prefers-color-scheme: dark) { #tflwp-otp-form { background: #ffffff !important; } }\n";
        }
        
        // Add custom CSS from admin
        if (!empty($settings['custom_css'])) {
            $custom_css .= $settings['custom_css'] . "\n";
        }
        
        if (!empty($custom_css)) {
            echo '<style id="tflwp-custom-styles">' . esc_html($custom_css) . '</style>';
        }
    }

    public function enqueue_admin_assets() {
        // Optionally enqueue admin styles/scripts
    }

    public function register_settings_page() {
        add_options_page(
            esc_html__('2Factor Login Settings', '2factor-login-for-wp'),
            esc_html__('2Factor Login', '2factor-login-for-wp'),
            'manage_options',
            'twofactor-login-wp',
            array($this, 'settings_page_html')
        );
    }

    public function register_settings() {
        register_setting('twofactor_login_wp_settings', 'twofactor_login_wp_options', [
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default' => $this->default_settings(),
            'textdomain' => '2factor-login-for-wp',
        ]);
    }

    public function default_settings() {
        return [
            'api_key' => '',
            'otp_length' => 5,
            'otp_expiry' => 300,
            'country_code' => '+91',
            'allow_country_selection' => 0,
            'require_email' => 1,
            'require_name' => 0,
            'onboarding_timing' => 'after',
            'ui_primary_color' => '#0073aa',
            'ui_secondary_color' => '#005a87',
            'ui_border_radius' => '12px',
            'ui_form_width' => '450px',
            'ui_form_padding' => '32px',
            'ui_button_style' => 'modern',
            'ui_animation_effects' => 1,
            'ui_dark_mode_support' => 1,
            'ui_custom_font' => '',
            'ui_form_background' => '#ffffff',
            'ui_input_background' => '#ffffff',
            'ui_text_color' => '#1a1a1a',
            'ui_placeholder_color' => '#9ca3af',
            'ui_border_color' => '#e8eaed',
            'ui_shadow_style' => 'subtle',
            'ui_button_hover_effect' => 'lift',
            'ui_message_style' => 'rounded',
            'redirect_url' => '',
            'custom_css' => '',
            'user_role' => 'subscriber',
            'username_generation' => 'truncated',
        ];
    }

    public function get_plugin_settings() {
        $defaults = $this->default_settings();
        $options = get_option('twofactor_login_wp_options', []);
        return wp_parse_args($options, $defaults);
    }

    public function sanitize_settings($input) {
        $input['api_key'] = sanitize_text_field($input['api_key']);
        $input['otp_length'] = max(4, min(8, intval($input['otp_length'])));
        $input['otp_expiry'] = max(60, min(900, intval($input['otp_expiry'])));
        $input['country_code'] = preg_replace('/[^\+0-9]/', '', $input['country_code']);
        $input['allow_country_selection'] = !empty($input['allow_country_selection']) ? 1 : 0;
        $input['require_email'] = !empty($input['require_email']) ? 1 : 0;
        $input['require_name'] = !empty($input['require_name']) ? 1 : 0;
        $input['onboarding_timing'] = in_array($input['onboarding_timing'], ['after','both']) ? $input['onboarding_timing'] : 'after';
        $input['ui_primary_color'] = sanitize_hex_color($input['ui_primary_color']);
        $input['ui_secondary_color'] = sanitize_hex_color($input['ui_secondary_color']);
        $input['ui_border_radius'] = sanitize_text_field($input['ui_border_radius']);
        $input['ui_form_width'] = sanitize_text_field($input['ui_form_width']);
        $input['ui_form_padding'] = sanitize_text_field($input['ui_form_padding']);
        $input['ui_button_style'] = in_array($input['ui_button_style'], ['modern', 'classic', 'minimal']) ? $input['ui_button_style'] : 'modern';
        $input['ui_animation_effects'] = !empty($input['ui_animation_effects']) ? 1 : 0;
        $input['ui_dark_mode_support'] = !empty($input['ui_dark_mode_support']) ? 1 : 0;
        $input['ui_custom_font'] = sanitize_text_field($input['ui_custom_font']);
        $input['ui_form_background'] = sanitize_hex_color($input['ui_form_background']);
        $input['ui_input_background'] = sanitize_hex_color($input['ui_input_background']);
        $input['ui_text_color'] = sanitize_hex_color($input['ui_text_color']);
        $input['ui_placeholder_color'] = sanitize_hex_color($input['ui_placeholder_color']);
        $input['ui_border_color'] = sanitize_hex_color($input['ui_border_color']);
        $input['ui_shadow_style'] = in_array($input['ui_shadow_style'], ['subtle', 'medium', 'strong', 'none']) ? $input['ui_shadow_style'] : 'subtle';
        $input['ui_button_hover_effect'] = in_array($input['ui_button_hover_effect'], ['lift', 'glow', 'scale', 'none']) ? $input['ui_button_hover_effect'] : 'lift';
        $input['ui_message_style'] = in_array($input['ui_message_style'], ['rounded', 'square', 'pill']) ? $input['ui_message_style'] : 'rounded';
        $input['redirect_url'] = esc_url_raw($input['redirect_url']);
        $input['custom_css'] = wp_strip_all_tags($input['custom_css']);
        $input['user_role'] = sanitize_key($input['user_role']);
        $input['username_generation'] = in_array($input['username_generation'], ['truncated', 'full']) ? $input['username_generation'] : 'truncated';
        return $input;
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) return;
        $settings = $this->get_plugin_settings();
        $roles = wp_roles()->roles;
        ?>
        <div class="wrap">
            <div style="margin-bottom: 24px; text-align: center;">
                <img src="<?php echo esc_url(TWOFACTOR_LOGIN_WP_URL . 'assets/img/logo.svg'); ?>" alt="2Factor Login for WP Logo" style="max-width:120px; height:auto; display:inline-block; margin-bottom: 8px;" />
            </div>
            <h1><?php esc_html_e('2Factor Login for WP', '2factor-login-for-wp'); ?></h1>
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active"><?php esc_html_e('API Settings', '2factor-login-for-wp'); ?></a>
                <a href="#customization" class="nav-tab"><?php esc_html_e('Customization', '2factor-login-for-wp'); ?></a>
                <a href="#shortcode" class="nav-tab"><?php esc_html_e('Shortcode', '2factor-login-for-wp'); ?></a>
                <a href="#advanced" class="nav-tab"><?php esc_html_e('Advanced', '2factor-login-for-wp'); ?></a>
            </nav>
            <form method="post" action="options.php" id="tflwp-settings-form">
                <?php
                settings_fields('twofactor_login_wp_settings');
                do_settings_sections('twofactor_login_wp_settings');
                $options = $settings;
                ?>
                <!-- API Settings Tab -->
                <div id="api-settings" class="tab-content active">
                    <div class="tflwp-settings-section">
                        <h2><?php esc_html_e('API Configuration', '2factor-login-for-wp'); ?></h2>
                        <p class="description"><?php esc_html_e('Configure your 2Factor.in API settings and OTP parameters.', '2factor-login-for-wp'); ?></p>
                <table class="form-table">
                    <tr>
                                <th scope="row"><?php esc_html_e('2Factor API Key', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="text" name="twofactor_login_wp_options[api_key]" value="<?php echo esc_attr($options['api_key']); ?>" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Get your API key from', '2factor-login-for-wp'); ?> <a href="https://2factor.in" target="_blank">2Factor.in</a></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('OTP Length', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="number" name="twofactor_login_wp_options[otp_length]" value="<?php echo esc_attr($options['otp_length']); ?>" min="4" max="8">
                                    <p class="description"><?php esc_html_e('Number of digits in the OTP (4-8 digits).', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('OTP Expiry (seconds)', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="number" name="twofactor_login_wp_options[otp_expiry]" value="<?php echo esc_attr($options['otp_expiry']); ?>" min="60" max="900">
                                    <p class="description"><?php esc_html_e('How long the OTP remains valid (60-900 seconds).', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Allow Users to Choose Country Code', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="checkbox" name="twofactor_login_wp_options[allow_country_selection]" value="1" <?php checked($options['allow_country_selection'], 1); ?>>
                                    <p class="description"><?php esc_html_e('If enabled, users can select their country code. If disabled, the default country code will be used.', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Default Country Code', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="text" name="twofactor_login_wp_options[country_code]" value="<?php echo esc_attr($options['country_code']); ?>" class="small-text">
                                    <p class="description"><?php esc_html_e('Default country code when user selection is disabled (e.g., +91 for India).', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Require Email Before Onboarding', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="checkbox" name="twofactor_login_wp_options[require_email]" value="1" <?php checked($options['require_email'], 1); ?>>
                                    <p class="description"><?php esc_html_e('Require users to provide their email address during registration.', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Require Name Before Onboarding', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="checkbox" name="twofactor_login_wp_options[require_name]" value="1" <?php checked($options['require_name'], 1); ?>>
                                    <p class="description"><?php esc_html_e('Require users to provide their full name during registration.', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('When to collect onboarding fields?', '2factor-login-for-wp'); ?></th>
                        <td>
                            <select name="twofactor_login_wp_options[onboarding_timing]">
                                <option value="after" <?php selected($options['onboarding_timing'], 'after'); ?>><?php esc_html_e('After OTP', '2factor-login-for-wp'); ?></option>
                                <option value="both" <?php selected($options['onboarding_timing'], 'both'); ?>><?php esc_html_e('Both', '2factor-login-for-wp'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose when to collect email/name fields from the user.', '2factor-login-for-wp'); ?></p>
                        </td>
                    </tr>
                        </table>
                    </div>
                </div>
                <!-- Customization Tab -->
                <div id="customization" class="tab-content">
                    <div class="tflwp-settings-section">
                        <h2><?php esc_html_e('Form Customization', '2factor-login-for-wp'); ?></h2>
                        <p class="description"><?php esc_html_e('Customize the appearance and behavior of the OTP login form.', '2factor-login-for-wp'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Primary Button Color', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_primary_color]" value="<?php echo esc_attr($options['ui_primary_color']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_primary_color']); ?>" class="tflwp-color-text" placeholder="#0073aa" data-target="ui_primary_color">
                                    </div>
                                    <p class="description"><?php esc_html_e('Main color for buttons and primary elements.', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Secondary Color', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_secondary_color]" value="<?php echo esc_attr($options['ui_secondary_color']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_secondary_color']); ?>" class="tflwp-color-text" placeholder="#005a87" data-target="ui_secondary_color">
                                    </div>
                                    <p class="description"><?php esc_html_e('Used for hover effects and secondary elements.', '2factor-login-for-wp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Form Width', '2factor-login-for-wp'); ?></th>
                                <td><input type="text" name="twofactor_login_wp_options[ui_form_width]" value="<?php echo esc_attr($options['ui_form_width']); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Width of the form container (e.g., 450px, 100%).', '2factor-login-for-wp'); ?></p></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Form Padding', '2factor-login-for-wp'); ?></th>
                                <td><input type="text" name="twofactor_login_wp_options[ui_form_padding]" value="<?php echo esc_attr($options['ui_form_padding']); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Internal padding of the form (e.g., 32px, 24px).', '2factor-login-for-wp'); ?></p></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Border Radius', '2factor-login-for-wp'); ?></th>
                                <td><input type="text" name="twofactor_login_wp_options[ui_border_radius]" value="<?php echo esc_attr($options['ui_border_radius']); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Border radius for form elements (e.g., 12px, 8px).', '2factor-login-for-wp'); ?></p></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Button Style', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <select name="twofactor_login_wp_options[ui_button_style]">
                                        <option value="modern" <?php selected($options['ui_button_style'], 'modern'); ?>><?php esc_html_e('Modern (with animations)', '2factor-login-for-wp'); ?></option>
                                        <option value="classic" <?php selected($options['ui_button_style'], 'classic'); ?>><?php esc_html_e('Classic (simple)', '2factor-login-for-wp'); ?></option>
                                        <option value="minimal" <?php selected($options['ui_button_style'], 'minimal'); ?>><?php esc_html_e('Minimal (flat)', '2factor-login-for-wp'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Button Hover Effect', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <select name="twofactor_login_wp_options[ui_button_hover_effect]">
                                        <option value="lift" <?php selected($options['ui_button_hover_effect'], 'lift'); ?>><?php esc_html_e('Lift (move up)', '2factor-login-for-wp'); ?></option>
                                        <option value="glow" <?php selected($options['ui_button_hover_effect'], 'glow'); ?>><?php esc_html_e('Glow (shadow)', '2factor-login-for-wp'); ?></option>
                                        <option value="scale" <?php selected($options['ui_button_hover_effect'], 'scale'); ?>><?php esc_html_e('Scale (grow)', '2factor-login-for-wp'); ?></option>
                                        <option value="none" <?php selected($options['ui_button_hover_effect'], 'none'); ?>><?php esc_html_e('None', '2factor-login-for-wp'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Shadow Style', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <select name="twofactor_login_wp_options[ui_shadow_style]">
                                        <option value="subtle" <?php selected($options['ui_shadow_style'], 'subtle'); ?>><?php esc_html_e('Subtle', '2factor-login-for-wp'); ?></option>
                                        <option value="medium" <?php selected($options['ui_shadow_style'], 'medium'); ?>><?php esc_html_e('Medium', '2factor-login-for-wp'); ?></option>
                                        <option value="strong" <?php selected($options['ui_shadow_style'], 'strong'); ?>><?php esc_html_e('Strong', '2factor-login-for-wp'); ?></option>
                                        <option value="none" <?php selected($options['ui_shadow_style'], 'none'); ?>><?php esc_html_e('None', '2factor-login-for-wp'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Message Style', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <select name="twofactor_login_wp_options[ui_message_style]">
                                        <option value="rounded" <?php selected($options['ui_message_style'], 'rounded'); ?>><?php esc_html_e('Rounded', '2factor-login-for-wp'); ?></option>
                                        <option value="square" <?php selected($options['ui_message_style'], 'square'); ?>><?php esc_html_e('Square', '2factor-login-for-wp'); ?></option>
                                        <option value="pill" <?php selected($options['ui_message_style'], 'pill'); ?>><?php esc_html_e('Pill', '2factor-login-for-wp'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Form Background', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_form_background]" value="<?php echo esc_attr($options['ui_form_background']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_form_background']); ?>" class="tflwp-color-text" placeholder="#ffffff" data-target="ui_form_background">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Input Background', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_input_background]" value="<?php echo esc_attr($options['ui_input_background']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_input_background']); ?>" class="tflwp-color-text" placeholder="#ffffff" data-target="ui_input_background">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Text Color', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_text_color]" value="<?php echo esc_attr($options['ui_text_color']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_text_color']); ?>" class="tflwp-color-text" placeholder="#1a1a1a" data-target="ui_text_color">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Placeholder Color', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_placeholder_color]" value="<?php echo esc_attr($options['ui_placeholder_color']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_placeholder_color']); ?>" class="tflwp-color-text" placeholder="#9ca3af" data-target="ui_placeholder_color">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Border Color', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <div class="tflwp-color-input-group">
                                        <input type="color" name="twofactor_login_wp_options[ui_border_color]" value="<?php echo esc_attr($options['ui_border_color']); ?>" class="tflwp-color-picker">
                                        <input type="text" value="<?php echo esc_attr($options['ui_border_color']); ?>" class="tflwp-color-text" placeholder="#e8eaed" data-target="ui_border_color">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Custom Font', '2factor-login-for-wp'); ?></th>
                                <td><input type="text" name="twofactor_login_wp_options[ui_custom_font]" value="<?php echo esc_attr($options['ui_custom_font']); ?>" class="regular-text" placeholder="'Roboto', sans-serif">
                                <p class="description"><?php esc_html_e('Custom font family (e.g., "Roboto", sans-serif). Leave empty for default.', '2factor-login-for-wp'); ?></p></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Animation Effects', '2factor-login-for-wp'); ?></th>
                                <td><input type="checkbox" name="twofactor_login_wp_options[ui_animation_effects]" value="1" <?php checked($options['ui_animation_effects'], 1); ?>>
                                <p class="description"><?php esc_html_e('Enable smooth animations and transitions.', '2factor-login-for-wp'); ?></p></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Dark Mode Support', '2factor-login-for-wp'); ?></th>
                                <td><input type="checkbox" name="twofactor_login_wp_options[ui_dark_mode_support]" value="1" <?php checked($options['ui_dark_mode_support'], 1); ?>>
                                <p class="description"><?php esc_html_e('Automatically adapt to user\'s dark mode preference.', '2factor-login-for-wp'); ?></p></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <!-- Shortcode Tab -->
                <div id="shortcode" class="tab-content">
                    <div class="tflwp-settings-section">
                        <h2><?php esc_html_e('Shortcode Usage', '2factor-login-for-wp'); ?></h2>
                        <p class="description"><?php esc_html_e('Learn how to use the OTP login form on your website.', '2factor-login-for-wp'); ?></p>
                        <div class="tflwp-shortcode-info">
                            <h3><?php esc_html_e('Basic Shortcode', '2factor-login-for-wp'); ?></h3>
                            <div class="tflwp-code-block">
                                <code>[twofactor_login]</code>
                                <button type="button" class="button tflwp-copy-btn" data-clipboard-text="[twofactor_login]"><?php esc_html_e('Copy', '2factor-login-for-wp'); ?></button>
                            </div>
                            <h3><?php esc_html_e('Usage Examples', '2factor-login-for-wp'); ?></h3>
                            <div class="tflwp-usage-examples">
                                <div class="tflwp-example">
                                    <h4><?php esc_html_e('In Pages/Posts', '2factor-login-for-wp'); ?></h4>
                                    <p><?php esc_html_e('Simply add the shortcode to your content:', '2factor-login-for-wp'); ?></p>
                                    <div class="tflwp-code-block">
                                        <code>[twofactor_login]</code>
                                        <button type="button" class="button tflwp-copy-btn" data-clipboard-text="[twofactor_login]"><?php esc_html_e('Copy', '2factor-login-for-wp'); ?></button>
                                    </div>
                                </div>
                                <div class="tflwp-example">
                                    <h4><?php esc_html_e('In Widgets', '2factor-login-for-wp'); ?></h4>
                                    <p><?php esc_html_e('Add the shortcode to a Text widget:', '2factor-login-for-wp'); ?></p>
                                    <div class="tflwp-code-block">
                                        <code>[twofactor_login]</code>
                                        <button type="button" class="button tflwp-copy-btn" data-clipboard-text="[twofactor_login]"><?php esc_html_e('Copy', '2factor-login-for-wp'); ?></button>
                                    </div>
                                </div>
                                <div class="tflwp-example">
                                    <h4><?php esc_html_e('In Theme Files', '2factor-login-for-wp'); ?></h4>
                                    <p><?php esc_html_e('Use PHP code in your theme templates:', '2factor-login-for-wp'); ?></p>
                                    <div class="tflwp-code-block">
                                        <code>&lt;?php echo do_shortcode("[twofactor_login]"); ?&gt;</code>
                                        <button type="button" class="button tflwp-copy-btn" data-clipboard-text="<?php echo esc_attr('<?php echo do_shortcode("[twofactor_login]"); ?>'); ?>"><?php esc_html_e('Copy', '2factor-login-for-wp'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Advanced Tab -->
                <div id="advanced" class="tab-content">
                    <div class="tflwp-settings-section">
                        <h2><?php esc_html_e('Advanced Settings', '2factor-login-for-wp'); ?></h2>
                        <p class="description"><?php esc_html_e('Configure advanced options for user management and form behavior.', '2factor-login-for-wp'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('User Role for New Users', '2factor-login-for-wp'); ?></th>
                        <td>
                            <select name="twofactor_login_wp_options[user_role]" id="tflwp_user_role_select">
                                <?php foreach ($roles as $role_key => $role): ?>
                                    <option value="<?php echo esc_attr($role_key); ?>" <?php selected($options['user_role'], $role_key); ?>><?php echo esc_html($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="tflwp-admin-role-warning" style="display:<?php echo ($options['user_role'] === 'administrator') ? 'block' : 'none'; ?>;color:#b32d2e;font-weight:bold;margin-top:8px;">
                                <?php esc_html_e('Warning: Assigning the Administrator role to new users is a serious security risk! Only use this for testing or if you fully understand the consequences.', '2factor-login-for-wp'); ?>
                            </div>
                            <p class="description"><?php esc_html_e('Role assigned to new users (default: subscriber).', '2factor-login-for-wp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Username Generation', '2factor-login-for-wp'); ?></th>
                        <td>
                            <select name="twofactor_login_wp_options[username_generation]">
                                        <option value="truncated" <?php selected($options['username_generation'], 'truncated'); ?>><?php esc_html_e('Truncated (Last 4 digits + random code)', '2factor-login-for-wp'); ?></option>
                                        <option value="full" <?php selected($options['username_generation'], 'full'); ?>><?php esc_html_e('Full phone number', '2factor-login-for-wp'); ?></option>
                            </select>
                                    <p class="description"><?php esc_html_e('How to generate usernames for new users. Truncated is more privacy-friendly.', '2factor-login-for-wp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                                <th scope="row"><?php esc_html_e('Redirect URL After Login', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <input type="url" name="twofactor_login_wp_options[redirect_url]" value="<?php echo esc_attr($options['redirect_url']); ?>" class="regular-text" placeholder="https://yoursite.com/dashboard">
                                    <p class="description"><?php esc_html_e('URL to redirect users after successful login. Leave empty for default.', '2factor-login-for-wp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Custom CSS', '2factor-login-for-wp'); ?></th>
                                <td>
                                    <textarea name="twofactor_login_wp_options[custom_css]" rows="10" class="large-text code" placeholder="/* Add your custom CSS here */"><?php echo esc_textarea($options['custom_css']); ?></textarea>
                                    <p class="description"><?php esc_html_e('Add custom CSS to further customize the form appearance.', '2factor-login-for-wp'); ?></p>
                                </td>
                    </tr>
                </table>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
            <div class="tflwp-footer">
                <p><?php esc_html_e('Plugin by', '2factor-login-for-wp'); ?> <a href="https://github.com/iMiMofficial" target="_blank">Md Mim Akhtar</a>. <?php esc_html_e('Not affiliated with 2Factor.in.', '2factor-login-for-wp'); ?></p>
        </div>
        </div>
        <style>
        .tflwp-settings-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tflwp-color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tflwp-color-picker {
            width: 50px;
            height: 35px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .tflwp-color-text {
            width: 100px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
        }
        .tflwp-shortcode-info {
            max-width: 800px;
        }
        .tflwp-code-block {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tflwp-code-block code {
            font-size: 14px;
            color: #333;
        }
        .tflwp-copy-btn {
            margin-left: 10px;
        }
        .tflwp-usage-examples {
            margin-top: 20px;
        }
        .tflwp-example {
            margin-bottom: 25px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .tflwp-example h4 {
            margin-top: 0;
            color: #0073aa;
        }
        .tflwp-footer {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
            text-align: center;
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                // Show target content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            // Color picker and text input sync
            $('.tflwp-color-picker').on('change', function() {
                var color = $(this).val();
                var target = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
                $('.tflwp-color-text[data-target="' + target + '"]').val(color);
            });
            $('.tflwp-color-text').on('input', function() {
                var color = $(this).val();
                var target = $(this).data('target');
                $('input[name="twofactor_login_wp_options[' + target + ']"]').val(color);
            });
            // Copy button functionality
            $('.tflwp-copy-btn').on('click', function() {
                var text = $(this).data('clipboard-text');
                navigator.clipboard.writeText(text).then(function() {
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.text('Copied!');
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                }.bind(this));
            });
        });
        </script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var roleSelect = document.getElementById('tflwp_user_role_select');
            var warning = document.getElementById('tflwp-admin-role-warning');
            if (roleSelect && warning) {
                roleSelect.addEventListener('change', function() {
                    if (roleSelect.value === 'administrator') {
                        warning.style.display = 'block';
                    } else {
                        warning.style.display = 'none';
                    }
                });
            }
        });
        </script>
        <?php
    }

    // ... (AJAX handlers, form rendering, onboarding, OTP logic will be added next)
    public function render_login_form($atts = []) {
        $settings = $this->get_plugin_settings();
        $primary_color = $settings['ui_primary_color'] ?? '#0073aa';
        $timing = $settings['onboarding_timing'] ?? 'after';
        $require_email = !empty($settings['require_email']);
        $require_name = !empty($settings['require_name']);
        $allow_country_selection = !empty($settings['allow_country_selection']);
        $country_code = $settings['country_code'] ?? '+91';
        ob_start();
        ?>
        <div class="tflwp-form-container">
            <div style="text-align:center; margin-bottom: 18px;">
                <img src="<?php echo esc_url(TWOFACTOR_LOGIN_WP_URL . 'assets/img/logo.svg'); ?>" alt="2Factor Login for WP Logo" style="max-width:80px; height:auto; display:inline-block; margin-bottom: 6px;" />
            </div>
            <form id="tflwp-otp-form" autocomplete="off">
                <div class="tflwp-phone-section">
                    <div class="form-group">
                        <label for="tflwp_phone"><?php esc_html_e('Phone Number', '2factor-login-for-wp'); ?></label>
                        <div class="input-group">
                            <?php if ($allow_country_selection): ?>
                                <select id="tflwp_country_code" name="country_code" class="country-code-select">
                                    <option value="+91" <?php selected($country_code, '+91'); ?>>+91</option>
                                    <option value="+1" <?php selected($country_code, '+1'); ?>>+1</option>
                                    <option value="+44" <?php selected($country_code, '+44'); ?>>+44</option>
                                    <option value="+61" <?php selected($country_code, '+61'); ?>>+61</option>
                                    <option value="+86" <?php selected($country_code, '+86'); ?>>+86</option>
                                    <option value="+81" <?php selected($country_code, '+81'); ?>>+81</option>
                                    <option value="+49" <?php selected($country_code, '+49'); ?>>+49</option>
                                    <option value="+33" <?php selected($country_code, '+33'); ?>>+33</option>
                                    <option value="+39" <?php selected($country_code, '+39'); ?>>+39</option>
                                    <option value="+34" <?php selected($country_code, '+34'); ?>>+34</option>
                                    <option value="+971" <?php selected($country_code, '+971'); ?>>+971</option>
                                    <option value="+966" <?php selected($country_code, '+966'); ?>>+966</option>
                                    <option value="+65" <?php selected($country_code, '+65'); ?>>+65</option>
                                    <option value="+60" <?php selected($country_code, '+60'); ?>>+60</option>
                                    <option value="+66" <?php selected($country_code, '+66'); ?>>+66</option>
                                </select>
                            <?php else: ?>
                                <span class="country-code"><?php echo esc_html($country_code); ?></span>
                            <?php endif; ?>
                            <input type="tel" id="tflwp_phone" name="phone" maxlength="10" pattern="[0-9]{10}" required placeholder="Enter your phone number">
            </div>
                    </div>
                    <?php if ($timing === 'before' || $timing === 'both'): ?>
                        <?php if ($require_email): ?>
                            <div class="form-group">
                                <label for="tflwp_email"><?php esc_html_e('Email', '2factor-login-for-wp'); ?></label>
                                <input type="email" id="tflwp_email" name="email" required placeholder="you@example.com">
                            </div>
                        <?php endif; ?>
                        <?php if ($require_name): ?>
                            <div class="form-group">
                                <label for="tflwp_name"><?php esc_html_e('Name', '2factor-login-for-wp'); ?></label>
                                <input type="text" id="tflwp_name" name="name" required placeholder="Your Name">
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button type="submit" class="btn tflwp-send-otp" style="background:<?php echo esc_attr($primary_color); ?>"><?php esc_html_e('Send OTP', '2factor-login-for-wp'); ?></button>
                </div>
                <div class="tflwp-otp-section" style="display:none;">
                    <div class="form-group">
                        <label for="tflwp_otp"><?php esc_html_e('Enter OTP', '2factor-login-for-wp'); ?></label>
                        <input type="text" id="tflwp_otp" name="otp" maxlength="6" pattern="[0-9]{4,8}" required placeholder="12345">
                    </div>
                    <?php if ($timing === 'after' || $timing === 'both'): ?>
                        <?php if ($require_email): ?>
                            <div class="form-group">
                                <label for="tflwp_email2"><?php esc_html_e('Email', '2factor-login-for-wp'); ?></label>
                                <input type="email" id="tflwp_email2" name="email2" required placeholder="you@example.com">
                            </div>
                        <?php endif; ?>
                        <?php if ($require_name): ?>
                            <div class="form-group">
                                <label for="tflwp_name2"><?php esc_html_e('Name', '2factor-login-for-wp'); ?></label>
                                <input type="text" id="tflwp_name2" name="name2" required placeholder="Your Name">
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button type="submit" class="btn tflwp-verify-otp" style="background:<?php echo esc_attr($primary_color); ?>"><?php esc_html_e('Verify OTP', '2factor-login-for-wp'); ?></button>
                    <div class="resend-otp"><button type="button" class="tflwp-resend-btn"><?php esc_html_e('Resend OTP', '2factor-login-for-wp'); ?></button> <span class="tflwp-timer" style="display:none;"></span></div>
                </div>
                <div class="tflwp-message" role="alert" style="display:none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    public function ajax_check_user() {
        check_ajax_referer('twofactor_login_wp_nonce', 'nonce');
        $settings = $this->get_plugin_settings();
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $country_code = $settings['country_code'] ?: '+91';
        $phone = $this->normalize_phone_number($phone, $country_code);
        // Always return generic message to prevent enumeration
        $require_email = $settings['require_email'] ?? 1;
        $require_name = $settings['require_name'] ?? 0;
        $onboarding_timing = $settings['onboarding_timing'] ?? 'after';
        wp_send_json_success([
            'user_exists' => null, // do not reveal
            'require_email' => $require_email,
            'require_name' => $require_name,
            'onboarding_timing' => $onboarding_timing,
            'message' => esc_html__('Continue to login or register.', '2factor-login-for-wp')
        ]);
    }

    public function ajax_send_otp() {
        check_ajax_referer('twofactor_login_wp_nonce', 'nonce');
        $settings = $this->get_plugin_settings();
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $before = isset($_POST['before']) && is_array($_POST['before']) ? array_map('sanitize_text_field', wp_unslash($_POST['before'])) : [];
        $country_code = $settings['country_code'] ?: '+91';
        
        // Clean phone number and handle country code properly
        $phone = $this->normalize_phone_number($phone, $country_code);
        // Validate phone
        if (!preg_match('/^\+\d{1,4}[0-9]{10}$/', $phone)) {
            wp_send_json_error(['message' => esc_html__('Invalid phone number.', '2factor-login-for-wp')]);
        }
        // Validate onboarding fields if required before OTP
        if (($settings['onboarding_timing'] === 'before' || $settings['onboarding_timing'] === 'both')) {
            if ($settings['require_email'] && empty($before['email'])) {
                wp_send_json_error(['message' => esc_html__('Email is required.', '2factor-login-for-wp')]);
            }
            if ($settings['require_name'] && empty($before['name'])) {
                wp_send_json_error(['message' => esc_html__('Name is required.', '2factor-login-for-wp')]);
            }
            if (!empty($before['email']) && !is_email($before['email'])) {
                wp_send_json_error(['message' => esc_html__('Invalid email address.', '2factor-login-for-wp')]);
            }
        }
        // Rate limiting: store in transient (per phone)
        $rate_key = 'tflwp_otp_rate_' . md5($phone);
        $last_sent = get_transient($rate_key);
        if ($last_sent && (time() - $last_sent < 60)) {
            wp_send_json_error(['message' => esc_html__('Please wait before requesting another OTP.', '2factor-login-for-wp')]);
        }
        // Generate OTP
        $otp = '';
        for ($i = 0; $i < intval($settings['otp_length']); $i++) {
            $otp .= wp_rand(0, 9);
        }
        // Store OTP in transient (secure, expires in otp_expiry)
        set_transient('tflwp_otp_' . md5($phone), [
            'otp' => $otp,
            'expires' => time() + intval($settings['otp_expiry']),
            'attempts' => 0,
            'before' => $before,
        ], intval($settings['otp_expiry']));
        set_transient($rate_key, time(), 60); // 1 min cooldown

        // Send OTP via 2Factor API (production)
        $api_key = $settings['api_key'];
        if (empty($api_key) || $api_key === 'YOUR_2FACTOR_API_KEY_HERE') {
            wp_send_json_error(['message' => esc_html__('2Factor API key not configured. Please contact administrator.', '2factor-login-for-wp')]);
        }
        $api_url = 'https://2factor.in/API/V1/' . urlencode($api_key) . '/SMS/' . urlencode($phone) . '/' . urlencode($otp) . '/OTP';
        $response = wp_remote_get($api_url, ['timeout' => 15]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => esc_html__('Failed to send OTP. Please try again.', '2factor-login-for-wp')]);
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data || !isset($data['Status']) || $data['Status'] !== 'Success') {
            wp_send_json_error(['message' => esc_html__('Failed to send OTP. Please try again.', '2factor-login-for-wp')]);
        }
        wp_send_json_success(['message' => esc_html__('OTP sent successfully! Check your phone.', '2factor-login-for-wp')]);
    }

    public function ajax_verify_otp() {
        check_ajax_referer('twofactor_login_wp_nonce', 'nonce');
        $settings = $this->get_plugin_settings();
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $otp = isset($_POST['otp']) ? sanitize_text_field(wp_unslash($_POST['otp'])) : '';
        $after = isset($_POST['after']) && is_array($_POST['after']) ? array_map('sanitize_text_field', wp_unslash($_POST['after'])) : [];
        $country_code = $settings['country_code'] ?: '+91';
        $phone = $this->normalize_phone_number($phone, $country_code);
        // Brute force protection: IP-based lockout
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $ip_fail_key = 'tflwp_otp_fail_' . md5($ip);
        $ip_failures = (int) get_transient($ip_fail_key);
        if ($ip_failures >= 3) {
            wp_send_json_error(['message' => esc_html__('Too many failed attempts from your IP. Please wait 5 minutes and try again.', '2factor-login-for-wp')]);
        }
        // Validate phone
        if (!preg_match('/^\+\d{1,4}[0-9]{10}$/', $phone)) {
            wp_send_json_error(['message' => esc_html__('Invalid phone number.', '2factor-login-for-wp')]);
        }
        // Validate OTP
        if (empty($otp)) {
            wp_send_json_error(['message' => esc_html__('Please enter OTP.', '2factor-login-for-wp')]);
        }
        // Retrieve OTP from transient, fallback to DB if missing
        $otp_data = get_transient('tflwp_otp_' . md5($phone));
        if (!$otp_data || !isset($otp_data['otp'])) {
            // Fallback: check DB for unexpired, unverified OTP
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'otp_logins');
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE phone = %s AND expires_at > NOW() AND verified = 0 ORDER BY created_at DESC LIMIT 1", $phone));
            if ($row) {
                $otp_data = [
                    'otp' => $row->otp,
                    'expires' => strtotime($row->expires_at),
                    'attempts' => $row->attempts,
                    'before' => [],
                ];
            } else {
                wp_send_json_error(['message' => esc_html__('OTP expired or not found. Please request a new one.', '2factor-login-for-wp')]);
            }
        }
        // Check attempts
        if ($otp_data['attempts'] >= 3) {
            wp_send_json_error(['message' => esc_html__('Too many attempts. Please request a new OTP.', '2factor-login-for-wp')]);
        }
        // Check OTP
        if ($otp_data['otp'] !== $otp) {
            $otp_data['attempts']++;
            set_transient('tflwp_otp_' . md5($phone), $otp_data, $settings['otp_expiry']);
            set_transient($ip_fail_key, $ip_failures + 1, 5 * MINUTE_IN_SECONDS);
            wp_send_json_error(['message' => esc_html__('Invalid OTP. Please try again.', '2factor-login-for-wp')]);
        } else {
            // Reset IP failure count on success
            delete_transient($ip_fail_key);
        }
        // OTP is valid, but do not delete transient yet
        $onboarding = array_merge(
            isset($otp_data['before']) && is_array($otp_data['before']) ? $otp_data['before'] : [],
            $after
        );
        $user = $this->get_user_by_phone($phone);
        if ($user) {
            $user_id = $user->ID;
            if (!empty($onboarding['name'])) {
                update_user_meta($user_id, 'tflwp_name', sanitize_text_field($onboarding['name']));
                $name_parts = explode(' ', trim($onboarding['name']), 2);
                $first_name = $name_parts[0];
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                update_user_meta($user_id, 'first_name', sanitize_text_field($first_name));
                update_user_meta($user_id, 'last_name', sanitize_text_field($last_name));
            }
            if (!empty($onboarding['email'])) update_user_meta($user_id, 'tflwp_email', sanitize_email($onboarding['email']));
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            do_action('wp_login', $user->user_login, $user);
            $redirect_url = apply_filters('tflwp_login_redirect', home_url('/'));
            if (!empty($settings['redirect_url'])) {
                $redirect_url = esc_url_raw($settings['redirect_url']);
            }
            if (user_can($user_id, 'manage_options')) $redirect_url = admin_url();
            delete_transient('tflwp_otp_' . md5($phone));
            wp_send_json_success([
                'message' => esc_html__('Login successful! Redirecting...', '2factor-login-for-wp'),
                'redirect_url' => $redirect_url,
            ]);
        } else {
            $require_email = $settings['require_email'] ?? 1;
            $require_name = $settings['require_name'] ?? 0;
            $onboarding_timing = $settings['onboarding_timing'] ?? 'after';
            if ($require_email && empty($onboarding['email'])) {
                wp_send_json_error(['message' => esc_html__('Registration failed. Please try again or use a different email.', '2factor-login-for-wp')]);
            }
            if ($require_name && empty($onboarding['name'])) {
                wp_send_json_error(['message' => esc_html__('Registration failed. Please try again or use a different email.', '2factor-login-for-wp')]);
            }
            if (!empty($onboarding['email']) && !is_email($onboarding['email'])) {
                wp_send_json_error(['message' => esc_html__('Registration failed. Please try again or use a different email.', '2factor-login-for-wp')]);
            }
            if (!empty($onboarding['email']) && email_exists($onboarding['email'])) {
                wp_send_json_error(['message' => esc_html__('Registration failed. Please try again or use a different email.', '2factor-login-for-wp')]);
            }
            // ... rest of user creation logic ...
        }
    }
    
    private function generate_username($onboarding, $phone, $settings) {
        $username_generation = $settings['username_generation'] ?? 'truncated';
        
        // If name is provided, use it for username generation
        if (!empty($onboarding['name'])) {
            $name = sanitize_user($onboarding['name']);
            $name = preg_replace('/[^a-zA-Z0-9]/', '', $name); // Remove special characters
            $name = strtolower($name);
            
            if (!empty($name)) {
                $username = $name;
                
                // Ensure unique username
                $original_username = $username;
                $counter = 1;
                while (username_exists($username)) {
                    $username = $original_username . '_' . $counter;
                    $counter++;
                    if ($counter > 100) {
                        break; // Fall back to phone-based generation
                    }
                }
                
                if (!username_exists($username)) {
                    return $username;
                }
            }
        }
        
        // Fall back to phone-based username generation
        $phone_digits = preg_replace('/[^0-9]/', '', $phone);
        
        if ($username_generation === 'full') {
            // Full phone number option
            $username = 'user_' . $phone_digits;
        } else {
            // Truncated phone with random code (default)
            $last_4_digits = substr($phone_digits, -4);
            $random_code = wp_generate_password(4, false, false); // 4 random alphanumeric chars
            $username = 'user_' . $last_4_digits . '_' . $random_code;
        }
        
        // Ensure unique username
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            if ($username_generation === 'full') {
                $username = $original_username . '_' . $counter;
            } else {
                $random_code = wp_generate_password(4, false, false);
                $username = 'user_' . $last_4_digits . '_' . $random_code;
            }
            $counter++;
            if ($counter > 100) {
                wp_send_json_error(['message' => esc_html__('Unable to create unique username. Please try again.', '2factor-login-for-wp')]);
            }
        }
        
        return $username;
    }

    private function normalize_phone_number($phone, $default_country_code = '+91') {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If phone already starts with +, use it as is
        if (strpos($phone, '+') === 0) {
            return $phone;
        }
        
        // If phone starts with country code without +, add +
        $country_code_without_plus = ltrim($default_country_code, '+');
        if (strpos($phone, $country_code_without_plus) === 0) {
            return '+' . $phone;
        }
        
        // Otherwise, add the default country code
        return $default_country_code . $phone;
    }

    private function get_user_by_phone($phone) {
        $user_id = $this->get_cached_user_id_by_phone($phone);
        if ($user_id) {
            return get_user_by('id', $user_id);
        }
        return false;
    }
    
    private function create_phone_mapping_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tflwp_user_phones';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            phone varchar(20) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY phone_active (phone, is_active),
            KEY user_id (user_id),
            KEY phone (phone)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function migrate_phone_to_table($user_id, $phone) {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'tflwp_user_phones');
        $exists = $wpdb->get_var(
            $wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND phone = %s",
            $user_id, $phone
            )
        );
        if (!$exists) {
            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'phone' => $phone,
                    'is_active' => 1
                ],
                ['%d', '%s', '%d']
            );
            $this->delete_cached_user_id_by_phone($phone);
            $this->delete_cached_phones_by_user_id($user_id);
        }
    }
    
    private function store_user_phone($user_id, $phone) {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'tflwp_user_phones');
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        if (!$table_exists) {
            $this->create_phone_mapping_table();
        }
        $wpdb->update(
            $table_name,
            ['is_active' => 0],
            ['user_id' => $user_id],
            ['%d'],
            ['%d']
        );
        $this->delete_cached_phones_by_user_id($user_id);
        $existing_user = $this->get_cached_user_id_by_phone($phone);
        if ($existing_user && $existing_user != $user_id) {
            return false; // Phone already in use
        }
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'phone' => $phone,
                'is_active' => 1
            ],
            ['%d', '%s', '%d']
        );
        $this->delete_cached_user_id_by_phone($phone);
        $this->delete_cached_phones_by_user_id($user_id);
        update_user_meta($user_id, 'tflwp_phone', $phone);
        return $result !== false;
    }

    private function is_phone_in_use($phone, $exclude_user_id = null) {
        $user_id = $this->get_cached_user_id_by_phone($phone);
        if ($exclude_user_id && $user_id && $user_id == $exclude_user_id) {
            return false;
        }
        return (bool)$user_id;
    }

    private function get_user_phones($user_id) {
        return $this->get_cached_phones_by_user_id($user_id);
    }
    
    public function show_user_onboarding_fields($user) {
        $phones = $this->get_user_phones($user->ID);
        $primary_phone = !empty($phones) ? $phones[0] : get_user_meta($user->ID, 'tflwp_phone', true);
        
        // Clean up the phone number display to prevent duplicate country codes
        if (!empty($primary_phone)) {
            $primary_phone = $this->normalize_phone_number($primary_phone);
        }
        ?>
        <h3><?php esc_html_e('2Factor Login Onboarding Data', '2factor-login-for-wp'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="tflwp_phone"><?php esc_html_e('Primary Phone Number', '2factor-login-for-wp'); ?></label></th>
                <td>
                    <input type="text" name="tflwp_phone" id="tflwp_phone" value="<?php echo esc_attr($primary_phone); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Primary phone number for OTP login.', '2factor-login-for-wp'); ?></p>
                    <?php if (count($phones) > 1): ?>
                        <?php // translators: %d: Number of phone numbers registered for the user ?>
                        <p class="description"><?php printf(esc_html__('User has %d phone numbers registered.', '2factor-login-for-wp'), count($phones)); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="tflwp_name"><?php esc_html_e('Name', '2factor-login-for-wp'); ?></label></th>
                <td><input type="text" name="tflwp_name" id="tflwp_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'tflwp_name', true)); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="tflwp_email"><?php esc_html_e('Email (for OTP login)', '2factor-login-for-wp'); ?></label></th>
                <td><input type="email" name="tflwp_email" id="tflwp_email" value="<?php echo esc_attr(get_user_meta($user->ID, 'tflwp_email', true)); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }

    public function save_user_onboarding_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) return false;
        $nonce = isset($_POST['tflwp_nonce']) ? sanitize_text_field(wp_unslash($_POST['tflwp_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'tflwp_onboarding')) {
            return false;
        }
        // Handle phone number update
        if (isset($_POST['tflwp_phone'])) {
            $new_phone = sanitize_text_field(wp_unslash($_POST['tflwp_phone']));
            
            // Normalize the phone number to prevent duplicate country codes
            if (!empty($new_phone)) {
                $new_phone = $this->normalize_phone_number($new_phone);
            }
            
            $current_phones = $this->get_user_phones($user_id);
            $current_primary = !empty($current_phones) ? $current_phones[0] : '';
            
            // Only update if phone number has changed
            if ($new_phone !== $current_primary && !empty($new_phone)) {
                // Check if new phone is already in use by another user
                if ($this->is_phone_in_use($new_phone, $user_id)) {
                    // Add error message
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . esc_html__('Phone number is already registered with another account.', '2factor-login-for-wp') . '</p></div>';
                    });
                    return false;
                }
                
                // Store new phone number
                $this->store_user_phone($user_id, $new_phone);
            }
        }
        
        // Save other fields
        if (isset($_POST['tflwp_name'])) {
            $name = sanitize_text_field(wp_unslash($_POST['tflwp_name']));
            update_user_meta($user_id, 'tflwp_name', $name);
            
            // Also update WordPress standard name fields
            $name_parts = explode(' ', trim($name), 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            update_user_meta($user_id, 'first_name', sanitize_text_field($first_name));
            update_user_meta($user_id, 'last_name', sanitize_text_field($last_name));
        }
        if (isset($_POST['tflwp_email'])) update_user_meta($user_id, 'tflwp_email', sanitize_email(wp_unslash($_POST['tflwp_email'])));
        
        return true;
    }
    
    public function delete_user_phone_records($user_id) {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'tflwp_user_phones');
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        if (!$table_exists) {
            return;
        }
        $wpdb->delete(
            $table_name,
            ['user_id' => $user_id],
            ['%d']
        );
        $this->delete_cached_phones_by_user_id($user_id);
        // Also clean up user meta for backward compatibility
        delete_user_meta($user_id, 'tflwp_phone');
        delete_user_meta($user_id, 'tflwp_name');
        delete_user_meta($user_id, 'tflwp_email');
    }

    // === PHONE CACHE HELPERS ===
    private function get_cached_user_id_by_phone($phone) {
        $cache_key = 'tflwp_uid_' . md5($phone);
        $user_id = wp_cache_get($cache_key, 'tflwp_phone');
        if ($user_id === false) {
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'tflwp_user_phones');
            $user_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM $table_name WHERE phone = %s AND is_active = 1",
                    $phone
                )
            );
            if ($user_id) {
                wp_cache_set($cache_key, $user_id, 'tflwp_phone', 600);
            }
        }
        return $user_id;
    }
    private function delete_cached_user_id_by_phone($phone) {
        $cache_key = 'tflwp_uid_' . md5($phone);
        wp_cache_delete($cache_key, 'tflwp_phone');
    }
    private function get_cached_phones_by_user_id($user_id) {
        $cache_key = 'tflwp_phones_' . intval($user_id);
        $phones = wp_cache_get($cache_key, 'tflwp_phone');
        if ($phones === false) {
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'tflwp_user_phones');
            $phones = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT phone FROM $table_name WHERE user_id = %d AND is_active = 1 ORDER BY created_at DESC",
                    $user_id
                )
            );
            // Also get from user meta for backward compatibility
            $meta_phone = get_user_meta($user_id, 'tflwp_phone', true);
            if ($meta_phone && !in_array($meta_phone, $phones)) {
                $phones[] = $meta_phone;
            }
            wp_cache_set($cache_key, $phones, 'tflwp_phone', 600);
        }
        return $phones;
    }
    private function delete_cached_phones_by_user_id($user_id) {
        $cache_key = 'tflwp_phones_' . intval($user_id);
        wp_cache_delete($cache_key, 'tflwp_phone');
    }
}

new TwoFactor_Login_WP(); 