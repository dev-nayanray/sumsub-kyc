<?php
class Sumsub_KYC_API {
    private $api_url = "https://api.sumsub.com";

    private function get_token() {
        return get_option('sumsub_api_token');
    }

    public function createApplicant($user_id, $data) {
        $token = $this->get_token();
        if (!$token) {
            error_log('Sumsub API: Token not set');
            return false;
        }

        $response = wp_remote_post("$this->api_url/resources/applicants?key=$token", [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($data)
        ]);

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("Sumsub API createApplicant failed: HTTP $code - " . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        if ($result && isset($result['id'])) {
            update_user_meta($user_id, 'sumsub_applicant_id', $result['id']);
            update_user_meta($user_id, 'kyc_status', 'pending');
            update_user_meta($user_id, 'kyc_initiated', time());
            error_log("Sumsub API: Created applicant {$result['id']} for user $user_id");

            // Generate access token and verification URL
            $access_token = $this->createAccessToken($result['id']);
            if ($access_token) {
                $verification_url = "https://verification-api.sumsub.com/id-check/{$result['id']}?accessToken={$access_token}";
                update_user_meta($user_id, 'sumsub_verification_url', $verification_url);
                $result['verification_url'] = $verification_url;
            }
        }

        return $result;
    }

    private function createAccessToken($applicant_id) {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $response = wp_remote_post("$this->api_url/resources/applicants/$applicant_id/accessToken?key=$token", [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['type' => 'idCheck'])
        ]);

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("Sumsub API createAccessToken failed: HTTP $code - " . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        return $result['accessToken'] ?? false;
    }

    public function getStatus($applicant_id) {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $response = wp_remote_get("$this->api_url/resources/applicants/$applicant_id/status?key=$token");
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("Sumsub API getStatus failed: HTTP $code");
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    public function createAccessTokenForUser($user_id, $applicant_id) {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $response = wp_remote_post("$this->api_url/resources/accessTokens?key=$token", [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'userId' => $user_id,
                'applicantId' => $applicant_id,
                'ttlInSecs' => 600 // 10 minutes
            ])
        ]);

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("Sumsub API createAccessTokenForUser failed: HTTP $code - " . wp_remote_retrieve_body($response));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        if ($result && isset($result['token'])) {
            return $result['token'];
        }

        return false;
    }

    public function getVerificationUrl($user_id) {
        $applicant_id = get_user_meta($user_id, 'sumsub_applicant_id', true);
        if (!$applicant_id) {
            return false;
        }

        $stored_url = get_user_meta($user_id, 'sumsub_verification_url', true);
        if ($stored_url) {
            // Check if URL is still valid (simple check, assume 10 min)
            $initiated = get_user_meta($user_id, 'kyc_initiated', true);
            if ($initiated && (time() - $initiated) < 600) {
                return $stored_url;
            }
        }

        // Generate new access token
        $access_token = $this->createAccessTokenForUser($user_id, $applicant_id);
        if ($access_token) {
            $verification_url = "https://verification-api.sumsub.com/id-check/{$applicant_id}?accessToken={$access_token}";
            update_user_meta($user_id, 'sumsub_verification_url', $verification_url);
            return $verification_url;
        }

        return false;
    }
}
