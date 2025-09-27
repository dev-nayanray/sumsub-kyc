<?php
class Sumsub_KYC_Hooks {
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
        add_action('wp_login', [$this, 'check_login'], 10, 2);
        add_action('user_register', [$this, 'handle_registration'], 10, 1);
        add_filter('registration_errors', [$this, 'check_temp_email_registration'], 10, 3);
    }

    private function is_temp_email($email) {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        return in_array($domain, $this->temp_email_domains);
    }

    public function check_temp_email_registration($errors, $sanitized_user_login, $user_email) {
        if ($this->is_temp_email($user_email)) {
            $errors->add('temp_email', __('Registration with temporary email addresses is not allowed.'));
        }
        return $errors;
    }

    public function handle_registration($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        if ($this->is_temp_email($user->user_email)) {
            update_user_meta($user_id, 'account_blocked', true);
        $email_template = get_option('sumsub_email_block_temp', '<p>Your account has been blocked due to use of temporary email.</p>');
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($user->user_email, 'Account Blocked - Temporary Email', $email_template, $headers);
            error_log("Sumsub: Blocked new user {$user_id} for temp email: {$user->user_email}");
            return; // Skip KYC initiation
        }

        // Proceed with normal KYC initiation
        $this->initiate_kyc($user_id);
    }

    public function check_login($user_login, $user) {
        $status = get_user_meta($user->ID, 'kyc_status', true);
        $blocked = get_user_meta($user->ID, 'account_blocked', true);
        $deactivated = get_user_meta($user->ID, 'account_deactivated', true);
        if (($blocked || $deactivated) && $status !== 'completed') {
            wp_logout();
            wp_redirect(wp_registration_url()); // Redirect to register page
            exit;
        }
        // Allow access if KYC completed
    }

    public function initiate_kyc($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $api = new Sumsub_KYC_API();
        $data = [
            'externalUserId' => $user_id,
            'email' => $user->user_email,
            'phone' => '', // Add if available
            'fixedInfo' => [
                'firstName' => $user->first_name ?: $user->display_name,
                'lastName' => $user->last_name ?: '',
            ]
        ];

        $result = $api->createApplicant($user_id, $data);
        if (!$result) {
            error_log("Failed to initiate KYC for user $user_id");
            return;
        }

        // Auto-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // Redirect to Sumsub verification
        if (isset($result['verification_url'])) {
            wp_redirect($result['verification_url']);
            exit;
        }
    }
}
