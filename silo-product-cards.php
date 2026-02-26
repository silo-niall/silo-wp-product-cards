<?php
/**
 * Plugin Name: SILO Product Cards
 * Description: Dynamic product cards for blog monetisation. Supports manual entry, page scraping, and Magento 2 API integration. Fully self-contained — no ACF required.
 * Version: 1.0.0
 * Author: Niall Cullen — SILO Digital
 * Author URI: https://silodigital.co.uk
 * Text Domain: silo-product-cards
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SILO_PC_VERSION', '1.0.0' );
define( 'SILO_PC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SILO_PC_URL', plugin_dir_url( __FILE__ ) );

// Include classes.
require_once SILO_PC_DIR . 'includes/class-silo-pc-cpt.php';
require_once SILO_PC_DIR . 'includes/class-silo-pc-post-picker.php';
require_once SILO_PC_DIR . 'includes/class-silo-pc-data-source.php';
require_once SILO_PC_DIR . 'includes/class-silo-pc-renderer.php';
require_once SILO_PC_DIR . 'includes/class-silo-pc-admin.php';

// Auto-updates from GitHub.
require_once SILO_PC_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$silo_pc_updater = PucFactory::buildUpdateChecker(
    'https://github.com/silo-niall/silo-wp-product-cards/',
    __FILE__,
    'silo-product-cards'
);

/**
 * Bootstrap the plugin.
 */
add_action( 'plugins_loaded', function () {
    new Silo_PC_CPT();
    new Silo_PC_Post_Picker();
    new Silo_PC_Renderer();
    new Silo_PC_Admin();
} );

/**
 * Set default options on activation.
 */
register_activation_hook( __FILE__, function () {
    $defaults = array(
        'silo_pc_default_data_source' => 'manual',
        'silo_pc_default_tracking'    => 'blog_product_card',
        'silo_pc_cache_ttl'           => 6,
        'silo_pc_magento_api_url'     => '',
        'silo_pc_magento_api_token'   => '',
        'silo_pc_custom_css'          => '',
        'silo_pc_auto_insert'         => 'after_content',
        'silo_pc_scrape_selectors'    => Silo_PC_Data_Source::default_selectors(),
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }

    // Refresh stored selectors when the built-in defaults are updated.
    $stored_ver = (int) get_option( 'silo_pc_selector_version', 0 );
    if ( $stored_ver < Silo_PC_Data_Source::SELECTOR_VERSION ) {
        update_option( 'silo_pc_scrape_selectors', Silo_PC_Data_Source::default_selectors() );
        update_option( 'silo_pc_selector_version', Silo_PC_Data_Source::SELECTOR_VERSION );
    }

    // Flush rewrite rules for the CPT.
    Silo_PC_CPT::register_post_type();
    flush_rewrite_rules();

    // Seed a demo product on first activation.
    if ( false === get_option( 'silo_pc_demo_seeded' ) ) {
        silo_pc_seed_demo_product();
        update_option( 'silo_pc_demo_seeded', '1' );
    }
} );

/**
 * Create a demo product from the example card data.
 */
function silo_pc_seed_demo_product() {
    $post_id = wp_insert_post( array(
        'post_type'   => 'silo_product',
        'post_title'  => 'Example Product',
        'post_status' => 'draft',
    ) );

    if ( ! $post_id || is_wp_error( $post_id ) ) {
        return;
    }

    $meta = array(
        'data_source'      => 'manual',
        'product_url'      => 'https://example.com/product',
        'product_sku'      => '',
        'description'      => 'This is a demo product card. Edit the fields above to add your own product data, or switch to scrape mode to pull data from a product page automatically.',
        'images'           => '',
        'price'            => '49.99',
        'compare_at_price' => '79.99',
        'currency'         => 'USD',
        'badge'            => 'Demo',
        'rating'           => '4.5',
        'review_count'     => '24',
        'tracking_source'  => 'blog_product_card',
        'show_discount'    => '0',
        'discount_code'    => '',
        'discount_percent' => '',
        'discount_label'   => '',
    );

    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, '_silo_pc_' . $key, $value );
    }
}

/**
 * Clean up rewrite rules on deactivation.
 */
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
