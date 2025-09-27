<?php
/*
Plugin Name: Sumsub KYC Integration
Description: KYC verification with Sumsub API for WordPress Multisite.
Version: 1.0
Author: Nayan Ray
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('SUMSUB_KYC_PATH', plugin_dir_path(__FILE__));

require_once SUMSUB_KYC_PATH . 'includes/class-api.php';
require_once SUMSUB_KYC_PATH . 'includes/class-cron.php';
require_once SUMSUB_KYC_PATH . 'includes/class-hooks.php';
require_once SUMSUB_KYC_PATH . 'includes/class-settings.php';
require_once SUMSUB_KYC_PATH . 'includes/class-webhook.php';

// Init plugin
add_action('plugins_loaded', function() {
    new Sumsub_KYC_API();
    new Sumsub_KYC_Cron();
    new Sumsub_KYC_Hooks();
    new Sumsub_KYC_Settings();
    new Sumsub_KYC_Webhook();
});
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'sumsub_blocked_users';
    $sql = "CREATE TABLE $table (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_login varchar(60) NOT NULL,
        user_pass varchar(255) NOT NULL,
        user_nicename varchar(50) NOT NULL,
        user_email varchar(100) NOT NULL,
        user_url varchar(100) NOT NULL,
        user_registered datetime NOT NULL,
        user_activation_key varchar(255) NOT NULL,
        user_status int(11) NOT NULL,
        display_name varchar(250) NOT NULL,
        user_meta longtext NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (! wp_next_scheduled('sumsub_kyc_check_users')) {
        wp_schedule_event(time(), 'hourly', 'sumsub_kyc_check_users');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('sumsub_kyc_check_users');
});
