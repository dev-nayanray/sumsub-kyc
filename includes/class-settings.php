<?php
class Sumsub_KYC_Settings {
    private $temp_email_domains = [
        '10minutemail.com',
        'temp-mail.org',
        'guerrillamail.com',
        'mailinator.com',
        'throwaway.email',
        'tempail.com',
        'yopmail.com',
        'maildrop.cc',
        'temp-mail.io',
        'dispostable.com'
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_sumsub_send_reminder', [$this, 'ajax_send_reminder']);
        add_action('wp_ajax_sumsub_initiate_kyc', [$this, 'ajax_initiate_kyc']);
        add_action('wp_ajax_sumsub_block_user', [$this, 'ajax_block_user']);
        add_action('wp_ajax_sumsub_unblock_user', [$this, 'ajax_unblock_user']);
        add_action('wp_ajax_sumsub_scan_temp_emails', [$this, 'ajax_scan_temp_emails']);
        add_filter('pre_user_query', [$this, 'exclude_blocked_users']);
    }

    public function exclude_blocked_users($query) {
        global $wpdb;
        $query->query_where .= " AND {$wpdb->users}.ID NOT IN (
            SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'account_blocked' AND meta_value = '1'
        ) AND {$wpdb->users}.ID NOT IN (
            SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'account_deactivated' AND meta_value = '1'
        )";
    }

    public function add_admin_menu() {
        add_menu_page(
            'Sumsub KYC',
            'Sumsub KYC',
            'manage_options',
            'sumsub-kyc',
            [$this, 'settings_page'],
            'dashicons-shield',
            30
        );
        add_submenu_page(
            'sumsub-kyc',
            'Settings',
            'Settings',
            'manage_options',
            'sumsub-kyc',
            [$this, 'settings_page']
        );
        add_submenu_page(
            'sumsub-kyc',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'sumsub-kyc-dashboard',
            [$this, 'dashboard_page']
        );
    }

    public function register_settings() {
        register_setting('sumsub_kyc_options', 'sumsub_api_token', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('sumsub_kyc_options', 'sumsub_webhook_secret', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('sumsub_kyc_options', 'sumsub_reminder_days', ['sanitize_callback' => 'intval', 'default' => 3]);
        register_setting('sumsub_kyc_options', 'sumsub_block_days', ['sanitize_callback' => 'intval', 'default' => 6]);
        register_setting('sumsub_kyc_options', 'sumsub_deactivate_days', ['sanitize_callback' => 'intval', 'default' => 30]);
        register_setting('sumsub_kyc_options', 'sumsub_email_reminder', ['sanitize_callback' => 'wp_kses_post', 'default' => '<p>Please complete your KYC verification within {days} days. <a href="{verification_url}">Verify Now</a></p>']);
        register_setting('sumsub_kyc_options', 'sumsub_email_block', ['sanitize_callback' => 'wp_kses_post', 'default' => '<p>Your account has been blocked due to incomplete KYC.</p>']);
        register_setting('sumsub_kyc_options', 'sumsub_email_block_temp', ['sanitize_callback' => 'wp_kses_post', 'default' => '<p>Your account has been blocked due to use of temporary email.</p>']);
        register_setting('sumsub_kyc_options', 'sumsub_email_deactivate', ['sanitize_callback' => 'wp_kses_post', 'default' => '<p>Your account has been deactivated due to prolonged incomplete KYC.</p>']);
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sumsub-kyc') !== false) {
            wp_enqueue_style('sumsub-admin-css', plugins_url('assets/admin.css', dirname(__FILE__)), [], '1.0');
            wp_enqueue_script('sumsub-admin-js', plugins_url('assets/admin.js', dirname(__FILE__)), ['jquery'], '1.0', true);
        }
    }

    public function settings_page() {
        ?>
        <div class="wrap sumsub-settings">
            <h1>Sumsub KYC Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('sumsub_kyc_options');
                do_settings_sections('sumsub-kyc');
                ?>
                <div class="sumsub-card">
                    <h2 class="sumsub-section-title dashicons-before dashicons-admin-network">API Configuration</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">API Token</th>
                            <td><input type="text" name="sumsub_api_token" value="<?php echo esc_attr(get_option('sumsub_api_token')); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Webhook Secret</th>
                            <td><input type="text" name="sumsub_webhook_secret" value="<?php echo esc_attr(get_option('sumsub_webhook_secret')); ?>" /></td>
                        </tr>
                    </table>
                </div>
                <div class="sumsub-card">
                    <h2 class="sumsub-section-title dashicons-before dashicons-clock">Automation Settings</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Reminder Days</th>
                            <td><input type="number" name="sumsub_reminder_days" value="<?php echo esc_attr(get_option('sumsub_reminder_days', 3)); ?>" min="1" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Block Days</th>
                            <td><input type="number" name="sumsub_block_days" value="<?php echo esc_attr(get_option('sumsub_block_days', 6)); ?>" min="1" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Deactivate Days</th>
                            <td><input type="number" name="sumsub_deactivate_days" value="<?php echo esc_attr(get_option('sumsub_deactivate_days', 30)); ?>" min="1" /></td>
                        </tr>
                    </table>
                </div>
                <div class="sumsub-card">
                    <h2 class="sumsub-section-title dashicons-before dashicons-email">Email Templates</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Reminder Email</th>
                            <td><textarea name="sumsub_email_reminder"><?php echo esc_textarea(get_option('sumsub_email_reminder', 'Please complete your KYC verification within {days} days.')); ?></textarea></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Block Email</th>
                            <td><textarea name="sumsub_email_block"><?php echo esc_textarea(get_option('sumsub_email_block', 'Your account has been blocked due to incomplete KYC.')); ?></textarea></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Temp Email Block Email</th>
                            <td><textarea name="sumsub_email_block_temp"><?php echo esc_textarea(get_option('sumsub_email_block_temp', 'Your account has been blocked due to use of temporary email.')); ?></textarea></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Deactivate Email</th>
                            <td><textarea name="sumsub_email_deactivate"><?php echo esc_textarea(get_option('sumsub_email_deactivate', 'Your account has been deactivated due to prolonged incomplete KYC.')); ?></textarea></td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function dashboard_page() {
        // Get all users
        $users = get_users(['number' => -1, 'orderby' => 'ID', 'order' => 'DESC']);

        $scan_nonce = wp_create_nonce('sumsub_scan_temp');
        ?>
        <div class="wrap sumsub-dashboard">
            <h1>Sumsub KYC Dashboard</h1>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button class="button sumsub-scan-temp-emails" data-nonce="<?php echo $scan_nonce; ?>">Scan for Temp Emails</button>
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registration Date</th>
                        <th>KYC Status</th>
                        <th>Initiated</th>
                        <th>Days Since</th>
                        <th>Blocked</th>
                        <th>Deactivated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="10">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $status = get_user_meta($user->ID, 'kyc_status', true) ?: 'Not Initiated';
                            $initiated = get_user_meta($user->ID, 'kyc_initiated', true);
                            $blocked_meta = get_user_meta($user->ID, 'account_blocked', true);
                            $blocked = $blocked_meta ? 'Yes' : 'No';
                            $deactivated_meta = get_user_meta($user->ID, 'account_deactivated', true);
                            $deactivated = $deactivated_meta ? 'Yes' : 'No';
                            $days = $initiated ? floor((time() - $initiated) / DAY_IN_SECONDS) : '-';
                            $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name;
                            $registered = date('Y-m-d', strtotime($user->user_registered));
                            $nonce = wp_create_nonce('sumsub_action_' . $user->ID);
                            ?>
                            <tr>
                                <td><?php echo $user->ID; ?></td>
                                <td><?php echo esc_html($name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo $registered; ?></td>
                                <td class="kyc-status status-<?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $status))); ?>"><?php echo esc_html($status); ?></td>
                                <td><?php echo $initiated ? date('Y-m-d', $initiated) : '-'; ?></td>
                                <td><?php echo $days; ?></td>
                                <td class="blocked-status blocked-<?php echo $blocked_meta ? 'yes' : 'no'; ?>"><?php echo $blocked; ?></td>
                                <td class="deactivated-status deactivated-<?php echo $deactivated_meta ? 'yes' : 'no'; ?>"><?php echo $deactivated; ?></td>
                                <td>
                                    <button class="button sumsub-send-reminder" data-user-id="<?php echo $user->ID; ?>" data-nonce="<?php echo $nonce; ?>">Send Reminder</button>
                                    <button class="button sumsub-initiate-kyc" data-user-id="<?php echo $user->ID; ?>" data-nonce="<?php echo $nonce; ?>">Initiate KYC</button>
                                    <?php if (!$blocked_meta && !$deactivated_meta): ?>
                                        <button class="button sumsub-block-user" data-user-id="<?php echo $user->ID; ?>" data-nonce="<?php echo $nonce; ?>">Block</button>
                                    <?php else: ?>
                                        <button class="button sumsub-unblock-user" data-user-id="<?php echo $user->ID; ?>" data-nonce="<?php echo $nonce; ?>">Unblock</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function ajax_send_reminder() {
        check_ajax_referer('sumsub_action_' . $_POST['user_id'], 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        if (!$user) wp_die('User not found');

        $reminder_days = get_option('sumsub_reminder_days', 3);
        $email_template = get_option('sumsub_email_reminder', '<p>Please complete your KYC verification within {days} days. <a href="{verification_url}">Verify Now</a></p>');
        $message = str_replace('{days}', $reminder_days, $email_template);

        $api = new Sumsub_KYC_API();
        $verification_url = $api->getVerificationUrl($user_id);
        if ($verification_url) {
            $message = str_replace('{verification_url}', $verification_url, $message);
        } else {
            // If no URL, remove the placeholder or link
            $message = str_replace('{verification_url}', '#', $message);
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($user->user_email, 'KYC Reminder', $message, $headers);

        wp_send_json_success('Reminder sent');
    }

    public function ajax_initiate_kyc() {
        check_ajax_referer('sumsub_action_' . $_POST['user_id'], 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        if (!$user) wp_die('User not found');

        $api = new Sumsub_KYC_API();
        $data = [
            'externalUserId' => $user_id,
            'email' => $user->user_email,
            'phone' => '',
            'fixedInfo' => [
                'firstName' => $user->first_name ?: $user->display_name,
                'lastName' => $user->last_name ?: '',
            ]
        ];

        $result = $api->createApplicant($user_id, $data);
        if (!$result) wp_die('Failed to initiate KYC');

        $verification_url = $result['verification_url'] ?? '';
        if ($verification_url) {
            $message = "Please complete your KYC verification: $verification_url";
            wp_mail($user->user_email, 'KYC Verification Link', $message);
        }

        wp_send_json_success('KYC initiated and link sent');
    }

    public function ajax_block_user() {
        check_ajax_referer('sumsub_action_' . $_POST['user_id'], 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        if (!$user) wp_die('User not found');

        // Check if already blocked
        $already_blocked = get_user_meta($user_id, 'account_blocked', true);
        if ($already_blocked) {
            wp_send_json_error('User is already blocked');
            return;
        }

        // Block user
        update_user_meta($user_id, 'account_blocked', true);

        // Send notification email
        $email_template = get_option('sumsub_email_block', '<p>Your account has been blocked due to incomplete KYC.</p>');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($user->user_email, 'Account Blocked', $email_template, $headers);

        error_log("Sumsub Admin: Manually blocked user {$user_id}");

        wp_send_json_success('User blocked successfully');
    }

    public function ajax_unblock_user() {
        check_ajax_referer('sumsub_action_' . $_POST['user_id'], 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        if (!$user) wp_die('User not found');

        // Check if blocked
        $blocked = get_user_meta($user_id, 'account_blocked', true);
        if (!$blocked) {
            wp_send_json_error('User is not blocked');
            return;
        }

        // Unblock user
        delete_user_meta($user_id, 'account_blocked');

        error_log("Sumsub Admin: Manually unblocked user {$user_id}");

        wp_send_json_success('User unblocked successfully');
    }

    public function ajax_scan_temp_emails() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'sumsub_scan_temp')) {
            wp_send_json_error('Invalid nonce');
        }

        $blocked_count = 0;
        $users = get_users(['number' => -1]); // Get all users

        foreach ($users as $user) {
            $domain = strtolower(substr(strrchr($user->user_email, "@"), 1));
            if (in_array($domain, $this->temp_email_domains)) {
                $already_blocked = get_user_meta($user->ID, 'account_blocked', true);
                if (!$already_blocked) {
                    update_user_meta($user->ID, 'account_blocked', true);
                    $email_template = get_option('sumsub_email_block_temp', '<p>Your account has been blocked due to use of temporary email.</p>');
                    $headers = ['Content-Type: text/html; charset=UTF-8'];
                    wp_mail($user->user_email, 'Account Blocked - Temporary Email', $email_template, $headers);
                    error_log("Sumsub Admin: Scanned and blocked user {$user->ID} for temp email: {$user->user_email}");
                    $blocked_count++;
                }
            }
        }

        wp_send_json_success("Scan complete. Blocked $blocked_count users with temporary emails.");
    }
}
