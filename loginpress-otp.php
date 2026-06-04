<?php
/*
Plugin Name: LoginPress OTP
Description: Adds one-time passcode authentication to LoginPress.
Author: FirstTracks Marketing
Author URI: https://firsttracksmarketing.com
Version: 1.0.1
*/

if (!defined('ABSPATH')) {
    exit;
}

// Check if LoginPress is installed
if (!in_array('loginpress/loginpress.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    wp_die(
        __('LoginPress OTP requires the LoginPress plugin to be installed and active.', 'loginpress-otp'),
        __('Plugin Dependency Error', 'loginpress-otp'),
        array('back_link' => true)
    );
}

class LoginPressOTPEmailAuth {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'loginpress_otp_codes';
        
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_send_otp_code', array($this, 'send_otp_code'));
        add_action('wp_ajax_nopriv_send_otp_code', array($this, 'send_otp_code'));
        add_action('wp_ajax_verify_otp_code', array($this, 'verify_otp_code'));
        add_action('wp_ajax_nopriv_verify_otp_code', array($this, 'verify_otp_code'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('login_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('login_form', array($this, 'add_otp_fields'));
        
        register_activation_hook(__FILE__, array($this, 'create_otp_table'));
        register_deactivation_hook(__FILE__, array($this, 'cleanup_expired_codes'));
    }
    
    public function init() {
        // Clean up expired codes periodically
        if (rand(1, 100) === 1) {
            $this->cleanup_expired_codes();
        }
    }
    
    public function create_otp_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            code varchar(6) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            attempts int(2) DEFAULT 0,
            PRIMARY KEY (id),
            KEY email (email),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function enqueue_scripts() {
        // Only load on login page or if we're in admin and it's the login page
        if (is_admin() && !isset($GLOBALS['pagenow'])) return;
        if (isset($GLOBALS['pagenow']) && !in_array($GLOBALS['pagenow'], array('wp-login.php')) && !did_action('login_enqueue_scripts')) {
            return;
        }
             
        wp_enqueue_script(
            'loginpress-otp-auth',
            plugin_dir_url(__FILE__) . 'assets/js/loginpress-otp.js',
            array('jquery'),
            '1.0.2',
            true
        );
        
        wp_localize_script('loginpress-otp-auth', 'loginpress_otp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('loginpress_otp_nonce')
        ));
        
        wp_enqueue_style(
            'loginpress-otp-style',
            plugin_dir_url(__FILE__) . 'assets/css/loginpress-otp.css',
            array(),
            '1.0.2'
        );
    }
    
    public function add_otp_fields() {
        ?>
        <div id="otp-auth-container" style="display: none;">

            <div id="otp-messages"></div>

            <p>
                <label for="otp_email"><?php _e('Email Address', 'loginpress-otp'); ?></label>
                <input type="email" name="otp_email" id="otp_email" class="input" size="20" />
            </p>
            <p>
                <input type="button" id="send-otp-btn" class="button button-primary" style="margin-bottom:15px;margin-top:-10px;" value="<?php _e('Send Code', 'loginpress-otp'); ?>" />
            </p>
            <div id="otp-code-section" style="display: none;">
                <p>
                    <label for="otp_code"><?php _e('6-Digit Passcode', 'loginpress-otp'); ?></label>
                    <input type="text" name="otp_code" id="otp_code" class="input" size="20" maxlength="6" />
                </p>
                <p>
                    <input type="button" id="verify-otp-btn" class="button button-primary" value="<?php _e('Verify & Login', 'loginpress-otp'); ?>" style="margin-top: -40px;" />
                    <input type="button" id="resend-otp-btn" class="button" value="<?php _e('Resend Code', 'loginpress-otp'); ?>" style="display:none;" />
                </p>
            </div>
        </div>
        
        <p id="regular-login-toggle-wrap" style="display: none;">
            <a href="/" id="toggle-regular-login"><?php _e('Login with Password', 'loginpress-otp'); ?></a>
        </p>

        <p id="otp-login-toggle-wrap">
            <a href="#" id="toggle-otp-login" class="button button-primary button-large"><?php _e('Login with One-time Passcode', 'loginpress-otp'); ?></a>
        </p>

        <?php
    }
    
    public function send_otp_code() {
        check_ajax_referer('loginpress_otp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'loginpress-otp')));
        }
        
        // Check if user exists with this email
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => __('No account found with this email address.', 'loginpress-otp')));
        }
        
        global $wpdb;

        // Rate limiting
        $ten_minutes_ago = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $code_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s AND created_at >= %s",
            $email,
            $ten_minutes_ago
        ));

        if ($code_count >= 3) {
            wp_send_json_error(array('message' => __('You have requested too many codes. Please try again in 10 minutes.', 'loginpress-otp')));
        }
        
        // Generate 6-digit code
        $code = sprintf('%06d', rand(0, 999999));
        
        // Insert new code
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'email' => $email,
                'code' => $code,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to generate code. Please try again.', 'loginpress-otp')));
        }
        
        // Send email
        $subject = sprintf(__('Your Passcode: %s', 'loginpress-otp'), $code);
        $message = sprintf(
            __('Your 6-digit one-time passcode is: <strong>%s</strong><br><br>This code will expire in 10 minutes.<br><br>If you did not request this code, please ignore this email.', 'loginpress-otp'),
            $code
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success(array('message' => __('Code sent! Please check your email.', 'loginpress-otp')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send email. Please try again.', 'loginpress-otp')));
        }
    }
    
    public function verify_otp_code() {
        check_ajax_referer('loginpress_otp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $code = sanitize_text_field($_POST['code']);
        
        if (!is_email($email) || empty($code)) {
            wp_send_json_error(array('message' => __('Please provide both email and code.', 'loginpress-otp')));
        }
        
        if (!preg_match('/^\d{6}$/', $code)) {
            wp_send_json_error(array('message' => __('Code must be 6 digits.', 'loginpress-otp')));
        }
        
        global $wpdb;
        
        // Get the code record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE email = %s AND code = %s AND expires_at > NOW()",
            $email, $code
        ));
        
        if (!$record) {
            // Increment attempts for any valid record
            $wpdb->query($wpdb->prepare(
                "UPDATE $this->table_name SET attempts = attempts + 1 WHERE email = %s AND expires_at > NOW()",
                $email
            ));
            
            wp_send_json_error(array('message' => __('Invalid or expired code.', 'loginpress-otp')));
        }
        
        // Check attempts
        if ($record->attempts >= 3) {
            wp_send_json_error(array('message' => __('Too many attempts. Please request a new code.', 'loginpress-otp')));
        }
        
        // Get user and log them in
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => __('User account not found.', 'loginpress-otp')));
        }
        
        // Clean up the used code
        $wpdb->delete(
            $this->table_name,
            array('id' => $record->id),
            array('%d')
        );
        
        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, false, is_ssl());
        do_action('wp_login', $user->user_login, $user);
        
        wp_send_json_success(array(
            'message' => __('Login successful!', 'loginpress-otp'),
            'redirect' => admin_url()
        ));
    }
    
    public function cleanup_expired_codes() {
        global $wpdb;
        $wpdb->query("DELETE FROM $this->table_name WHERE expires_at < NOW() OR attempts >= 3");
    }
}

// Initialize the plugin
new LoginPressOTPEmailAuth();

?>
