<?php
class Sumsub_KYC_Cron {
    public function __construct() {
        add_action('sumsub_kyc_check_users', [$this, 'check_users']);
        if (! wp_next_scheduled('sumsub_kyc_check_users')) {
            wp_schedule_event(time(), 'hourly', 'sumsub_kyc_check_users');
        }
    }

    public function check_users() {
        $users = get_users(['meta_key' => 'kyc_status', 'meta_value' => 'pending']);
        $reminder_days = get_option('sumsub_reminder_days', 3);
        $block_days = get_option('sumsub_block_days', 6);
        $deactivate_days = get_option('sumsub_deactivate_days', 30);
        $email_reminder = get_option('sumsub_email_reminder', '<p>Please complete your KYC verification within {days} days. <a href="{verification_url}">Verify Now</a></p>');
        $email_block = get_option('sumsub_email_block', '<p>Your account has been blocked due to incomplete KYC.</p>');
        $email_deactivate = get_option('sumsub_email_deactivate', '<p>Your account has been deactivated due to prolonged incomplete KYC.</p>');

        foreach($users as $user) {
            $initiated = get_user_meta($user->ID, 'kyc_initiated', true);
            if (!$initiated) continue; // Skip if not initiated

            $days = floor((time() - $initiated) / DAY_IN_SECONDS);

            if ($days >= $reminder_days && $days < $block_days) {
                $subject = "Reminder: Complete your KYC";
                $message = str_replace('{days}', $reminder_days, $email_reminder);
                $api = new Sumsub_KYC_API();
                $verification_url = $api->getVerificationUrl($user->ID);
                if ($verification_url) {
                    $message = str_replace('{verification_url}', $verification_url, $message);
                } else {
                    $message = str_replace('{verification_url}', '#', $message);
                }
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                wp_mail($user->user_email, $subject, $message, $headers);
                error_log("Sumsub Cron: Sent reminder to user {$user->ID}");
            } elseif ($days >= $block_days && $days < $deactivate_days) {
                // Check if already blocked
                $already_blocked = get_user_meta($user->ID, 'account_blocked', true);
                if (!$already_blocked) {
                    update_user_meta($user->ID, 'account_blocked', true);
                    error_log("Sumsub Cron: Blocked user {$user->ID} due to incomplete KYC");
                    // Notify admin
                    wp_mail(get_option('admin_email'), "User Blocked", "User {$user->ID} blocked due to incomplete KYC.");
                    // Notify user
                    $headers = ['Content-Type: text/html; charset=UTF-8'];
                    wp_mail($user->user_email, "Account Blocked", $email_block, $headers);
                }
            } elseif ($days >= $deactivate_days) {
                // Check if already deactivated
                $already_deactivated = get_user_meta($user->ID, 'account_deactivated', true);
                if (!$already_deactivated) {
                    update_user_meta($user->ID, 'account_deactivated', true);
                    error_log("Sumsub Cron: Deactivated user {$user->ID} after {$deactivate_days} days");
                    // Notify admin
                    wp_mail(get_option('admin_email'), "User Deactivated", "User {$user->ID} deactivated due to prolonged incomplete KYC.");
                    // Notify user
                    $headers = ['Content-Type: text/html; charset=UTF-8'];
                    wp_mail($user->user_email, "Account Deactivated", $email_deactivate, $headers);
                }
            }
        }
    }
}
