<?php

/**
 * Plugin Name:       WP2Static Add-on: BunnyCDN
 * Plugin URI:        https://wp2static.com
 * Description:       BunnyCDN as a deployment option for WP2Static.
 * Version:           0.1
 * Author:            Leon Stafford
 * Author URI:        https://ljs.dev
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-bunnycdn
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP2STATIC_BUNNYCDN_PATH', plugin_dir_path( __FILE__ ) );

require WP2STATIC_BUNNYCDN_PATH . 'vendor/autoload.php';

// @codingStandardsIgnoreStart
$ajax_action = isset( $_POST['ajax_action'] ) ? $_POST['ajax_action'] : '';
// @codingStandardsIgnoreEnd

if ( $ajax_action == 'test_bunnycdn' ) {
    $bunnycdn = new WP2Static\BunnyCDN();

    $bunnycdn->test_bunnycdn();

    wp_die();
    return null;
} elseif ( $ajax_action == 'bunnycdn_prepare_export' ) {
    $bunnycdn = new WP2Static\BunnyCDN();

    $bunnycdn->bootstrap();
    $bunnycdn->prepareDeploy( true );

    wp_die();
    return null;
} elseif ( $ajax_action == 'bunnycdn_transfer_files' ) {
    $bunnycdn = new WP2Static\BunnyCDN();

    $bunnycdn->bootstrap();
    $bunnycdn->bunnycdn_transfer_files();

    wp_die();
    return null;
} elseif ( $ajax_action == 'bunnycdn_purge_cache' ) {
    $bunnycdn = new WP2Static\BunnyCDN();

    $bunnycdn->bunnycdn_purge_cache();

    wp_die();
    return null;
}

define( 'PLUGIN_NAME_VERSION', '0.1' );

function runBackendDeployment( $method ) {
    if ( $method !== 'bunnycdn' ) {
        return;
    }

    WP2Static\WsLog::l('Starting BunnyCDN headless deployment');

    $bunnyCDN = new WP2Static\BunnyCDN();
    $bunnyCDN->bootstrap();
    $bunnyCDN->prepareDeploy( true );
    $bunnyCDN->bunnycdn_transfer_files();
    $bunnyCDN->bunnycdn_purge_cache();
}

add_filter( 'wp2static_addon_trigger_deploy', 'runBackendDeployment' );

function run_wp2static_addon_bunnycdn() {
    $plugin = new WP2Static\BunnyCDNAddon();
    $plugin->run();
}

run_wp2static_addon_bunnycdn();


  
