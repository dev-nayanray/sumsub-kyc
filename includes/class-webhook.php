<?php
class Sumsub_KYC_Webhook {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('sumsub/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Public, but verify signature
        ]);
    }

    public function handle_webhook(WP_REST_Request $request) {
        $body = $request->get_body();
        $headers = $request->get_headers();

        // Verify signature if secret is set
        $secret = get_option('sumsub_webhook_secret');
        if ($secret && isset($headers['x_payload_digest'])) {
            $digest = $headers['x_payload_digest'][0];
            $expected = hash_hmac('sha256', $body, $secret);
            if (!hash_equals($expected, $digest)) {
                error_log('Sumsub webhook signature verification failed');
                return new WP_Error('invalid_signature', 'Signature verification failed', ['status' => 403]);
            }
        }

        $data = json_decode($body, true);
        if (!$data || !isset($data['applicantId'], $data['reviewStatus'])) {
            return new WP_Error('invalid_payload', 'Invalid webhook payload', ['status' => 400]);
        }

        // Find user by applicantId meta
        $users = get_users([
            'meta_key' => 'sumsub_applicant_id',
            'meta_value' => $data['applicantId'],
            'number' => 1
        ]);

        if (empty($users)) {
            error_log('Sumsub webhook: User not found for applicantId ' . $data['applicantId']);
            return new WP_REST_Response(['status' => 'user_not_found'], 200);
        }

        $user = $users[0];
        $status = $data['reviewStatus']; // e.g., 'completed', 'rejected'

        update_user_meta($user->ID, 'kyc_status', $status);

        if ($status === 'completed') {
            // Optionally unblock if blocked
            delete_user_meta($user->ID, 'account_blocked');
        } elseif ($status === 'rejected') {
            update_user_meta($user->ID, 'account_blocked', true);
        }

        error_log("Sumsub webhook: Updated user {$user->ID} KYC status to {$status}");

        return new WP_REST_Response(['status' => 'updated'], 200);
    }
}
